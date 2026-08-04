<?php

declare(strict_types=1);

use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use App\Enums\PartnerCategory;
use App\Enums\StatSource;
use App\Filament\Resources\Partners\PartnerResource;
use App\Filament\Resources\SiteStats\SiteStatResource;
use App\Models\AdminUser;
use App\Models\Merchant;
use App\Models\Partner;
use App\Models\SiteStat;
use App\Support\PartnerImporter;
use App\Support\Showcase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

/**
 * The showcase feed is public and unauthenticated, so these tests exist to pin
 * down what may leave the building: curated logos only, no exact figures, and
 * absolutely nothing financial.
 */
beforeEach(function () {
    Showcase::flush();
});

it('publishes only partners that were explicitly published', function () {
    Partner::create([
        'slug' => 'aloqabank', 'name_uz' => 'Aloqabank',
        'category' => PartnerCategory::Bank, 'is_published' => true,
    ]);
    Partner::create([
        'slug' => 'private-uni', 'name_uz' => 'Maxfiy universitet',
        'category' => PartnerCategory::Institution, 'is_published' => false,
    ]);

    $body = getJson('/api/public/site')->assertOk()->json();

    expect(json_encode($body))
        ->toContain('Aloqabank')
        ->not->toContain('Maxfiy universitet');
});

it('defaults a new partner to unpublished', function () {
    $partner = Partner::create([
        'slug' => 'x', 'name_uz' => 'X', 'category' => PartnerCategory::Bank,
    ]);

    expect($partner->fresh()->is_published)->toBeFalse();
});

it('never exposes a financial field on the public feed', function () {
    Partner::create([
        'slug' => 'bank', 'name_uz' => 'Bank',
        'category' => PartnerCategory::Bank, 'is_published' => true,
    ]);

    $raw = getJson('/api/public/site')->assertOk()->content();

    // Guards against someone widening the payload later: no money-shaped key
    // may appear, and no raw tiyin figure alongside it.
    foreach (['amount', 'balance', 'tiyin', 'commission', 'turnover', 'deposit', 'revenue'] as $forbidden) {
        expect(strtolower($raw))->not->toContain($forbidden);
    }
});

it('rounds an auto stat down and marks it with a plus', function () {
    foreach (range(1, 23) as $i) {
        Merchant::withoutGlobalScopes()->create([
            'name' => "Institution {$i}",
            'type' => MerchantType::University,
            'status' => MerchantStatus::Active,
        ]);
    }

    $stat = SiteStat::where('key', 'institutions')->first();

    expect($stat->value())->toBe('20+');            // 23 → 20, never the exact count
});

it('hides an auto stat while the real count is below one rounding step', function () {
    Merchant::withoutGlobalScopes()->create([
        'name' => 'Only one', 'type' => MerchantType::University,
        'status' => MerchantStatus::Active,
    ]);

    $stat = SiteStat::where('key', 'institutions')->first();   // round_to = 10

    expect($stat->value())->toBeNull();

    $payload = Showcase::payload();
    expect(collect($payload['stats'])->pluck('key'))->not->toContain('institutions');
});

it('counts only published partners in the partners stat', function () {
    foreach (range(1, 12) as $i) {
        Partner::create([
            'slug' => "p{$i}", 'name_uz' => "P{$i}",
            'category' => PartnerCategory::Bank,
            'is_published' => $i <= 7,
        ]);
    }

    expect(StatSource::Partners->count())->toBe(7);
});

it('offers no money source for an automatic stat', function () {
    // Structural guarantee: if a money case is ever added, this fails loudly.
    $values = array_column(StatSource::cases(), 'value');

    expect($values)->toBe(['partners', 'institutions', 'banks', 'payment_providers']);
});

it('groups published partners by category with localised headings', function () {
    Partner::create([
        'slug' => 'b', 'name_uz' => 'Bank A', 'name_ru' => 'Банк А',
        'category' => PartnerCategory::Bank, 'is_published' => true,
    ]);

    $groups = Showcase::payload()['groups'];

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['key'])->toBe('bank')
        ->and($groups[0]['heading']['ru'])->toBe('Банки')
        ->and($groups[0]['partners'][0]['name']['ru'])->toBe('Банк А');
});

it('falls back to the Uzbek name when a translation is missing', function () {
    $p = Partner::create([
        'slug' => 'c', 'name_uz' => 'Faqat uz',
        'category' => PartnerCategory::Bank, 'is_published' => true,
    ]);

    expect($p->names())->toBe(['uz' => 'Faqat uz', 'ru' => 'Faqat uz', 'en' => 'Faqat uz']);
});

it('refreshes the cached feed as soon as a partner is published', function () {
    $partner = Partner::create([
        'slug' => 'late', 'name_uz' => 'Late Bank',
        'category' => PartnerCategory::Bank, 'is_published' => false,
    ]);

    expect(Showcase::payload()['groups'])->toBeEmpty();

    $partner->update(['is_published' => true]);

    expect(Showcase::payload()['groups'])->toHaveCount(1);
});

it('imports institutions as unpublished partner rows', function () {
    $merchant = Merchant::withoutGlobalScopes()->create([
        'name' => 'Toshkent Davlat Universiteti',
        'type' => MerchantType::University,
        'status' => MerchantStatus::Active,
    ]);

    $created = PartnerImporter::import('merchant', [$merchant->id]);
    $partner = Partner::where('source_id', $merchant->id)->first();

    expect($created)->toBe(1)
        ->and($partner->is_published)->toBeFalse()
        ->and($partner->category)->toBe(PartnerCategory::Institution)
        ->and($partner->slug)->toBe('toshkent-davlat-universiteti');
});

it('does not offer an already-imported record for import again', function () {
    $merchant = Merchant::withoutGlobalScopes()->create([
        'name' => 'Imported Uni', 'type' => MerchantType::University,
        'status' => MerchantStatus::Active,
    ]);

    expect(PartnerImporter::options('merchant'))->toHaveKey($merchant->id);

    PartnerImporter::import('merchant', [$merchant->id]);

    expect(PartnerImporter::options('merchant'))->not->toHaveKey($merchant->id);
});

it('renders the admin screens', function () {
    $admin = AdminUser::create([
        'name' => 'Admin', 'email' => 'a@edu-gate.uz',
        'password' => bcrypt('password'), 'is_active' => true,
    ]);

    actingAs($admin, 'admin')
        ->get(PartnerResource::getUrl('index'))->assertOk();

    actingAs($admin, 'admin')
        ->get(SiteStatResource::getUrl('index'))->assertOk();
});

<?php

declare(strict_types=1);

use App\Enums\LegalDocumentType;
use App\Filament\Resources\Legal\LegalDocumentResource;
use App\Filament\Resources\Legal\LegalDocumentVersionResource;
use App\Models\AdminUser;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use Illuminate\Support\Facades\Hash;

/**
 * Legal documents.
 *
 * The point of this feature is not that an admin can edit text — it is that we
 * can show, later, exactly what somebody agreed to and when. Everything below
 * defends that: published text cannot move, an acceptance points at a version
 * rather than a document, and a version announced for next month does not
 * quietly bind anyone today.
 */
function offer(array $version = [], bool $active = true): LegalDocument
{
    $doc = LegalDocument::create([
        'slug' => 'oferta', 'type' => LegalDocumentType::PublicOffer, 'is_active' => $active,
    ]);

    $doc->versions()->create(array_merge([
        'title_uz' => 'Ommaviy oferta',
        'title_ru' => 'Публичная оферта',
        'body_uz' => "## 1. Umumiy qoidalar\n\nMatn.",
    ], $version));

    return $doc->fresh();
}

it('numbers versions in sequence without being told', function () {
    $doc = offer();
    $doc->versions()->create(['body_uz' => 'v2']);
    $doc->versions()->create(['body_uz' => 'v3']);

    expect($doc->versions()->pluck('version')->sort()->values()->all())->toBe([1, 2, 3]);
});

it('keeps a draft off the public page and out of the API', function () {
    $doc = offer();   // created, never published

    expect($doc->currentVersion())->toBeNull();

    $this->get('/hujjat/oferta')->assertNotFound();
    $this->getJson('/api/public/legal/oferta')->assertNotFound();
    $this->getJson('/api/public/legal')->assertOk()->assertJsonPath('data', []);
});

it('serves the document once a version is published', function () {
    $doc = offer();
    $doc->versions()->first()->forceFill(['published_at' => now()])->saveQuietly();

    $this->get('/hujjat/oferta')
        ->assertOk()
        ->assertSee('Ommaviy oferta')
        ->assertSee('Umumiy qoidalar');

    $this->getJson('/api/public/legal/oferta')
        ->assertOk()
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.title.ru', 'Публичная оферта');
});

/**
 * The regression this was written for: effective_from is stored with a time
 * component, so comparing it against a bare "Y-m-d" string as text puts today
 * in the future — and a document effective today would never appear.
 */
it('counts a version effective today as in force', function () {
    $doc = offer(['effective_from' => now()->toDateString()]);
    $doc->versions()->first()->forceFill(['published_at' => now()])->saveQuietly();

    expect($doc->fresh()->currentVersion()?->version)->toBe(1);

    $this->get('/hujjat/oferta')->assertOk();
});

it('does not let an announced version bind anyone before its date', function () {
    $doc = offer();
    $doc->versions()->first()->forceFill([
        'published_at' => now(), 'effective_from' => now()->subDay(),
    ])->saveQuietly();

    $next = $doc->versions()->create(['body_uz' => 'New terms', 'effective_from' => now()->addMonth()]);
    $next->forceFill(['published_at' => now()])->saveQuietly();

    $doc = $doc->fresh();

    // Announcing a change ahead of time is the whole reason for the date.
    expect($doc->currentVersion()->version)->toBe(1)
        ->and($doc->upcomingVersion()->version)->toBe(2);

    $this->get('/hujjat/oferta')->assertOk()->assertDontSee('New terms');
});

it('refuses to edit a published version', function () {
    $doc = offer();
    $version = $doc->versions()->first();
    $version->forceFill(['published_at' => now()])->saveQuietly();

    // An acceptance record is worth nothing if the accepted text can be
    // rewritten afterwards.
    expect(fn () => $version->fresh()->update(['body_uz' => 'quietly different']))
        ->toThrow(RuntimeException::class);
});

it('refuses to delete a published version', function () {
    $doc = offer();
    $version = $doc->versions()->first();
    $version->forceFill(['published_at' => now()])->saveQuietly();

    expect(fn () => $version->fresh()->delete())->toThrow(RuntimeException::class);
});

it('lets a draft be edited and deleted freely', function () {
    $doc = offer();
    $version = $doc->versions()->first();

    $version->update(['body_uz' => 'reworded']);
    expect($version->fresh()->body_uz)->toBe('reworded');

    $version->delete();
    expect($doc->versions()->count())->toBe(0);
});

it('strips markup out of admin-authored text', function () {
    $doc = offer(['body_uz' => "Salom\n\n<script>alert(1)</script>\n\n<img src=x onerror=alert(1)>"]);
    $doc->versions()->first()->forceFill(['published_at' => now()])->saveQuietly();

    $html = $doc->fresh()->currentVersion()->html('uz');

    // The text is written by our own admins, but it lands on a public page.
    expect($html)->not->toContain('<script')
        ->and($html)->not->toContain('onerror')
        ->and($html)->toContain('Salom');
});

it('falls back to another language rather than showing an empty page', function () {
    $doc = offer(['body_ru' => null, 'title_en' => null]);
    $version = $doc->versions()->first();

    // Half-translated is normal while a document is being prepared; a blank
    // page is not an acceptable way to show it.
    expect($version->body('ru'))->toContain('Umumiy qoidalar')
        ->and($version->title('en'))->toBe('Ommaviy oferta');
});

it('hides a document that has been switched off', function () {
    $doc = offer(active: false);
    $doc->versions()->first()->forceFill(['published_at' => now()])->saveQuietly();

    $this->get('/hujjat/oferta')->assertNotFound();
    $this->getJson('/api/public/legal/oferta')->assertNotFound();
});

it('records an acceptance against a version, not a document', function () {
    $doc = offer();
    $v1 = $doc->versions()->first();
    $v1->forceFill(['published_at' => now()])->saveQuietly();

    LegalAcceptance::record($v1, ip: '203.0.113.9', userAgent: 'ClickPay/1.0');

    $row = LegalAcceptance::first();

    // "They accepted the offer" is worthless without saying which text.
    expect($row->legal_document_version_id)->toBe($v1->id)
        ->and($row->ip)->toBe('203.0.113.9')
        ->and($row->accepted_at)->not->toBeNull();
});

it('answers 404 for a slug nobody has created', function () {
    $this->get('/hujjat/nothing-here')->assertNotFound();
    $this->getJson('/api/public/legal/nothing-here')->assertNotFound();
});

it('opens in the language the reader asked for', function () {
    $doc = offer(['body_ru' => '## Общие положения']);
    $doc->versions()->first()->forceFill(['published_at' => now()])->saveQuietly();

    $this->get('/hujjat/oferta?lang=ru')->assertOk()->assertSee('Общие положения');
    $this->get('/hujjat/oferta?lang=uz')->assertOk()->assertSee('Umumiy qoidalar');
});

it('opens both admin screens', function () {
    $admin = AdminUser::create([
        'name' => 'Admin', 'email' => 'admin@edu-gate.uz',
        'password' => Hash::make('x'),
        'is_active' => true, 'password_changed_at' => now(),
    ]);

    $doc = offer();

    foreach ([
        LegalDocumentResource::class,
        LegalDocumentVersionResource::class,
    ] as $resource) {
        Pest\Laravel\actingAs($admin, 'admin')->get($resource::getUrl('index'))->assertOk();
        Pest\Laravel\actingAs($admin, 'admin')->get($resource::getUrl('create'))->assertOk();
    }

    // The markdown editor is the whole point of the version screen.
    Pest\Laravel\actingAs($admin, 'admin')
        ->get(LegalDocumentVersionResource::getUrl('edit', ['record' => $doc->versions()->first()]))
        ->assertOk();
});

it('scaffolds the five documents and can be run twice', function () {
    $this->artisan('edugate:legal-documents')->assertSuccessful();

    expect(LegalDocument::count())->toBe(5)
        ->and(LegalDocument::pluck('slug')->sort()->values()->all())
        ->toBe(['hamkor-shartnomasi', 'maxfiylik', 'muassasa-shartnomasi', 'oferta', 'qaytarish-tartibi']);

    // Safe to re-run after a deploy — it must never duplicate a slug that is
    // already in circulation on printed links.
    $this->artisan('edugate:legal-documents')->assertSuccessful();
    expect(LegalDocument::count())->toBe(5);
});

it('scaffolds documents that are still invisible until text is published', function () {
    $this->artisan('edugate:legal-documents');

    // Active, but with nothing published — so the page 404s and an admin never
    // has to hunt for a second switch after publishing.
    $this->get('/hujjat/oferta')->assertNotFound();
    $this->getJson('/api/public/legal')->assertOk()->assertJsonPath('data', []);
});

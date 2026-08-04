<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\MerchantStatus;
use App\Enums\PartnerCategory;
use App\Enums\PspStatus;
use App\Models\Bank;
use App\Models\Merchant;
use App\Models\Partner;
use App\Models\Psp;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pre-fills marketing partner rows from operational records.
 *
 * This is a typing shortcut, nothing more. Imported rows are always created
 * UNPUBLISHED: being a merchant, PSP or bank in the platform says nothing about
 * whether that organisation has agreed to appear on the public homepage.
 */
class PartnerImporter
{
    /** @return array<string, string> */
    public static function sources(): array
    {
        return [
            'bank' => 'Banks',
            'psp' => 'Payment providers (PSPs)',
            'merchant' => 'Institutions (merchants)',
        ];
    }

    /**
     * Selectable records of one type, excluding anything already imported.
     *
     * @return array<int, string>
     */
    public static function options(?string $type): array
    {
        if (! $type) {
            return [];
        }

        $model = self::modelClass($type);
        $taken = Partner::where('source_type', $model)->pluck('source_id')->all();

        return self::query($type)
            ->whereNotIn('id', $taken)
            ->pluck(self::nameColumn($type), 'id')
            ->all();
    }

    /**
     * @param  array<int|string>  $ids
     * @return int rows created
     */
    public static function import(string $type, array $ids): int
    {
        $model = self::modelClass($type);
        $category = self::category($type);
        $created = 0;

        foreach (self::query($type)->whereIn('id', $ids)->get() as $record) {
            if (Partner::where('source_type', $model)->where('source_id', $record->id)->exists()) {
                continue;
            }

            $name = (string) $record->{self::nameColumn($type)};

            Partner::create([
                'slug' => self::uniqueSlug($name, $record->id),
                'name_uz' => $name,
                'name_ru' => $record->name_ru ?? null,
                'name_en' => $record->name_en ?? null,
                'category' => $category,
                'logo_path' => self::copyLogo($record->logo_path ?? null),
                'source_type' => $model,
                'source_id' => $record->id,
                'is_published' => false,   // never inherit visibility
                'sort_order' => 0,
            ]);

            $created++;
        }

        return $created;
    }

    private static function query(string $type)
    {
        return match ($type) {
            'bank' => Bank::query()->where('is_active', true)->orderBy('name_uz'),
            'psp' => Psp::query()->withoutGlobalScopes()
                ->where('status', PspStatus::Active)->orderBy('name'),
            'merchant' => Merchant::query()->withoutGlobalScopes()
                ->where('status', MerchantStatus::Active)->orderBy('name'),
            default => throw new \InvalidArgumentException("Unknown partner source [{$type}]."),
        };
    }

    private static function modelClass(string $type): string
    {
        return match ($type) {
            'bank' => Bank::class,
            'psp' => Psp::class,
            'merchant' => Merchant::class,
            default => throw new \InvalidArgumentException("Unknown partner source [{$type}]."),
        };
    }

    private static function nameColumn(string $type): string
    {
        return $type === 'bank' ? 'name_uz' : 'name';
    }

    private static function category(string $type): PartnerCategory
    {
        return match ($type) {
            'bank' => PartnerCategory::Bank,
            'psp' => PartnerCategory::PaymentProvider,
            'merchant' => PartnerCategory::Institution,
        };
    }

    private static function uniqueSlug(string $name, int|string $id): string
    {
        $slug = Str::slug($name) ?: 'partner';

        return Partner::where('slug', $slug)->exists() ? $slug.'-'.$id : $slug;
    }

    /**
     * Copy rather than reference the source logo: the marketing asset should
     * survive someone clearing a bank's operational logo, and the two are
     * cropped differently often enough that sharing one file causes surprises.
     */
    private static function copyLogo(?string $path): ?string
    {
        $disk = Storage::disk('public');

        if (blank($path) || ! $disk->exists($path)) {
            return null;
        }

        $target = 'partner-logos/'.basename($path);

        if (! $disk->exists($target)) {
            $disk->copy($path, $target);
        }

        return $target;
    }
}

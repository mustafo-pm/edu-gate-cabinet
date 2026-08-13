<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use League\CommonMark\CommonMarkConverter;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * One version of a legal document. Append-only once published.
 *
 * Editing published text in place would destroy the only thing that makes an
 * acceptance record worth anything — the ability to show, later, the exact
 * words somebody agreed to. Drafts are freely editable; publication freezes
 * them. `isPublished()` is the line.
 */
class LegalDocumentVersion extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [
        'legal_document_id', 'version',
        'title_uz', 'title_ru', 'title_en',
        'body_uz', 'body_ru', 'body_en',
        'published_at', 'published_by', 'effective_from', 'change_note',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'published_at' => 'datetime',
            'effective_from' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Numbered here rather than in the admin form, so a version created by
        // a seeder or a console command is numbered the same way.
        static::creating(function (self $version) {
            if (blank($version->version)) {
                $version->version = $version->document?->nextVersionNumber()
                    ?? (static::where('legal_document_id', $version->legal_document_id)->max('version') + 1);
            }
        });

        // The guard, rather than a note in a README that nobody reads. A
        // published version is a record of what was public; correcting it means
        // a new version, which is also what leaves an audit trail.
        static::updating(function (self $version) {
            if ($version->getOriginal('published_at') !== null) {
                throw new \RuntimeException(
                    'A published legal document version cannot be edited. Publish a new version instead.',
                );
            }
        });

        static::deleting(function (self $version) {
            if ($version->published_at !== null) {
                throw new \RuntimeException('A published legal document version cannot be deleted.');
            }
        });
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'published_by');
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(LegalAcceptance::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function isInForce(): bool
    {
        return $this->isPublished()
            && ($this->effective_from === null || ! $this->effective_from->isFuture());
    }

    /**
     * Title in one locale, falling back so a half-translated document still
     * renders rather than showing an empty heading to the public.
     */
    public function title(?string $locale = null): string
    {
        return $this->pick('title', $locale) ?: $this->document?->slug ?? '';
    }

    public function body(?string $locale = null): string
    {
        return $this->pick('body', $locale) ?? '';
    }

    /** Rendered for display. Markdown in, safe HTML out. */
    public function html(?string $locale = null): string
    {
        return (string) (new CommonMarkConverter([
            // The text is written by our own admins, but it is served on a
            // public page and this costs nothing.
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]))->convert($this->body($locale));
    }

    private function pick(string $field, ?string $locale): ?string
    {
        $locale ??= app()->getLocale();

        $order = match ($locale) {
            'ru' => ['ru', 'uz', 'en'],
            'en' => ['en', 'uz', 'ru'],
            default => ['uz', 'ru', 'en'],
        };

        foreach ($order as $l) {
            if (filled($this->{$field.'_'.$l})) {
                return $this->{$field.'_'.$l};
            }
        }

        return null;
    }
}

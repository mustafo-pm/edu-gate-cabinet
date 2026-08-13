<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use Illuminate\Console\Command;

/**
 * Creates the five legal documents so an admin only has to write the text.
 *
 * The slug is the one field nobody can fix later — it goes into a public
 * address that ends up printed, emailed and linked from PSP apps, so a typo
 * costs more than a typo in a paragraph. Choosing them here rather than in a
 * form field means they are chosen once, by us, and reviewed in a diff.
 *
 * Idempotent: creates what is missing and leaves everything else alone, so it
 * is safe to run again after a deploy.
 */
class ScaffoldLegalDocuments extends Command
{
    protected $signature = 'edugate:legal-documents';

    protected $description = 'Create the legal document placeholders an admin fills in';

    /** @var array<string, array{type: LegalDocumentType, sort: int}> */
    private const DOCUMENTS = [
        'oferta' => ['type' => LegalDocumentType::PublicOffer, 'sort' => 1],
        'maxfiylik' => ['type' => LegalDocumentType::PrivacyPolicy, 'sort' => 2],
        'muassasa-shartnomasi' => ['type' => LegalDocumentType::InstitutionAgreement, 'sort' => 3],
        'hamkor-shartnomasi' => ['type' => LegalDocumentType::PspAgreement, 'sort' => 4],
        'qaytarish-tartibi' => ['type' => LegalDocumentType::RefundPolicy, 'sort' => 5],
    ];

    public function handle(): int
    {
        $created = 0;

        foreach (self::DOCUMENTS as $slug => $spec) {
            if (LegalDocument::where('slug', $slug)->exists()) {
                $this->line("  <fg=gray>exists</>  {$slug}");

                continue;
            }

            LegalDocument::create([
                'slug' => $slug,
                'type' => $spec['type'],
                'sort_order' => $spec['sort'],

                // Active from the start, and still invisible: a document with no
                // published version 404s anyway. Leaving it off would mean an
                // admin publishes text, sees nothing, and hunts for a second
                // switch they were never told about.
                'is_active' => true,
            ]);

            $this->line("  <fg=green>created</> {$slug}  ({$spec['type']->label()})");
            $created++;
        }

        $this->newLine();
        $this->info($created === 0
            ? 'Nothing to do — all five documents already exist.'
            : "Created {$created} document(s).");

        $this->line('Add the text in the admin panel: Website → Legal document text → New.');
        $this->line('Nothing is public until a version is published.');

        return self::SUCCESS;
    }
}

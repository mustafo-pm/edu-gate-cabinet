<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public legal documents — the offer, the privacy policy and their siblings.
 *
 * Deliberately not a CMS. The requirement is not "an admin can edit the text",
 * it is "two years from now we can prove exactly what a person agreed to on a
 * given date". That makes versions append-only, like `transactions` and
 * `deposits`: a published version is never edited, and fixing a typo means
 * publishing version 4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();

            // Appears in the public URL, so it is chosen once and left alone —
            // a changed slug breaks every link printed or emailed so far.
            $table->string('slug', 60)->unique();
            $table->string('type', 40);              // LegalDocumentType

            // Off by default: creating a document must not publish it.
            $table->boolean('is_active')->default(false);

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('legal_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_document_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('version');

            // Markdown, not HTML: it diffs cleanly between versions, renders
            // predictably, and cannot smuggle a script tag onto a public page.
            $table->string('title_uz')->nullable();
            $table->string('title_ru')->nullable();
            $table->string('title_en')->nullable();
            $table->longText('body_uz')->nullable();
            $table->longText('body_ru')->nullable();
            $table->longText('body_en')->nullable();

            // Null while a draft. Set once, at publication, and never cleared —
            // unpublishing would erase the record of what was once public.
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('admin_users')->nullOnDelete();

            // A new offer usually takes effect on a future date, after notice.
            $table->date('effective_from')->nullable();

            // Free text for the admin: "corrected bank details", "counsel
            // review 2026-09". Not shown publicly.
            $table->string('change_note')->nullable();

            $table->timestamps();

            $table->unique(['legal_document_id', 'version']);
            $table->index(['legal_document_id', 'published_at']);
        });

        Schema::create('legal_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_document_version_id')->constrained()->cascadeOnDelete();

            // Polymorphic because the three cabinets have three user tables and
            // a payer has none at all — a payer's acceptance is recorded against
            // the transaction that constituted it.
            $table->nullableMorphs('acceptor');
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('accepted_at');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            $table->index('legal_document_version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_acceptances');
        Schema::dropIfExists('legal_document_versions');
        Schema::dropIfExists('legal_documents');
    }
};

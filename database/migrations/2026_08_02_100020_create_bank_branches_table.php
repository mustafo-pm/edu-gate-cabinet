<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Branch registry keyed by MFO (~1237 rows). This is the routing table:
 * an account's MFO tells us which bank holds it.
 *
 * `bank_id` is NULLABLE on purpose — the source registry ships without it, so
 * links are derived from the branch name and must be confirmed by a human
 * before money is routed (see BranchMatchStatus).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->string('mfo', 5)->unique();
            $table->string('name_uz')->nullable();
            $table->string('name_ru')->nullable();
            $table->string('name_en')->nullable();
            $table->string('region')->nullable();

            $table->string('match_status', 20)->default('unmapped'); // BranchMatchStatus
            $table->string('match_note')->nullable();                // why it matched / needs review

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['bank_id', 'match_status']);
            $table->index('match_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_branches');
    }
};

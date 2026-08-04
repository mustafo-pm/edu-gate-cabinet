<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Public showcase content for edu-gate.uz — a curated logo wall and a strip of
 * trust figures.
 *
 * Curated on purpose. It would be easy to render every merchant and PSP row
 * straight onto the homepage, but naming a client publicly is a consent
 * decision, not a database query: some institutions do not want to be
 * advertised, banks are stricter still, and a suspended merchant must never
 * appear as a "trusted partner". So this is a separate, opt-in table and
 * `is_published` defaults to false.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name_uz');
            $table->string('name_ru')->nullable();
            $table->string('name_en')->nullable();
            $table->string('category', 30);              // PartnerCategory
            $table->string('logo_path')->nullable();
            $table->string('website_url')->nullable();

            // Where the row was pre-filled from, when it mirrors an internal
            // record (bank / psp / merchant). Purely informational — the
            // marketing row never inherits the operational row's visibility.
            $table->nullableMorphs('source');

            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });

        Schema::create('site_stats', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('label_uz');
            $table->string('label_ru')->nullable();
            $table->string('label_en')->nullable();

            $table->string('mode', 10)->default('manual');   // manual | auto
            $table->string('source', 40)->nullable();        // StatSource (counts only)
            $table->string('manual_value', 40)->nullable();  // e.g. "150+"

            // Auto values are rounded DOWN to this step before display, so the
            // site shows "150+" rather than an exact, inferable client count.
            $table->unsignedInteger('round_to')->default(10);

            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        // A bulk insert needs identical keys in every row, so each default
        // carries both value columns and leaves the unused one null.
        $defaults = [
            [
                'key' => 'institutions',
                'label_uz' => 'Ta\'lim muassasasi',
                'label_ru' => 'Учебных заведений',
                'label_en' => 'Institutions',
                'mode' => 'auto', 'source' => 'institutions',
                'manual_value' => null, 'round_to' => 10, 'sort_order' => 10,
            ],
            [
                'key' => 'partners',
                'label_uz' => 'Hamkor tashkilot',
                'label_ru' => 'Партнёрских организаций',
                'label_en' => 'Partner organisations',
                'mode' => 'auto', 'source' => 'partners',
                'manual_value' => null, 'round_to' => 5, 'sort_order' => 20,
            ],
            [
                'key' => 'settlement',
                'label_uz' => 'Hisob-kitob vaqti',
                'label_ru' => 'Время расчёта',
                'label_en' => 'Settlement time',
                'mode' => 'manual', 'source' => null,
                'manual_value' => '0–30s', 'round_to' => 10, 'sort_order' => 30,
            ],
            [
                'key' => 'uptime',
                'label_uz' => 'Platforma barqarorligi',
                'label_ru' => 'Доступность платформы',
                'label_en' => 'Platform uptime',
                'mode' => 'manual', 'source' => null,
                'manual_value' => '99.9%', 'round_to' => 10, 'sort_order' => 40,
            ],
        ];

        DB::table('site_stats')->insert(array_map(
            fn (array $row) => $row + ['is_published' => true, 'created_at' => $now, 'updated_at' => $now],
            $defaults,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('site_stats');
        Schema::dropIfExists('partners');
    }
};

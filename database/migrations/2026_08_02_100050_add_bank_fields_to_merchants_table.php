<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            // Resolved from `mfo` via bank_branches; kept denormalised for display.
            $table->foreignId('bank_id')->nullable()->after('bank_name')
                ->constrained('banks')->nullOnDelete();

            // Banks validate against the registered legal entity name, which is
            // often not the display name ("Toshkent Davlat Universiteti").
            $table->string('legal_name')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_id');
            $table->dropColumn('legal_name');
        });
    }
};

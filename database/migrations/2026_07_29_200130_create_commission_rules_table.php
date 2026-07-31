<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commission resolution priority: category > merchant > global.
 * Higher `priority` wins; scope narrows the match.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('scope');   // CommissionScope: global|merchant|psp|category
            $table->foreignId('merchant_id')->nullable()->constrained('merchants')->cascadeOnDelete();
            $table->foreignId('psp_id')->nullable()->constrained('psps')->cascadeOnDelete();
            $table->string('category')->nullable(); // MerchantType when scope=category
            $table->unsignedInteger('rate_bps')->default(0); // basis points (100 bps = 1%)
            $table->unsignedBigInteger('fixed_fee')->default(0); // tiyin, added on top
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['scope', 'is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
    }
};

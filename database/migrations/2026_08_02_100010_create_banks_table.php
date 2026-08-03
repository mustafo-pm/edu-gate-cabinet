<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Commercial banks (~38 rows). Reference data. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();       // registry code, e.g. 20012
            $table->string('slug', 60)->unique();       // stable key for drivers, e.g. aloqabank
            $table->string('name_uz');
            $table->string('name_ru')->nullable();
            $table->string('name_en')->nullable();
            $table->string('logo_path')->nullable();    // empty until logos are supplied
            $table->string('swift', 20)->nullable();

            // A2A capability — set once we hold an account + integration there.
            $table->boolean('a2a_supported')->default(false);
            $table->string('a2a_driver', 40)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};

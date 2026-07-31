<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psp_id')->constrained('psps')->cascadeOnDelete();
            $table->string('name');
            $table->string('key_id', 40)->unique();     // public identifier (shown)
            $table->string('secret_hash');              // hash of the secret (never stored plain)
            $table->string('environment')->default('sandbox'); // sandbox|live
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['psp_id', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');                 // MerchantType: university|school|kindergarten
            $table->string('status')->default('pending'); // MerchantStatus
            $table->string('stir', 20)->nullable();  // tax id (INN)
            $table->string('mfo', 20)->nullable();    // bank code
            $table->string('bank_account', 30)->nullable();
            $table->string('bank_name')->nullable();
            $table->unsignedInteger('commission_bps')->default(0); // default commission, basis points
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('contact_email')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};

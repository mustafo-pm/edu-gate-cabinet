<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();      // slug used in webhooks: /webhooks/{psp}
            $table->string('status')->default('pending'); // PspStatus
            $table->unsignedInteger('commission_bps')->default(0); // PSP-side commission, basis points
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('webhook_url')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psps');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('title');                 // e.g. "Tuition — Sept 2026"
            $table->string('period', 20)->nullable(); // e.g. "2026-09"
            $table->unsignedBigInteger('amount');     // tiyin (1 UZS = 100 tiyin)
            $table->unsignedBigInteger('paid_amount')->default(0); // tiyin
            $table->date('due_date');
            $table->string('status')->default('unpaid'); // ScheduleStatus
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
            $table->index(['student_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_schedules');
    }
};

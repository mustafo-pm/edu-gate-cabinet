<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('student_id_number', 50); // unique per merchant (see composite index below)
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('status')->default('active'); // active|inactive
            $table->string('parent_name')->nullable();
            $table->string('parent_phone', 30)->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'student_id_number']);
            $table->index(['merchant_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};

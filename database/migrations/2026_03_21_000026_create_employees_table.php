<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('employee_number', 20);
            $table->string('first_name');
            $table->string('last_name');
            $table->string('job_title')->nullable();
            $table->string('department', 100)->nullable();
            $table->enum('employment_type', ['full_time', 'part_time', 'contract'])->default('full_time');
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->date('hire_date');
            $table->date('termination_date')->nullable();
            $table->string('tpin', 10)->nullable();
            $table->string('napsa_number', 20)->nullable();
            $table->string('nhima_number', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account', 30)->nullable();
            $table->string('bank_branch', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'employee_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7)->comment('YYYY-MM, e.g. 2026-03');
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['draft', 'approved'])->default('draft');
            $table->decimal('total_gross', 15, 2)->default(0);
            $table->decimal('total_paye', 15, 2)->default(0);
            $table->decimal('total_napsa_employee', 15, 2)->default(0);
            $table->decimal('total_napsa_employer', 15, 2)->default(0);
            $table->decimal('total_nhima_employee', 15, 2)->default(0);
            $table->decimal('total_nhima_employer', 15, 2)->default(0);
            $table->decimal('total_net', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};

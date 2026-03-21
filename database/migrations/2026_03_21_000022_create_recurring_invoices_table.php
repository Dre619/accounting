<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('frequency', ['weekly', 'monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->unsignedTinyInteger('day_of_month')->default(1); // 1-28
            $table->unsignedSmallInteger('days_due')->default(30);   // days until due after issue
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('next_run_at')->nullable();
            $table->date('last_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('recurring_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description', 255);
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tax_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_invoice_items');
        Schema::dropIfExists('recurring_invoices');
    }
};

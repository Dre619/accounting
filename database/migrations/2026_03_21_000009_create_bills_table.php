<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bills = supplier invoices (money owed to suppliers)
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->comment('Supplier');
            $table->string('bill_number', 30)->nullable()->comment('Supplier invoice number');
            $table->string('reference', 100)->nullable();
            $table->enum('status', ['draft', 'approved', 'partial', 'paid', 'overdue', 'void'])->default('draft');
            $table->date('issue_date');
            $table->date('due_date');
            $table->text('notes')->nullable();

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('withholding_tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('amount_due', 15, 2)->default(0);

            $table->foreignId('payable_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};

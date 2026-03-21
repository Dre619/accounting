<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained();
            $table->string('invoice_number', 30)->comment('e.g. INV-0001');
            $table->enum('status', ['draft', 'sent', 'partial', 'paid', 'overdue', 'void'])->default('draft');
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('reference', 100)->nullable()->comment('Customer PO or reference number');
            $table->text('notes')->nullable();
            $table->text('footer')->nullable();

            // Amounts (stored in company currency)
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('withholding_tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('amount_due', 15, 2)->default(0);

            $table->foreignId('receivable_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'invoice_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

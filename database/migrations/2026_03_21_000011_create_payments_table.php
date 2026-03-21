<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['receipt', 'payment'])
                ->comment('receipt = money received from customer, payment = money paid to supplier');
            $table->string('payment_number', 30)->nullable();
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->decimal('withholding_tax_amount', 15, 2)->default(0);
            $table->enum('method', [
                'cash',
                'bank_transfer',
                'cheque',
                'airtel_money',
                'mtn_money',
                'zamtel_money',
                'other',
            ])->default('bank_transfer');
            $table->string('reference', 100)->nullable()->comment('Cheque no, transaction ID, etc.');
            $table->foreignId('deposit_account_id')->constrained('accounts')
                ->comment('Bank/cash account where money lands');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

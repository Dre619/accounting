<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_category_id')->constrained();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('code', 20);                     // e.g. "1000", "4100"
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->enum('type', [
                'asset',
                'liability',
                'equity',
                'income',
                'expense',
            ]);
            $table->enum('subtype', [
                // Asset subtypes
                'current_asset', 'fixed_asset', 'other_asset',
                // Liability subtypes
                'current_liability', 'long_term_liability',
                // Equity subtypes
                'equity',
                // Income subtypes
                'operating_income', 'other_income',
                // Expense subtypes
                'cost_of_goods_sold', 'operating_expense', 'other_expense',
            ])->nullable();
            $table->boolean('is_bank_account')->default(false);
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->boolean('is_system')->default(false)->comment('System accounts cannot be deleted');
            $table->boolean('is_active')->default(true);
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->date('opening_balance_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};

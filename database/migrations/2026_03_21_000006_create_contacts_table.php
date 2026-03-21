<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['customer', 'supplier', 'both'])->default('customer');
            $table->string('name');
            $table->string('tpin', 10)->nullable()->comment('ZRA TPIN for WHT purposes');
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->default('Zambia');
            $table->foreignId('default_receivable_account_id')->nullable()
                ->constrained('accounts')->nullOnDelete();
            $table->foreignId('default_payable_account_id')->nullable()
                ->constrained('accounts')->nullOnDelete();
            $table->foreignId('default_tax_rate_id')->nullable()
                ->constrained('tax_rates')->nullOnDelete();
            $table->boolean('withholding_tax_applicable')->default(false);
            $table->string('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};

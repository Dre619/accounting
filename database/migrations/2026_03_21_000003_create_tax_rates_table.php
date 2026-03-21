<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);                    // e.g. "Standard VAT", "WHT on Services"
            $table->string('code', 20);                     // e.g. "VAT16", "WHT15"
            $table->enum('type', ['vat', 'withholding', 'other'])->default('vat');
            $table->decimal('rate', 5, 2);                  // e.g. 16.00, 15.00
            $table->boolean('is_compound')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};

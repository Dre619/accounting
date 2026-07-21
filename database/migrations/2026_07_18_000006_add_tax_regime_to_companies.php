<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // 'turnover' = Turnover Tax (final tax, in lieu of income tax, not VAT registered).
            // 'standard' = VAT + Corporate Income Tax.
            $table->enum('tax_regime', ['standard', 'turnover'])->default('standard')->after('currency');
            // Rate is deliberately nullable and configurable: Zambian TOT rates and
            // thresholds are reset by each annual Finance Act, so nothing is hard-coded.
            $table->decimal('tot_rate', 5, 2)->nullable()->after('tax_regime')
                ->comment('Turnover tax rate %, per current ZRA guidance');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['tax_regime', 'tot_rate']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 'turnover' joins vat/withholding so TOT rates live alongside other taxes.
        Schema::table('tax_rates', function (Blueprint $table) {
            $table->enum('type', ['vat', 'withholding', 'turnover', 'other'])->default('vat')->change();
        });

        // Null effective_from = "since forever"; null effective_to = "still current".
        Schema::table('tax_rates', function (Blueprint $table) {
            $table->date('effective_from')->nullable()->after('rate');
            $table->date('effective_to')->nullable()->after('effective_from');
        });

        // A code may now repeat across rate versions, distinguished by start date.
        Schema::table('tax_rates', function (Blueprint $table) {
            $table->dropUnique('tax_rates_company_id_code_unique');
        });

        Schema::table('tax_rates', function (Blueprint $table) {
            $table->unique(['company_id', 'code', 'effective_from'], 'tax_rates_company_code_from_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tax_rates', function (Blueprint $table) {
            $table->dropUnique('tax_rates_company_code_from_unique');
        });

        Schema::table('tax_rates', function (Blueprint $table) {
            $table->dropColumn(['effective_from', 'effective_to']);
        });

        Schema::table('tax_rates', function (Blueprint $table) {
            $table->unique(['company_id', 'code'], 'tax_rates_company_id_code_unique');
            $table->enum('type', ['vat', 'withholding', 'other'])->default('vat')->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->enum('subtype', [
                'current_asset', 'fixed_asset', 'other_asset',
                'current_liability', 'long_term_liability',
                'equity',
                'operating_income', 'other_income',
                'cost_of_goods_sold', 'operating_expense', 'other_expense',
                'taxation', // income/turnover tax — presented below operating profit
            ])->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->enum('subtype', [
                'current_asset', 'fixed_asset', 'other_asset',
                'current_liability', 'long_term_liability',
                'equity',
                'operating_income', 'other_income',
                'cost_of_goods_sold', 'operating_expense', 'other_expense',
            ])->nullable()->change();
        });
    }
};

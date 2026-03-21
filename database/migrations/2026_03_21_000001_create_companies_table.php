<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('tpin', 10)->nullable()->comment('ZRA Tax Payer Identification Number');
            $table->string('vat_number', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->default('Zambia');
            $table->string('currency', 3)->default('ZMW');
            $table->string('financial_year_end', 5)->default('12-31')->comment('MM-DD format');
            $table->string('invoice_prefix', 10)->default('INV');
            $table->unsignedInteger('invoice_sequence')->default(1);
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};

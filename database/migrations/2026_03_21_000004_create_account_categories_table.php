<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_categories', function (Blueprint $table) {
            $table->id();
            // Null company_id = system/default category available to all companies
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->enum('type', [
                'asset',
                'liability',
                'equity',
                'income',
                'expense',
            ]);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_categories');
    }
};

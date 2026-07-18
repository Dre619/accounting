<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->enum('source', [
                'manual',
                'invoice',
                'bill',
                'payment',
                'opening',
                'stock_adjustment',
            ])->default('manual')->change();
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->enum('source', [
                'manual',
                'invoice',
                'bill',
                'payment',
                'opening',
            ])->default('manual')->change();
        });
    }
};

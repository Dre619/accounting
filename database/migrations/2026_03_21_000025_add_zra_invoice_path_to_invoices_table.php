<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('zra_invoice_path')->nullable()->after('zra_mrc_no')
                ->comment('Path to an externally-generated ZRA invoice document (PDF/image) uploaded by the user');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('zra_invoice_path');
        });
    }
};

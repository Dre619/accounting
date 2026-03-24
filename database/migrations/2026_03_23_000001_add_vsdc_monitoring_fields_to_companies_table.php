<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('vsdc_status', 20)->nullable()->default(null)->after('vsdc_mrc_no')
                ->comment('online | offline | error');
            $table->timestamp('vsdc_last_seen_at')->nullable()->after('vsdc_status');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['vsdc_status', 'vsdc_last_seen_at']);
        });
    }
};

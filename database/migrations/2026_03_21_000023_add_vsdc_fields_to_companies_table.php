<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('vsdc_url', 255)->nullable()->after('logo_path')
                ->comment('Base URL of the local VSDC, e.g. http://localhost:8080');
            $table->string('vsdc_bhf_id', 3)->nullable()->after('vsdc_url')
                ->comment('Branch/Head-Office ID, e.g. 00');
            $table->string('vsdc_dvc_srl_no', 100)->nullable()->after('vsdc_bhf_id')
                ->comment('Device serial number used during initialization');
            $table->boolean('vsdc_initialized')->default(false)->after('vsdc_dvc_srl_no');
            $table->string('vsdc_sdc_id', 50)->nullable()->after('vsdc_initialized')
                ->comment('SDC ID returned by VSDC init');
            $table->string('vsdc_mrc_no', 50)->nullable()->after('vsdc_sdc_id')
                ->comment('MRC number returned by VSDC init');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'vsdc_url', 'vsdc_bhf_id', 'vsdc_dvc_srl_no',
                'vsdc_initialized', 'vsdc_sdc_id', 'vsdc_mrc_no',
            ]);
        });
    }
};

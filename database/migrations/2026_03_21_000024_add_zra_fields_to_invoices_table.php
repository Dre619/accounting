<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('zra_submitted_at')->nullable()->after('voided_at')
                ->comment('When the invoice was successfully submitted to ZRA VSDC');
            $table->unsignedInteger('zra_rcpt_no')->nullable()->after('zra_submitted_at')
                ->comment('Receipt number from VSDC saveSales response');
            $table->string('zra_internal_data', 500)->nullable()->after('zra_rcpt_no')
                ->comment('intrlData from VSDC saveSales response');
            $table->string('zra_rcpt_sign', 500)->nullable()->after('zra_internal_data')
                ->comment('rcptSign from VSDC saveSales response');
            $table->string('zra_sdc_id', 50)->nullable()->after('zra_rcpt_sign')
                ->comment('sdcId from VSDC saveSales response');
            $table->string('zra_mrc_no', 50)->nullable()->after('zra_sdc_id')
                ->comment('mrcNo from VSDC saveSales response');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'zra_submitted_at', 'zra_rcpt_no', 'zra_internal_data',
                'zra_rcpt_sign', 'zra_sdc_id', 'zra_mrc_no',
            ]);
        });
    }
};

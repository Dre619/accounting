<?php

use App\Models\TaxRate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Carry any single rate already captured on the company across to an
        // open-ended (always effective) turnover tax rate, so nothing is lost.
        DB::table('companies')->whereNotNull('tot_rate')->orderBy('id')
            ->each(function ($company) {
                $exists = TaxRate::where('company_id', $company->id)->where('type', 'turnover')->exists();
                if ($exists) {
                    return;
                }

                TaxRate::create([
                    'company_id'     => $company->id,
                    'name'           => 'Turnover Tax',
                    'code'           => 'TOT',
                    'type'           => 'turnover',
                    'rate'           => $company->tot_rate,
                    'effective_from' => null,
                    'effective_to'   => null,
                    'is_active'      => true,
                ]);
            });

        // Single source of truth is now tax_rates.
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('tot_rate');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->decimal('tot_rate', 5, 2)->nullable()->after('tax_regime');
        });
    }
};

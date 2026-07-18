<?php

use App\Models\Company;
use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Company::each(function (Company $company) {
            if (Warehouse::where('company_id', $company->id)->exists()) {
                return;
            }

            Warehouse::create([
                'company_id' => $company->id,
                'name'       => 'Main Warehouse',
                'code'       => 'MAIN',
                'is_default' => true,
                'is_active'  => true,
            ]);
        });
    }

    public function down(): void
    {
        Warehouse::where('code', 'MAIN')->where('is_default', true)->delete();
    }
};

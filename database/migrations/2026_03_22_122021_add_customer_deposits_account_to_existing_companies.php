<?php

use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Company;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Company::each(function (Company $company) {
            if (Account::where('company_id', $company->id)->where('code', '2050')->exists()) {
                return;
            }

            $category = AccountCategory::where('company_id', $company->id)
                ->where('name', 'Current Liabilities')
                ->first();

            if (! $category) {
                return;
            }

            Account::create([
                'company_id'          => $company->id,
                'account_category_id' => $category->id,
                'code'                => '2050',
                'name'                => 'Customer Deposits',
                'type'                => 'liability',
                'sub_type'            => 'current_liability',
                'is_active'           => true,
                'is_system'           => true,
            ]);
        });
    }

    public function down(): void
    {
        Account::where('code', '2050')->where('is_system', true)->delete();
    }
};

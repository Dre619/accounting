<?php

use App\Models\Account;
use App\Models\Company;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $needed = [
            '2450' => 'NHIMA Payable',
            '2550' => 'Salaries Payable',
        ];

        Company::each(function (Company $company) use ($needed) {
            // Borrow the category_id from an existing current-liability account (e.g. PAYE Payable 2300)
            $categoryId = Account::where('company_id', $company->id)
                ->where('type', 'liability')
                ->whereNotNull('account_category_id')
                ->value('account_category_id');

            foreach ($needed as $code => $name) {
                if (Account::where('company_id', $company->id)->where('code', $code)->exists()) {
                    continue;
                }

                Account::create([
                    'company_id'          => $company->id,
                    'account_category_id' => $categoryId,
                    'code'                => $code,
                    'name'                => $name,
                    'type'                => 'liability',
                    'subtype'             => 'current_liability',
                    'is_system'           => false,
                    'is_active'           => true,
                    'opening_balance'     => 0,
                ]);
            }
        });
    }

    public function down(): void
    {
        Account::whereIn('code', ['2450', '2550'])->delete();
    }
};

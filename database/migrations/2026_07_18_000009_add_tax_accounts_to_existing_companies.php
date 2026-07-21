<?php

use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Company;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // The Taxation category is a global template (company_id = null), matching
        // how CompanyProvisioningService resolves categories.
        $taxation = AccountCategory::firstOrCreate(
            ['name' => 'Taxation', 'company_id' => null],
            ['type' => 'expense', 'sort_order' => 120]
        );

        $accounts = [
            ['1450', 'Provisional Tax Paid',  'asset',     'current_asset',     'Current Assets'],
            ['2150', 'Turnover Tax Payable',  'liability', 'current_liability', 'Current Liabilities'],
            ['2250', 'Income Tax Payable',    'liability', 'current_liability', 'Current Liabilities'],
            ['8000', 'Turnover Tax Expense',  'expense',   'taxation',          null],
            ['8100', 'Income Tax Expense',    'expense',   'taxation',          null],
        ];

        Company::each(function (Company $company) use ($accounts, $taxation) {
            foreach ($accounts as [$code, $name, $type, $subtype, $categoryName]) {
                if (Account::where('company_id', $company->id)->where('code', $code)->exists()) {
                    continue;
                }

                $categoryId = $categoryName === null
                    ? $taxation->id
                    : $this->categoryId($company, $categoryName);

                if (! $categoryId) {
                    continue;
                }

                Account::create([
                    'company_id'          => $company->id,
                    'account_category_id' => $categoryId,
                    'code'                => $code,
                    'name'                => $name,
                    'type'                => $type,
                    'subtype'             => $subtype,
                    'is_active'           => true,
                    'is_system'           => true,
                ]);
            }
        });
    }

    /** Categories may be global templates or company-scoped depending on vintage. */
    private function categoryId(Company $company, string $name): ?int
    {
        return AccountCategory::whereNull('company_id')->where('name', $name)->value('id')
            ?? AccountCategory::where('company_id', $company->id)->where('name', $name)->value('id');
    }

    public function down(): void
    {
        Account::whereIn('code', ['1450', '2150', '2250', '8000', '8100'])
            ->where('is_system', true)->delete();
    }
};

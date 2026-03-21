<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Company;
use App\Models\TaxRate;

class CompanyProvisioningService
{
    /**
     * Set up a new company with the default Zambian chart of accounts and tax rates.
     */
    public function provision(Company $company): void
    {
        // Start 14-day free trial
        $company->update(['trial_ends_at' => now()->addDays(14)]);

        $this->createTaxRates($company);
        $this->createChartOfAccounts($company);
    }

    private function createTaxRates(Company $company): void
    {
        $rates = [
            ['name' => 'Standard VAT (16%)',          'code' => 'VAT16',  'type' => 'vat',         'rate' => 16.00],
            ['name' => 'Zero-Rated VAT (0%)',          'code' => 'VAT0',   'type' => 'vat',         'rate' => 0.00],
            ['name' => 'WHT - Dividends (15%)',        'code' => 'WHT15',  'type' => 'withholding', 'rate' => 15.00],
            ['name' => 'WHT - Services (15%)',         'code' => 'WHT15S', 'type' => 'withholding', 'rate' => 15.00],
            ['name' => 'WHT - Non-Resident (20%)',     'code' => 'WHT20',  'type' => 'withholding', 'rate' => 20.00],
            ['name' => 'WHT - Rent (10%)',             'code' => 'WHT10R', 'type' => 'withholding', 'rate' => 10.00],
        ];

        foreach ($rates as $rate) {
            TaxRate::create(array_merge($rate, ['company_id' => $company->id]));
        }
    }

    private function createChartOfAccounts(Company $company): void
    {
        $categories = AccountCategory::whereNull('company_id')->orderBy('sort_order')->get()
            ->keyBy('name');

        // Helper to create an account
        $make = fn (string $code, string $name, string $type, string $subtype, string $category, array $extra = []) =>
            Account::create(array_merge([
                'company_id'          => $company->id,
                'account_category_id' => $categories[$category]->id,
                'code'                => $code,
                'name'                => $name,
                'type'                => $type,
                'subtype'             => $subtype,
                'is_system'           => true,
            ], $extra));

        // ── Assets ────────────────────────────────────────────────────────────
        $make('1000', 'Cash on Hand',            'asset', 'current_asset', 'Current Assets', ['is_bank_account' => true]);
        $make('1010', 'Petty Cash',              'asset', 'current_asset', 'Current Assets', ['is_bank_account' => true]);
        $make('1100', 'Bank Account',            'asset', 'current_asset', 'Current Assets', ['is_bank_account' => true]);
        $make('1200', 'Accounts Receivable',     'asset', 'current_asset', 'Current Assets');
        $make('1300', 'Inventory',               'asset', 'current_asset', 'Current Assets');
        $make('1400', 'Prepaid Expenses',        'asset', 'current_asset', 'Current Assets');
        $make('1500', 'VAT Receivable (Input)',  'asset', 'current_asset', 'Current Assets');
        $make('1600', 'WHT Receivable',          'asset', 'current_asset', 'Current Assets');
        $make('1900', 'Property & Equipment',    'asset', 'fixed_asset',   'Fixed Assets');
        $make('1910', 'Accumulated Depreciation','asset', 'fixed_asset',   'Fixed Assets');

        // ── Liabilities ───────────────────────────────────────────────────────
        $make('2000', 'Accounts Payable',        'liability', 'current_liability', 'Current Liabilities');
        $make('2100', 'VAT Payable (Output)',     'liability', 'current_liability', 'Current Liabilities');
        $make('2200', 'WHT Payable',             'liability', 'current_liability', 'Current Liabilities');
        $make('2300', 'PAYE Payable',            'liability', 'current_liability', 'Current Liabilities');
        $make('2400', 'NAPSA Payable',           'liability', 'current_liability', 'Current Liabilities');
        $make('2450', 'NHIMA Payable',           'liability', 'current_liability', 'Current Liabilities');
        $make('2500', 'Accrued Liabilities',     'liability', 'current_liability', 'Current Liabilities');
        $make('2550', 'Salaries Payable',        'liability', 'current_liability', 'Current Liabilities');
        $make('2600', 'Short-Term Loans',        'liability', 'current_liability', 'Current Liabilities');
        $make('2900', 'Long-Term Loans',         'liability', 'long_term_liability', 'Long-Term Liabilities');

        // ── Equity ────────────────────────────────────────────────────────────
        $make('3000', 'Share Capital',           'equity', 'equity', 'Equity');
        $make('3100', 'Retained Earnings',       'equity', 'equity', 'Equity');
        $make('3200', 'Current Year Earnings',   'equity', 'equity', 'Equity');

        // ── Income ────────────────────────────────────────────────────────────
        $make('4000', 'Sales Revenue',           'income', 'operating_income', 'Operating Income');
        $make('4100', 'Service Revenue',         'income', 'operating_income', 'Operating Income');
        $make('4900', 'Other Income',            'income', 'other_income',     'Other Income');
        $make('4910', 'Interest Income',         'income', 'other_income',     'Other Income');

        // ── Cost of Sales ──────────────────────────────────────────────────────
        $make('5000', 'Cost of Goods Sold',      'expense', 'cost_of_goods_sold', 'Cost of Sales');
        $make('5100', 'Direct Labour',           'expense', 'cost_of_goods_sold', 'Cost of Sales');

        // ── Operating Expenses ────────────────────────────────────────────────
        $make('6000', 'Salaries & Wages',        'expense', 'operating_expense', 'Operating Expenses');
        $make('6010', 'NAPSA Contribution',      'expense', 'operating_expense', 'Operating Expenses');
        $make('6100', 'Rent & Rates',            'expense', 'operating_expense', 'Operating Expenses');
        $make('6200', 'Utilities',               'expense', 'operating_expense', 'Operating Expenses');
        $make('6300', 'Office Supplies',         'expense', 'operating_expense', 'Operating Expenses');
        $make('6400', 'Travel & Transport',      'expense', 'operating_expense', 'Operating Expenses');
        $make('6500', 'Telephone & Internet',    'expense', 'operating_expense', 'Operating Expenses');
        $make('6600', 'Marketing & Advertising', 'expense', 'operating_expense', 'Operating Expenses');
        $make('6700', 'Bank Charges',            'expense', 'operating_expense', 'Operating Expenses');
        $make('6800', 'Depreciation',            'expense', 'operating_expense', 'Operating Expenses');
        $make('6900', 'Professional Fees',       'expense', 'operating_expense', 'Operating Expenses');
        $make('6950', 'Insurance',               'expense', 'operating_expense', 'Operating Expenses');

        // ── Other Expenses ────────────────────────────────────────────────────
        $make('7000', 'Interest Expense',        'expense', 'other_expense', 'Other Expenses');
        $make('7100', 'Tax Expense',             'expense', 'other_expense', 'Other Expenses');
    }
}

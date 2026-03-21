<?php

namespace Database\Seeders;

use App\Models\AccountCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds default account categories used as templates for new companies.
 * These are system-level (company_id = null) and copied on company creation.
 */
class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Assets
            ['name' => 'Current Assets',     'type' => 'asset',     'sort_order' => 10],
            ['name' => 'Fixed Assets',        'type' => 'asset',     'sort_order' => 20],
            ['name' => 'Other Assets',        'type' => 'asset',     'sort_order' => 30],
            // Liabilities
            ['name' => 'Current Liabilities', 'type' => 'liability', 'sort_order' => 40],
            ['name' => 'Long-Term Liabilities','type' => 'liability', 'sort_order' => 50],
            // Equity
            ['name' => 'Equity',              'type' => 'equity',    'sort_order' => 60],
            // Income
            ['name' => 'Operating Income',    'type' => 'income',    'sort_order' => 70],
            ['name' => 'Other Income',        'type' => 'income',    'sort_order' => 80],
            // Expenses
            ['name' => 'Cost of Sales',       'type' => 'expense',   'sort_order' => 90],
            ['name' => 'Operating Expenses',  'type' => 'expense',   'sort_order' => 100],
            ['name' => 'Other Expenses',      'type' => 'expense',   'sort_order' => 110],
        ];

        foreach ($categories as $category) {
            AccountCategory::firstOrCreate(
                ['name' => $category['name'], 'company_id' => null],
                $category
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name'          => 'Starter',
                'slug'          => 'starter',
                'description'   => 'Perfect for sole traders and micro businesses.',
                'price_monthly' => 199.00,
                'price_annual'  => 1_990.00,
                'max_users'     => 1,
                'sort_order'    => 1,
                'features'      => [
                    'Invoicing & receipts',
                    'Contact management',
                    'Payment tracking',
                    'Profit & Loss report',
                    'VAT auto-calculation (16%)',
                    '1 user',
                ],
            ],
            [
                'name'          => 'Growth',
                'slug'          => 'growth',
                'description'   => 'For growing SMEs that need more power.',
                'price_monthly' => 399.00,
                'price_annual'  => 3_990.00,
                'max_users'     => 3,
                'sort_order'    => 2,
                'features'      => [
                    'Everything in Starter',
                    'Bills & supplier management',
                    'Recurring invoices',
                    'Balance Sheet report',
                    'Aged Receivables & Payables',
                    'VAT Summary report',
                    'Up to 3 users',
                ],
            ],
            [
                'name'          => 'Business',
                'slug'          => 'business',
                'description'   => 'Full-featured accounting for established businesses.',
                'price_monthly' => 799.00,
                'price_annual'  => 7_990.00,
                'max_users'     => 10,
                'sort_order'    => 3,
                'features'      => [
                    'Everything in Growth',
                    'Manual journal entries',
                    'Employee & payroll management',
                    'ZRA Smart Invoice (VSDC)',
                    'Up to 10 users',
                    'Priority support',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}

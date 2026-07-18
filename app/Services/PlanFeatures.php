<?php

namespace App\Services;

class PlanFeatures
{
    /**
     * Canonical feature keys available per plan slug.
     * Trial users always receive the 'business' feature set.
     */
    const MAP = [
        'starter' => [
            'invoices', 'contacts', 'payments', 'accounts', 'reports_pl',
        ],
        'growth' => [
            'invoices', 'contacts', 'payments', 'accounts', 'reports_pl',
            'bills', 'recurring', 'reports_advanced', 'inventory',
        ],
        'business' => [
            'invoices', 'contacts', 'payments', 'accounts', 'reports_pl',
            'bills', 'recurring', 'reports_advanced', 'inventory',
            'journals', 'payroll', 'zra_vsdc', 'crm',
        ],
    ];

    public static function forSlug(string $slug): array
    {
        return self::MAP[$slug] ?? [];
    }

    public static function has(string $slug, string $feature): bool
    {
        return in_array($feature, self::forSlug($slug));
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $revenue = SubscriptionPayment::where('status', 'completed')
            ->selectRaw('SUM(amount) as total, COUNT(*) as count')
            ->first();

        // Grouped in PHP: DATE_FORMAT() is MySQL-only and breaks portability.
        $revenueByMonth = SubscriptionPayment::where('status', 'completed')
            ->where('created_at', '>=', now()->subMonths(6))
            ->get(['created_at', 'amount'])
            ->groupBy(fn ($row) => $row->created_at->format('Y-m'))
            ->map(fn ($rows, $month) => (object) ['month' => $month, 'total' => round($rows->sum('amount'), 2)])
            ->sortBy('month')
            ->values();

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'total_companies'      => Company::count(),
                'total_users'          => User::where('is_admin', false)->count(),
                'active_subscriptions' => Subscription::whereIn('status', ['active', 'trialing'])
                    ->where('ends_at', '>', now())->count(),
                'pending_payments'     => SubscriptionPayment::where('status', 'pending')->count(),
                'total_revenue'        => $revenue->total ?? 0,
                'completed_payments'   => $revenue->count ?? 0,
            ],
            'revenueByMonth'  => $revenueByMonth,
            'recentPayments'  => SubscriptionPayment::with(['company', 'plan'])
                ->latest()->limit(8)->get(),
        ]);
    }
}

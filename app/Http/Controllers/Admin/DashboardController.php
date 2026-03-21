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

        $revenueByMonth = SubscriptionPayment::where('status', 'completed')
            ->where('created_at', '>=', now()->subMonths(6))
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(amount) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

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

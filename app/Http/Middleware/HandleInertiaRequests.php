<?php

namespace App\Http\Middleware;

use App\Models\SubscriptionPayment;
use App\Services\PlanFeatures;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user    = $request->user();
        $company = $user?->currentCompany;

        $subscription = $company?->subscriptions()
            ->with('plan:id,name,slug')
            ->whereIn('status', ['active', 'trialing'])
            ->where('ends_at', '>', now())
            ->latest()
            ->first(['id', 'plan_id', 'status', 'billing_cycle', 'ends_at']);

        return [
            ...parent::share($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info'    => fn () => $request->session()->get('info'),
            ],
            'name' => config('app.name'),
            'auth' => [
                'user'         => $user,
                'company'      => $company,
                'subscription' => $subscription,
                'onTrial'      => $company?->isOnTrial() ?? false,
                'trialEndsAt'  => $company?->trial_ends_at,
            ],
            'sidebarOpen'   => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'pendingCount'  => $user?->is_admin
                ? SubscriptionPayment::where('status', 'pending')->count()
                : 0,
            'planFeatures'  => $company?->isOnTrial()
                ? PlanFeatures::forSlug('business')
                : PlanFeatures::forSlug($company?->activeSubscription?->plan?->slug ?? ''),
        ];
    }
}

<?php

namespace App\Http\Middleware;

use App\Services\PlanFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $company = $request->user()?->currentCompany;

        // Trial → full business-level access
        if ($company?->isOnTrial()) {
            return $next($request);
        }

        $plan = $company?->activeSubscription?->plan;

        if ($plan && PlanFeatures::has($plan->slug, $feature)) {
            return $next($request);
        }

        $planName = $plan?->name ?? 'a paid plan';

        return redirect()->route('billing.plans')
            ->with('warning', "This feature is not available on your current plan ({$planName}). Please upgrade to continue.");
    }
}

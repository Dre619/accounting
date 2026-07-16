<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->user()?->currentCompany;

        if (! $company) {
            return redirect()->route('company.create');
        }

        // Allow if still within trial period
        if ($company->trial_ends_at && now()->lt($company->trial_ends_at)) {
            return $next($request);
        }

        // Allow if there is an active subscription
        $active = $company->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->where('ends_at', '>', now())
            ->exists();

        if ($active) {
            return $next($request);
        }

        $lapsed = $company->subscriptions()->exists();

        return redirect()->route('billing.plans')->with(
            'warning',
            $lapsed
                ? 'Your subscription has ended. Renew it to continue.'
                : 'Your trial has ended. Please subscribe to continue.'
        );
    }
}

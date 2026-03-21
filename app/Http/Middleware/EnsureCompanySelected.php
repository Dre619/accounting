<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanySelected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->is_admin) {
            return $next($request);
        }

        if (! $user?->hasCompany()) {
            return redirect()->route('company.create');
        }

        return $next($request);
    }
}

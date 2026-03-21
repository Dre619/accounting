<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\CompanyProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyProvisioningService $provisioning
    ) {}

    /**
     * Show the company setup wizard (only if user has no company yet).
     */
    public function create(): Response
    {
        return Inertia::render('company/Setup');
    }

    /**
     * Persist the new company and provision default COA + tax rates.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:150'],
            'tpin'                => ['nullable', 'string', 'max:10'],
            'vat_number'          => ['nullable', 'string', 'max:20'],
            'email'               => ['nullable', 'email', 'max:150'],
            'phone'               => ['nullable', 'string', 'max:20'],
            'address'             => ['nullable', 'string', 'max:255'],
            'city'                => ['nullable', 'string', 'max:100'],
            'financial_year_end'  => ['required', 'string', 'regex:/^\d{2}-\d{2}$/'],
            'invoice_prefix'      => ['required', 'string', 'max:10'],
        ]);

        $company = Company::create([
            ...$validated,
            'user_id'  => $request->user()->id,
            'currency' => 'ZMW',
            'country'  => 'Zambia',
        ]);

        $this->provisioning->provision($company);

        $request->user()->update(['current_company_id' => $company->id]);

        return redirect()->route('dashboard')
            ->with('success', 'Company created successfully. Welcome to CloudOne Accounting!');
    }

    /**
     * Switch the active company (for users with multiple companies).
     */
    public function switch(Request $request, Company $company): RedirectResponse
    {
        abort_unless($company->user_id === $request->user()->id, 403);

        $request->user()->update(['current_company_id' => $company->id]);

        return back()->with('success', "Switched to {$company->name}.");
    }
}

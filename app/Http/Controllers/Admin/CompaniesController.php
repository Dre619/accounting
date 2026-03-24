<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\ZraVsdcService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CompaniesController extends Controller
{
    public function __construct(private readonly ZraVsdcService $vsdc) {}

    public function index(): Response
    {
        $companies = Company::with(['owner', 'activeSubscription.plan'])
            ->withCount(['invoices', 'contacts'])
            ->latest()
            ->paginate(25);

        return Inertia::render('admin/companies/Index', [
            'companies' => $companies,
        ]);
    }

    public function vsdc(Company $company): Response
    {
        return Inertia::render('admin/companies/Vsdc', [
            'company' => $company->only([
                'id', 'name', 'tpin',
                'vsdc_url', 'vsdc_bhf_id', 'vsdc_dvc_srl_no',
                'vsdc_initialized', 'vsdc_sdc_id', 'vsdc_mrc_no',
                'vsdc_status', 'vsdc_last_seen_at',
            ]),
        ]);
    }

    public function updateVsdc(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'vsdc_url'       => ['required', 'string', 'url', 'max:255'],
            'vsdc_bhf_id'    => ['required', 'string', 'max:3'],
            'vsdc_dvc_srl_no'=> ['nullable', 'string', 'max:100'],
        ]);

        // Changing credentials resets initialization so the device must be re-initialized
        $resetInit = $company->vsdc_url !== $data['vsdc_url']
            || $company->vsdc_bhf_id !== $data['vsdc_bhf_id'];

        $company->update(array_merge($data, $resetInit ? [
            'vsdc_initialized' => false,
            'vsdc_sdc_id'      => null,
            'vsdc_mrc_no'      => null,
            'vsdc_status'      => null,
            'vsdc_last_seen_at'=> null,
        ] : []));

        return back()->with('success', 'VSDC credentials saved.' . ($resetInit ? ' Device must be re-initialized.' : ''));
    }

    public function initializeVsdc(Company $company): RedirectResponse
    {
        try {
            $this->vsdc->initialize($company);
        } catch (RuntimeException $e) {
            return back()->withErrors(['vsdc' => $e->getMessage()]);
        }

        return back()->with('success', 'VSDC device initialized successfully.');
    }
}

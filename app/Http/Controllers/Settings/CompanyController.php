<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function edit(Request $request): Response
    {
        $company = $request->user()->currentCompany;
        abort_if(is_null($company), 404, 'No company found.');

        return Inertia::render('settings/Company', [
            'company' => $company->only(
                'name', 'tpin', 'vat_number', 'email', 'phone',
                'address', 'city', 'country', 'financial_year_end',
                'invoice_prefix', 'logo_path',
                'vsdc_url', 'vsdc_bhf_id', 'vsdc_dvc_srl_no',
                'vsdc_initialized', 'vsdc_sdc_id', 'vsdc_mrc_no'
            ),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $request->user()->currentCompany;
        abort_if(is_null($company), 404);

        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:150'],
            'tpin'               => ['nullable', 'string', 'max:10'],
            'vat_number'         => ['nullable', 'string', 'max:20'],
            'email'              => ['nullable', 'email', 'max:150'],
            'phone'              => ['nullable', 'string', 'max:20'],
            'address'            => ['nullable', 'string', 'max:255'],
            'city'               => ['nullable', 'string', 'max:100'],
            'financial_year_end' => ['required', 'string', 'regex:/^\d{2}-\d{2}$/'],
            'invoice_prefix'     => ['required', 'string', 'max:10'],
            'logo'               => ['nullable', 'image', 'max:2048'],
            'vsdc_url'           => ['nullable', 'url', 'max:255'],
            'vsdc_bhf_id'        => ['nullable', 'string', 'max:3'],
            'vsdc_dvc_srl_no'    => ['nullable', 'string', 'max:100'],
        ]);

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $validated['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        unset($validated['logo']);
        $company->update($validated);

        return back()->with('success', 'Company settings saved.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Inertia\Inertia;
use Inertia\Response;

class CompaniesController extends Controller
{
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
}

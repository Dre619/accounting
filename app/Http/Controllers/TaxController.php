<?php

namespace App\Http\Controllers;

use App\Models\TaxRate;
use App\Services\TurnoverTaxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class TaxController extends Controller
{
    public function __construct(private readonly TurnoverTaxService $tot) {}

    public function turnoverTax(Request $request): Response
    {
        $company = $request->user()->currentCompany;
        [$from, $to] = $this->period($request);

        return Inertia::render('tax/TurnoverTax', [
            'result'    => $this->tot->compute($company, $from, $to),
            'taxRegime' => $company->tax_regime,
            'rates'     => $company->taxRates()->turnover()->orderByRaw('effective_from is null desc, effective_from asc')
                ->get()->map(fn (TaxRate $r) => [
                    'id'     => $r->id,
                    'name'   => $r->name,
                    'code'   => $r->code,
                    'rate'   => (float) $r->rate,
                    'period' => $r->periodLabel(),
                    'active' => $r->is_active,
                ]),
            'company'   => $company->only('name', 'tpin'),
        ]);
    }

    public function storeRate(Request $request): RedirectResponse
    {
        $company = $request->user()->currentCompany;

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'code'           => ['required', 'string', 'max:20'],
            'rate'           => ['required', 'numeric', 'min:0', 'max:100'],
            'effective_from' => ['nullable', 'date'],
            'effective_to'   => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $company->taxRates()->create($data + ['type' => 'turnover', 'is_active' => true]);

        return back()->with('success', 'Turnover tax rate added.');
    }

    public function destroyRate(Request $request, TaxRate $taxRate): RedirectResponse
    {
        abort_unless($taxRate->company_id === $request->user()->currentCompany->id, 403);
        abort_unless($taxRate->type === 'turnover', 422, 'Only turnover tax rates can be removed here.');

        $taxRate->delete();

        return back()->with('success', 'Rate removed.');
    }

    public function postTurnoverTax(Request $request): RedirectResponse
    {
        $company = $request->user()->currentCompany;
        [$from, $to] = $this->period($request);

        $this->tot->post($company, $from, $to);

        return back()->with('success', 'Turnover tax posted to the ledger.');
    }

    /** Defaults to the current calendar month — TOT is filed monthly. */
    private function period(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->toDateString()
            : now()->startOfMonth()->toDateString();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->toDateString()
            : now()->endOfMonth()->toDateString();

        return [$from, $to];
    }
}

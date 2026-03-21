<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\ZraVsdcService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ZraVsdcController extends Controller
{
    public function __construct(private readonly ZraVsdcService $vsdc) {}

    /**
     * Initialize the VSDC device for the current company.
     */
    public function initialize(Request $request): RedirectResponse
    {
        $company = $request->user()->currentCompany;

        try {
            $this->vsdc->initialize($company);
        } catch (RuntimeException $e) {
            return back()->withErrors(['vsdc' => $e->getMessage()]);
        }

        return back()->with('success', 'VSDC device initialised successfully.');
    }

    /**
     * Submit an invoice to ZRA via the VSDC.
     */
    public function submit(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->company_id === $request->user()->currentCompany->id, 403);
        abort_unless(in_array($invoice->status, ['sent', 'partial', 'paid']), 422, 'Only sent/partial/paid invoices can be submitted to ZRA.');

        $invoice->load(['contact', 'items.taxRate', 'company']);

        try {
            $this->vsdc->submitSale($invoice);
        } catch (RuntimeException $e) {
            return back()->withErrors(['vsdc' => $e->getMessage()]);
        }

        return back()->with('success', "Invoice {$invoice->invoice_number} submitted to ZRA successfully.");
    }
}

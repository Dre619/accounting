<?php

namespace App\Http\Controllers;

use App\Jobs\SubmitZraSaleJob;
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
     * Submit an invoice to ZRA via the VSDC (queued).
     */
    public function submit(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->company_id === $request->user()->currentCompany->id, 403);
        abort_unless(in_array($invoice->status, ['sent', 'partial', 'paid']), 422, 'Only sent/partial/paid invoices can be submitted to ZRA.');
        abort_if((bool) $invoice->zra_submitted_at, 422, 'This invoice has already been submitted to ZRA.');

        SubmitZraSaleJob::dispatch($invoice);

        return back()->with('success', "Invoice {$invoice->invoice_number} queued for ZRA submission.");
    }
}

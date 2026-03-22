<?php

namespace App\Http\Controllers;

use App\Mail\InvoiceEmail;
use App\Models\GoodsCode;
use App\Models\Invoice;
use App\Models\ServiceCode;
use App\Models\TaxRate;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $service) {}

    public function index(Request $request): Response
    {
        $company = $request->user()->currentCompany;
        $status  = $request->query('status', 'all');
        $search  = $request->query('search', '');

        $invoices = $company->invoices()
            ->with('contact:id,name')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) =>
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('contact', fn ($c) => $c->where('name', 'like', "%{$search}%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = $company->invoices()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return Inertia::render('invoices/Index', [
            'invoices'      => $invoices,
            'currentStatus' => $status,
            'search'        => $search,
            'counts'        => $counts,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('invoices/Form', $this->formData($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $data    = $this->validated($request);
        $company = $request->user()->currentCompany;
        $invoice = $this->service->store($company, $data);

        if ($request->hasFile('zra_invoice')) {
            $invoice->update([
                'zra_invoice_path' => $request->file('zra_invoice')
                    ->store("zra-invoices/{$company->id}", 'public'),
            ]);
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', "Invoice {$invoice->invoice_number} created.");
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        $this->authorise($request, $invoice);

        $invoice->load([
            'contact',
            'items.taxRate',
            'items.account:id,name,code',
            'createdBy:id,name',
        ]);

        return Inertia::render('invoices/Show', [
            'invoice' => $invoice,
            'company' => $request->user()->currentCompany->only('name', 'address', 'city', 'tpin', 'vat_number', 'email', 'phone'),
        ]);
    }

    public function edit(Request $request, Invoice $invoice): Response
    {
        $this->authorise($request, $invoice);
        abort_unless($invoice->status === 'draft', 422, 'Only draft invoices can be edited.');

        $invoice->load(['items.taxRate', 'items.account']);

        return Inertia::render('invoices/Form', array_merge(
            $this->formData($request),
            ['invoice' => $invoice]
        ));
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorise($request, $invoice);
        $data    = $this->validated($request);
        $invoice = $this->service->update($invoice, $data);

        if ($request->hasFile('zra_invoice')) {
            if ($invoice->zra_invoice_path) {
                Storage::disk('public')->delete($invoice->zra_invoice_path);
            }
            $invoice->update([
                'zra_invoice_path' => $request->file('zra_invoice')
                    ->store("zra-invoices/{$invoice->company_id}", 'public'),
            ]);
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Invoice updated.');
    }

    public function send(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorise($request, $invoice);
        $this->service->send($invoice);

        return back()->with('success', "Invoice {$invoice->invoice_number} marked as sent.");
    }

    public function void(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorise($request, $invoice);
        $this->service->void($invoice);

        return back()->with('success', "Invoice {$invoice->invoice_number} has been voided.");
    }

    public function email(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorise($request, $invoice);
        abort_unless($invoice->contact->email, 422, 'This contact has no email address.');

        $invoice->load(['contact', 'items.taxRate', 'items.account:id,name,code']);
        $company = $request->user()->currentCompany;

        Mail::to($invoice->contact->email)
            ->send(new InvoiceEmail($invoice, $company));

        return back()->with('success', "Invoice {$invoice->invoice_number} emailed to {$invoice->contact->email}.");
    }

    public function print(Request $request, Invoice $invoice): \Illuminate\Contracts\View\View
    {
        $this->authorise($request, $invoice);

        $invoice->load(['contact', 'items.taxRate', 'items.account:id,name,code']);
        $company = $request->user()->currentCompany;

        return view('invoices.print', compact('invoice', 'company'));
    }

    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorise($request, $invoice);
        abort_unless($invoice->status === 'draft', 422, 'Only draft invoices can be deleted.');

        $invoice->delete();

        return redirect()->route('invoices.index')
            ->with('success', 'Invoice deleted.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function formData(Request $request): array
    {
        $company = $request->user()->currentCompany;

        return [
            'invoice'      => null,
            'contacts'     => $company->contacts()->customers()->active()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'tpin']),
            'accounts'     => $company->accounts()->active()->ofType('income')
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'taxRates'     => $company->taxRates()->active()->vat()
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'rate']),
            'vsdcEnabled'  => (bool) $company->vsdc_initialized,
            'goodsCodes'   => GoodsCode::orderBy('name')->get(['id', 'name', 'hs_code']),
            'serviceCodes' => ServiceCode::orderBy('name')->get(['id', 'name', 'hs_code']),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'contact_id'      => ['required', 'integer'],
            'issue_date'      => ['required', 'date'],
            'due_date'        => ['required', 'date', 'after_or_equal:issue_date'],
            'reference'       => ['nullable', 'string', 'max:100'],
            'notes'           => ['nullable', 'string', 'max:2000'],
            'footer'          => ['nullable', 'string', 'max:500'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items'           => ['required', 'array', 'min:1'],
            'items.*.id'               => ['nullable', 'integer'],
            'items.*.description'      => ['required', 'string', 'max:255'],
            'items.*.account_id'       => ['nullable', 'integer'],
            'items.*.tax_rate_id'      => ['nullable', 'integer'],
            'items.*.quantity'         => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price'       => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.item_type'        => ['nullable', 'in:goods,service'],
            'items.*.cls_code_id'      => ['nullable', 'integer'],
            'zra_invoice'              => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
    }

    private function authorise(Request $request, Invoice $invoice): void
    {
        abort_unless($invoice->company_id === $request->user()->currentCompany->id, 403);
    }
}

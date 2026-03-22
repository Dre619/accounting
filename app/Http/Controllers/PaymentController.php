<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $service) {}

    public function index(Request $request): Response
    {
        $company = $request->user()->currentCompany;
        $type    = $request->query('type', 'all');

        $payments = $company->payments()
            ->with('contact:id,name', 'depositAccount:id,name,code')
            ->when($type !== 'all', fn ($q) => $q->where('type', $type))
            ->latest('payment_date')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('payments/Index', [
            'payments'    => $payments,
            'currentType' => $type,
            'counts'      => [
                'all'     => $company->payments()->count(),
                'receipt' => $company->payments()->where('type', 'receipt')->count(),
                'payment' => $company->payments()->where('type', 'payment')->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $company = $request->user()->currentCompany;

        return Inertia::render('payments/Create', [
            'contacts'     => $company->contacts()->active()->orderBy('name')
                ->get(['id', 'name', 'type']),
            'bankAccounts' => $company->accounts()->bankAccounts()->active()
                ->orderBy('code')->get(['id', 'code', 'name']),
            'defaultType'  => $request->query('type', 'receipt'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type'                   => ['required', 'in:receipt,payment'],
            'contact_id'             => ['nullable', 'integer'],
            'payment_date'           => ['required', 'date'],
            'amount'                 => ['required', 'numeric', 'min:0.01'],
            'withholding_tax_amount' => ['nullable', 'numeric', 'min:0'],
            'method'                 => ['required', 'in:cash,bank_transfer,cheque,airtel_money,mtn_money,zamtel_money,other'],
            'reference'              => ['nullable', 'string', 'max:100'],
            'deposit_account_id'     => ['required', 'integer'],
            'notes'                  => ['nullable', 'string', 'max:1000'],
            'allocations'            => ['nullable', 'array'],
            'allocations.*.type'     => ['required', 'in:invoice,bill'],
            'allocations.*.id'       => ['required', 'integer'],
            'allocations.*.amount'   => ['required', 'numeric', 'min:0.01'],
        ]);

        $payment = $this->service->record($request->user()->currentCompany, $validated);

        return redirect()->route('payments.show', $payment)
            ->with('success', ucfirst($payment->type) . " {$payment->payment_number} recorded.");
    }

    public function show(Request $request, Payment $payment): Response
    {
        $this->authorise($request, $payment);

        $payment->load([
            'contact',
            'depositAccount:id,name,code',
            'allocations.allocatable',
            'createdBy:id,name',
        ]);

        // Load open documents so the Allocate panel has data ready
        $openDocs = collect();
        if ($payment->contact_id && $payment->unallocated_amount > 0) {
            if ($payment->type === 'receipt') {
                $openDocs = Invoice::where('company_id', $payment->company_id)
                    ->where('contact_id', $payment->contact_id)
                    ->whereIn('status', ['sent', 'partial', 'overdue'])
                    ->get(['id', 'invoice_number as number', 'total', 'amount_due', 'due_date'])
                    ->map(fn ($d) => array_merge($d->toArray(), ['doc_type' => 'invoice']));
            } else {
                $openDocs = Bill::where('company_id', $payment->company_id)
                    ->where('contact_id', $payment->contact_id)
                    ->whereIn('status', ['approved', 'partial', 'overdue'])
                    ->get(['id', 'bill_number as number', 'total', 'amount_due', 'due_date'])
                    ->map(fn ($d) => array_merge($d->toArray(), ['doc_type' => 'bill']));
            }
        }

        return Inertia::render('payments/Show', [
            'payment'           => $payment,
            'openDocs'          => $openDocs,
            'unallocatedAmount' => $payment->unallocated_amount,
        ]);
    }

    /**
     * Allocate an existing payment to invoices / bills after the fact.
     */
    public function allocate(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorise($request, $payment);

        $validated = $request->validate([
            'allocations'          => ['required', 'array', 'min:1'],
            'allocations.*.type'   => ['required', 'in:invoice,bill'],
            'allocations.*.id'     => ['required', 'integer'],
            'allocations.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $this->service->allocate($payment, $validated['allocations']);

        return back()->with('success', 'Allocations saved.');
    }

    public function destroy(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorise($request, $payment);
        $this->service->destroy($payment);

        return redirect()->route('payments.index')
            ->with('success', 'Payment deleted and journal entries reversed.');
    }

    /**
     * JSON endpoint: return open invoices or bills for a given contact.
     */
    public function openDocuments(Request $request): JsonResponse
    {
        $company   = $request->user()->currentCompany;
        $contactId = $request->integer('contact_id');
        $type      = $request->query('type', 'receipt');

        if ($type === 'receipt') {
            $docs = Invoice::where('company_id', $company->id)
                ->where('contact_id', $contactId)
                ->whereIn('status', ['sent', 'partial', 'overdue'])
                ->get(['id', 'invoice_number as number', 'total', 'amount_due', 'due_date'])
                ->map(fn ($d) => array_merge($d->toArray(), ['doc_type' => 'invoice']));
        } else {
            $docs = Bill::where('company_id', $company->id)
                ->where('contact_id', $contactId)
                ->whereIn('status', ['approved', 'partial', 'overdue'])
                ->get(['id', 'bill_number as number', 'total', 'amount_due', 'due_date'])
                ->map(fn ($d) => array_merge($d->toArray(), ['doc_type' => 'bill']));
        }

        return response()->json($docs);
    }

    private function authorise(Request $request, Payment $payment): void
    {
        abort_unless($payment->company_id === $request->user()->currentCompany->id, 403);
    }
}

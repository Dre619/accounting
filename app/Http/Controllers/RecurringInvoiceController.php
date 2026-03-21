<?php

namespace App\Http\Controllers;

use App\Models\RecurringInvoice;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecurringInvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $service) {}

    public function index(Request $request): Response
    {
        $company = $request->user()->currentCompany;

        $recurring = $company->recurringInvoices()
            ->with('contact:id,name')
            ->latest()
            ->get();

        return Inertia::render('recurring/Index', [
            'recurring' => $recurring,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('recurring/Form', $this->formData($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $data    = $this->validated($request);
        $company = $request->user()->currentCompany;

        $recurring = $company->recurringInvoices()->create([
            'contact_id'      => $data['contact_id'],
            'frequency'       => $data['frequency'],
            'day_of_month'    => $data['day_of_month'],
            'days_due'        => $data['days_due'],
            'reference'       => $data['reference'] ?? null,
            'notes'           => $data['notes'] ?? null,
            'discount_amount' => $data['discount_amount'] ?? 0,
            'is_active'       => true,
            'next_run_at'     => $data['next_run_at'],
            'created_by'      => $request->user()->id,
        ]);

        foreach ($data['items'] as $i => $item) {
            $recurring->items()->create([
                'description'      => $item['description'],
                'account_id'       => $item['account_id'] ?? null,
                'tax_rate_id'      => $item['tax_rate_id'] ?? null,
                'quantity'         => $item['quantity'],
                'unit_price'       => $item['unit_price'],
                'discount_percent' => $item['discount_percent'] ?? 0,
                'sort_order'       => $i,
            ]);
        }

        return redirect()->route('recurring.index')
            ->with('success', 'Recurring invoice created.');
    }

    public function show(Request $request, RecurringInvoice $recurring): Response
    {
        $this->authorise($request, $recurring);

        $recurring->load(['contact', 'items.taxRate', 'items.account:id,name,code', 'createdBy:id,name']);

        return Inertia::render('recurring/Show', [
            'recurring' => $recurring,
        ]);
    }

    public function edit(Request $request, RecurringInvoice $recurring): Response
    {
        $this->authorise($request, $recurring);
        $recurring->load(['items.taxRate', 'items.account']);

        return Inertia::render('recurring/Form', array_merge(
            $this->formData($request),
            ['recurring' => $recurring]
        ));
    }

    public function update(Request $request, RecurringInvoice $recurring): RedirectResponse
    {
        $this->authorise($request, $recurring);
        $data = $this->validated($request);

        $recurring->update([
            'contact_id'      => $data['contact_id'],
            'frequency'       => $data['frequency'],
            'day_of_month'    => $data['day_of_month'],
            'days_due'        => $data['days_due'],
            'reference'       => $data['reference'] ?? null,
            'notes'           => $data['notes'] ?? null,
            'discount_amount' => $data['discount_amount'] ?? 0,
            'next_run_at'     => $data['next_run_at'],
        ]);

        $recurring->items()->delete();
        foreach ($data['items'] as $i => $item) {
            $recurring->items()->create([
                'description'      => $item['description'],
                'account_id'       => $item['account_id'] ?? null,
                'tax_rate_id'      => $item['tax_rate_id'] ?? null,
                'quantity'         => $item['quantity'],
                'unit_price'       => $item['unit_price'],
                'discount_percent' => $item['discount_percent'] ?? 0,
                'sort_order'       => $i,
            ]);
        }

        return redirect()->route('recurring.index')
            ->with('success', 'Recurring invoice updated.');
    }

    public function destroy(Request $request, RecurringInvoice $recurring): RedirectResponse
    {
        $this->authorise($request, $recurring);
        $recurring->delete();

        return redirect()->route('recurring.index')
            ->with('success', 'Recurring invoice deleted.');
    }

    /**
     * Manually trigger generation of an invoice from this recurring template.
     */
    public function run(Request $request, RecurringInvoice $recurring): RedirectResponse
    {
        $this->authorise($request, $recurring);
        $recurring->load('items');

        $company  = $request->user()->currentCompany;
        $today    = Carbon::today();
        $dueDate  = $today->copy()->addDays($recurring->days_due);

        $invoice = $this->service->store($company, [
            'contact_id'      => $recurring->contact_id,
            'issue_date'      => $today->toDateString(),
            'due_date'        => $dueDate->toDateString(),
            'reference'       => $recurring->reference,
            'notes'           => $recurring->notes,
            'discount_amount' => $recurring->discount_amount,
            'items'           => $recurring->items->map(fn ($item) => [
                'description'      => $item->description,
                'account_id'       => $item->account_id,
                'tax_rate_id'      => $item->tax_rate_id,
                'quantity'         => $item->quantity,
                'unit_price'       => $item->unit_price,
                'discount_percent' => $item->discount_percent,
            ])->toArray(),
        ]);

        // Advance next_run_at
        $recurring->update([
            'last_run_at' => $today->toDateString(),
            'next_run_at' => $this->nextRunDate($recurring->frequency, $recurring->day_of_month),
        ]);

        return redirect()->route('invoices.show', $invoice)
            ->with('success', "Invoice {$invoice->invoice_number} generated.");
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function formData(Request $request): array
    {
        $company = $request->user()->currentCompany;

        return [
            'recurring' => null,
            'contacts'  => $company->contacts()->customers()->active()->orderBy('name')->get(['id', 'name']),
            'accounts'  => $company->accounts()->active()->ofType('income')->orderBy('code')->get(['id', 'code', 'name']),
            'taxRates'  => $company->taxRates()->active()->vat()->orderBy('name')->get(['id', 'name', 'code', 'rate']),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'contact_id'      => ['required', 'integer'],
            'frequency'       => ['required', 'in:weekly,monthly,quarterly,yearly'],
            'day_of_month'    => ['required', 'integer', 'min:1', 'max:31'],
            'days_due'        => ['required', 'integer', 'min:0', 'max:365'],
            'next_run_at'     => ['required', 'date'],
            'reference'       => ['nullable', 'string', 'max:100'],
            'notes'           => ['nullable', 'string', 'max:2000'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items'           => ['required', 'array', 'min:1'],
            'items.*.description'      => ['required', 'string', 'max:255'],
            'items.*.account_id'       => ['nullable', 'integer'],
            'items.*.tax_rate_id'      => ['nullable', 'integer'],
            'items.*.quantity'         => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price'       => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    private function nextRunDate(string $frequency, int $dayOfMonth): string
    {
        $now = Carbon::today();
        return match ($frequency) {
            'weekly'    => $now->addWeek()->toDateString(),
            'monthly'   => $now->addMonth()->day($dayOfMonth)->toDateString(),
            'quarterly' => $now->addMonths(3)->day($dayOfMonth)->toDateString(),
            'yearly'    => $now->addYear()->day($dayOfMonth)->toDateString(),
            default     => $now->addMonth()->toDateString(),
        };
    }

    private function authorise(Request $request, RecurringInvoice $recurring): void
    {
        abort_unless($recurring->company_id === $request->user()->currentCompany->id, 403);
    }
}

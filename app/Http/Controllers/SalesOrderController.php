<?php

namespace App\Http\Controllers;

use App\Models\GoodsCode;
use App\Models\SalesOrder;
use App\Models\ServiceCode;
use App\Services\DocumentPdfService;
use App\Services\SalesOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class SalesOrderController extends Controller
{
    public function __construct(private readonly SalesOrderService $service) {}

    public function index(Request $request): Response
    {
        $company = $request->user()->currentCompany;
        $status  = $request->query('status', 'all');
        $search  = $request->query('search', '');

        $orders = $company->salesOrders()
            ->with('contact:id,name')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) => $q->where('order_number', 'like', "%{$search}%")
                ->orWhereHas('contact', fn ($c) => $c->where('name', 'like', "%{$search}%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('sales-orders/Index', [
            'orders'        => $orders,
            'currentStatus' => $status,
            'search'        => $search,
            'counts'        => $company->salesOrders()
                ->selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('sales-orders/Form', array_merge($this->formData($request), ['order' => null]));
    }

    public function store(Request $request): RedirectResponse
    {
        $order = $this->service->store($request->user()->currentCompany, $this->validated($request));

        return redirect()->route('sales-orders.show', $order)
            ->with('success', "Sales order {$order->order_number} created.");
    }

    public function show(Request $request, SalesOrder $salesOrder): Response
    {
        $this->authorise($request, $salesOrder);

        $salesOrder->load(['contact', 'items.product:id,name,type', 'items.account:id,code,name',
            'items.taxRate:id,name,rate', 'invoices:id,sales_order_id,invoice_number,status,total', 'createdBy:id,name']);

        return Inertia::render('sales-orders/Show', [
            'order'   => $salesOrder,
            'company' => $request->user()->currentCompany->only('name', 'address', 'city', 'tpin', 'vat_number', 'email', 'phone'),
        ]);
    }

    public function print(Request $request, SalesOrder $salesOrder, DocumentPdfService $pdf): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorise($request, $salesOrder);

        $salesOrder->load(['contact', 'items.account:id,code,name', 'items.taxRate:id,name,rate']);
        $company = $request->user()->currentCompany;

        // An accepted/invoiced order reads as a Sales Order; an open one as a Quotation.
        $isQuote = in_array($salesOrder->status, ['draft', 'sent']);

        return $pdf->streamInline('orders.print', [
            'order'      => $salesOrder,
            'company'    => $company,
            'logoSrc'    => $pdf->logoDataUri($company->logo_path),
            'docType'    => $isQuote ? 'QUOTATION' : 'SALES ORDER',
            'number'     => $salesOrder->order_number,
            'partyLabel' => 'Prepared For',
            'meta'       => array_filter([
                'Date'        => Carbon::parse($salesOrder->order_date)->format('d F Y'),
                'Valid Until' => $salesOrder->valid_until ? Carbon::parse($salesOrder->valid_until)->format('d F Y') : null,
                'Ref'         => $salesOrder->reference,
            ]),
        ], "{$salesOrder->order_number}.pdf");
    }

    public function edit(Request $request, SalesOrder $salesOrder): Response
    {
        $this->authorise($request, $salesOrder);
        abort_unless(in_array($salesOrder->status, ['draft', 'sent', 'accepted']), 422, 'This order can no longer be edited.');

        $salesOrder->load(['items.taxRate', 'items.account']);

        return Inertia::render('sales-orders/Form', array_merge(
            $this->formData($request),
            ['order' => $salesOrder]
        ));
    }

    public function update(Request $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->authorise($request, $salesOrder);
        $this->service->update($salesOrder, $this->validated($request));

        return redirect()->route('sales-orders.show', $salesOrder)->with('success', 'Sales order updated.');
    }

    public function send(Request $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->authorise($request, $salesOrder);
        $this->service->send($salesOrder);

        return back()->with('success', "Sales order {$salesOrder->order_number} marked as sent.");
    }

    public function accept(Request $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->authorise($request, $salesOrder);
        $this->service->accept($salesOrder);

        return back()->with('success', "Sales order {$salesOrder->order_number} accepted.");
    }

    public function cancel(Request $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->authorise($request, $salesOrder);
        $this->service->cancel($salesOrder);

        return back()->with('success', "Sales order {$salesOrder->order_number} cancelled.");
    }

    public function convertToInvoice(Request $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->authorise($request, $salesOrder);

        $validated = $request->validate([
            'lines'   => ['nullable', 'array'],
            'lines.*' => ['numeric', 'min:0'],
        ]);

        $lineQuantities = ! empty($validated['lines'])
            ? array_map('floatval', $validated['lines'])
            : null;

        $invoice = $this->service->convertToInvoice($salesOrder, $lineQuantities);

        return redirect()->route('invoices.show', $invoice)
            ->with('success', "Invoice created from {$salesOrder->order_number}. Send it to recognise revenue.");
    }

    public function destroy(Request $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->authorise($request, $salesOrder);
        abort_unless($salesOrder->status === 'draft', 422, 'Only draft orders can be deleted.');

        $salesOrder->delete();

        return redirect()->route('sales-orders.index')->with('success', 'Sales order deleted.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function formData(Request $request): array
    {
        $company = $request->user()->currentCompany;

        return [
            'contacts'     => $company->contacts()->customers()->active()
                ->orderBy('name')->get(['id', 'name', 'email', 'tpin']),
            'accounts'     => $company->accounts()->active()->ofType('income')
                ->orderBy('code')->get(['id', 'code', 'name']),
            'taxRates'     => $company->taxRates()->active()->vat()
                ->orderBy('name')->get(['id', 'name', 'code', 'rate']),
            'vsdcEnabled'  => (bool) $company->vsdc_initialized,
            'goodsCodes'   => GoodsCode::orderBy('name')->get(['id', 'name', 'hs_code']),
            'serviceCodes' => ServiceCode::orderBy('name')->get(['id', 'name', 'hs_code']),
            'products'     => $company->products()->active()->orderBy('name')
                ->get(['id', 'sku', 'name', 'type', 'sales_price', 'sales_account_id',
                    'tax_rate_id', 'item_type', 'cls_code_id', 'quantity_on_hand']),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'contact_id'      => ['required', 'integer'],
            'order_date'      => ['required', 'date'],
            'valid_until'     => ['nullable', 'date', 'after_or_equal:order_date'],
            'reference'       => ['nullable', 'string', 'max:100'],
            'notes'           => ['nullable', 'string', 'max:2000'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items'                    => ['required', 'array', 'min:1'],
            'items.*.id'               => ['nullable', 'integer'],
            'items.*.description'      => ['required', 'string', 'max:255'],
            'items.*.account_id'       => ['nullable', 'integer'],
            'items.*.product_id'       => ['nullable', 'integer'],
            'items.*.tax_rate_id'      => ['nullable', 'integer'],
            'items.*.quantity'         => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price'       => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.item_type'        => ['nullable', 'in:goods,service'],
            'items.*.cls_code_id'      => ['nullable', 'integer'],
        ]);
    }

    private function authorise(Request $request, SalesOrder $order): void
    {
        abort_unless($order->company_id === $request->user()->currentCompany->id, 403);
    }
}

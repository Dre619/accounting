<?php

namespace App\Http\Controllers;

use App\Models\GoodsCode;
use App\Models\PurchaseOrder;
use App\Models\ServiceCode;
use App\Services\DocumentPdfService;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $service) {}

    public function index(Request $request): Response
    {
        $company = $request->user()->currentCompany;
        $status  = $request->query('status', 'all');
        $search  = $request->query('search', '');

        $orders = $company->purchaseOrders()
            ->with('contact:id,name')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) => $q->where('po_number', 'like', "%{$search}%")
                ->orWhereHas('contact', fn ($c) => $c->where('name', 'like', "%{$search}%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('purchase-orders/Index', [
            'orders'        => $orders,
            'currentStatus' => $status,
            'search'        => $search,
            'counts'        => $company->purchaseOrders()
                ->selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('purchase-orders/Form', array_merge($this->formData($request), ['order' => null]));
    }

    public function store(Request $request): RedirectResponse
    {
        $order = $this->service->store($request->user()->currentCompany, $this->validated($request));

        return redirect()->route('purchase-orders.show', $order)
            ->with('success', "Purchase order {$order->po_number} created.");
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        $this->authorise($request, $purchaseOrder);

        $purchaseOrder->load(['contact', 'items.product:id,name,type', 'items.account:id,code,name',
            'items.taxRate:id,name,rate', 'bills:id,purchase_order_id,bill_number,status,total', 'createdBy:id,name']);

        return Inertia::render('purchase-orders/Show', [
            'order'   => $purchaseOrder,
            'company' => $request->user()->currentCompany->only('name', 'address', 'city', 'tpin', 'vat_number', 'email', 'phone'),
        ]);
    }

    public function print(Request $request, PurchaseOrder $purchaseOrder, DocumentPdfService $pdf): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorise($request, $purchaseOrder);

        $purchaseOrder->load(['contact', 'items.account:id,code,name', 'items.taxRate:id,name,rate']);
        $company = $request->user()->currentCompany;

        return $pdf->streamInline('orders.print', [
            'order'      => $purchaseOrder,
            'company'    => $company,
            'logoSrc'    => $pdf->logoDataUri($company->logo_path),
            'docType'    => 'PURCHASE ORDER',
            'number'     => $purchaseOrder->po_number,
            'partyLabel' => 'Supplier',
            'meta'       => array_filter([
                'Order Date' => Carbon::parse($purchaseOrder->order_date)->format('d F Y'),
                'Expected'   => $purchaseOrder->expected_date ? Carbon::parse($purchaseOrder->expected_date)->format('d F Y') : null,
                'Ref'        => $purchaseOrder->reference,
            ]),
        ], "{$purchaseOrder->po_number}.pdf");
    }

    public function edit(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        $this->authorise($request, $purchaseOrder);
        abort_unless(in_array($purchaseOrder->status, ['draft', 'sent']), 422, 'This order can no longer be edited.');

        $purchaseOrder->load(['items.taxRate', 'items.account']);

        return Inertia::render('purchase-orders/Form', array_merge(
            $this->formData($request),
            ['order' => $purchaseOrder]
        ));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorise($request, $purchaseOrder);
        $this->service->update($purchaseOrder, $this->validated($request));

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Purchase order updated.');
    }

    public function send(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorise($request, $purchaseOrder);
        $this->service->send($purchaseOrder);

        return back()->with('success', "Purchase order {$purchaseOrder->po_number} marked as sent.");
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorise($request, $purchaseOrder);
        $this->service->cancel($purchaseOrder);

        return back()->with('success', "Purchase order {$purchaseOrder->po_number} cancelled.");
    }

    public function convertToBill(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorise($request, $purchaseOrder);

        $validated = $request->validate([
            'lines'   => ['nullable', 'array'],
            'lines.*' => ['numeric', 'min:0'],
        ]);

        $lineQuantities = ! empty($validated['lines'])
            ? array_map('floatval', $validated['lines'])
            : null;

        $bill = $this->service->convertToBill($purchaseOrder, $lineQuantities);

        return redirect()->route('bills.show', $bill)
            ->with('success', "Bill created from {$purchaseOrder->po_number}. Approve it to receive stock.");
    }

    public function destroy(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorise($request, $purchaseOrder);
        abort_unless($purchaseOrder->status === 'draft', 422, 'Only draft orders can be deleted.');

        $purchaseOrder->delete();

        return redirect()->route('purchase-orders.index')->with('success', 'Purchase order deleted.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function formData(Request $request): array
    {
        $company = $request->user()->currentCompany;

        return [
            'contacts'     => $company->contacts()->suppliers()->active()
                ->orderBy('name')->get(['id', 'name', 'email', 'tpin']),
            'accounts'     => $company->accounts()->active()->ofType('expense')
                ->orderBy('code')->get(['id', 'code', 'name']),
            'taxRates'     => $company->taxRates()->active()->vat()
                ->orderBy('name')->get(['id', 'name', 'code', 'rate']),
            'vsdcEnabled'  => (bool) $company->vsdc_initialized,
            'goodsCodes'   => GoodsCode::orderBy('name')->get(['id', 'name', 'hs_code']),
            'serviceCodes' => ServiceCode::orderBy('name')->get(['id', 'name', 'hs_code']),
            'products'     => $company->products()->active()->orderBy('name')
                ->get(['id', 'sku', 'name', 'type', 'purchase_account_id',
                    'tax_rate_id', 'item_type', 'cls_code_id', 'quantity_on_hand', 'average_cost']),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'contact_id'      => ['required', 'integer'],
            'order_date'      => ['required', 'date'],
            'expected_date'   => ['nullable', 'date', 'after_or_equal:order_date'],
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

    private function authorise(Request $request, PurchaseOrder $order): void
    {
        abort_unless($order->company_id === $request->user()->currentCompany->id, 403);
    }
}

<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\TaxRate;
use Illuminate\Support\Facades\DB;

class SalesOrderService
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function store(Company $company, array $data): SalesOrder
    {
        return DB::transaction(function () use ($company, $data) {
            $order = $company->salesOrders()->create([
                'contact_id'      => $data['contact_id'],
                'order_number'    => $this->nextNumber($company),
                'status'          => 'draft',
                'order_date'      => $data['order_date'],
                'valid_until'     => $data['valid_until'] ?? null,
                'reference'       => $data['reference'] ?? null,
                'notes'           => $data['notes'] ?? null,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'created_by'      => auth()->id(),
            ]);

            $this->syncItems($order, $data['items'] ?? []);
            $order->recalculate();

            return $order->fresh();
        });
    }

    public function update(SalesOrder $order, array $data): SalesOrder
    {
        abort_unless(in_array($order->status, ['draft', 'sent', 'accepted']), 422, 'This order can no longer be edited.');

        return DB::transaction(function () use ($order, $data) {
            $order->update([
                'contact_id'      => $data['contact_id'],
                'order_date'      => $data['order_date'],
                'valid_until'     => $data['valid_until'] ?? null,
                'reference'       => $data['reference'] ?? null,
                'notes'           => $data['notes'] ?? null,
                'discount_amount' => $data['discount_amount'] ?? 0,
            ]);

            $this->syncItems($order, $data['items'] ?? []);
            $order->recalculate();

            return $order->fresh();
        });
    }

    public function send(SalesOrder $order): SalesOrder
    {
        abort_unless($order->status === 'draft', 422, 'Only draft orders can be sent.');

        $order->update(['status' => 'sent', 'sent_at' => now()]);

        return $order;
    }

    public function accept(SalesOrder $order): SalesOrder
    {
        abort_unless(in_array($order->status, ['draft', 'sent']), 422, 'Only an open order can be accepted.');

        $order->update(['status' => 'accepted']);

        return $order;
    }

    public function cancel(SalesOrder $order): SalesOrder
    {
        abort_if(in_array($order->status, ['invoiced', 'cancelled']), 422, 'This order can no longer be cancelled.');

        $order->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return $order;
    }

    /**
     * Turn a sales order / quotation into a draft invoice. Revenue is recognised
     * and — for inventory lines — stock is issued with COGS posted when that
     * invoice is sent (existing InvoiceService behaviour).
     *
     * Pass $lineQuantities [so_item_id => qty] to invoice only part of the order;
     * quantities are capped at each line's outstanding balance. When null, the
     * full outstanding balance of every line is invoiced. The order becomes
     * 'invoiced' once fully invoiced, otherwise 'partial'.
     */
    public function convertToInvoice(SalesOrder $order, ?array $lineQuantities = null): Invoice
    {
        abort_if(in_array($order->status, ['invoiced', 'cancelled']), 422, 'This order cannot be converted to an invoice.');

        return DB::transaction(function () use ($order, $lineQuantities) {
            $order->loadMissing('items');

            $invoiceItems = [];
            foreach ($order->items as $item) {
                $outstanding = $item->quantityOutstanding();
                if ($outstanding <= 0) {
                    continue;
                }

                $qty = $lineQuantities === null
                    ? $outstanding
                    : min((float) ($lineQuantities[$item->id] ?? 0), $outstanding);

                if ($qty <= 0) {
                    continue;
                }

                $invoiceItems[] = [
                    'description'      => $item->description,
                    'product_id'       => $item->product_id,
                    'account_id'       => $item->account_id,
                    'tax_rate_id'      => $item->tax_rate_id,
                    'quantity'         => $qty,
                    'unit_price'       => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'item_type'        => $item->item_type,
                    'cls_code_id'      => $item->cls_code_id,
                ];

                $item->quantity_invoiced = (float) $item->quantity_invoiced + $qty;
            }

            abort_if(empty($invoiceItems), 422, 'Nothing to invoice — enter a quantity.');

            $invoice = $this->invoices->store($order->company, [
                'contact_id'      => $order->contact_id,
                'issue_date'      => now()->toDateString(),
                'due_date'        => now()->addDays(30)->toDateString(),
                'reference'       => $order->order_number,
                'discount_amount' => 0,
                'items'           => $invoiceItems,
            ]);

            $invoice->update(['sales_order_id' => $order->id]);
            $order->items->each->save();
            $order->update(['status' => $order->isFullyInvoiced() ? 'invoiced' : 'partial']);

            return $invoice;
        });
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function nextNumber(Company $company): string
    {
        $seq = $company->salesOrders()->withTrashed()->count() + 1;

        return 'SO-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function syncItems(SalesOrder $order, array $items): void
    {
        $existing = $order->items()->pluck('id')->toArray();
        $kept     = [];

        foreach ($items as $i => $itemData) {
            $taxRate  = isset($itemData['tax_rate_id']) ? TaxRate::find($itemData['tax_rate_id']) : null;
            $gross    = ($itemData['quantity'] ?? 1) * ($itemData['unit_price'] ?? 0);
            $discount = $gross * (($itemData['discount_percent'] ?? 0) / 100);
            $subtotal = round($gross - $discount, 2);
            $tax      = $taxRate ? round($subtotal * ($taxRate->rate / 100), 2) : 0;
            $total    = $subtotal + $tax;

            $payload = [
                'product_id'       => $itemData['product_id'] ?? null,
                'account_id'       => $itemData['account_id'] ?? null,
                'tax_rate_id'      => $itemData['tax_rate_id'] ?? null,
                'description'      => $itemData['description'],
                'quantity'         => $itemData['quantity'] ?? 1,
                'unit_price'       => $itemData['unit_price'] ?? 0,
                'discount_percent' => $itemData['discount_percent'] ?? 0,
                'subtotal'         => $subtotal,
                'tax_amount'       => $tax,
                'total'            => $total,
                'item_type'        => $itemData['item_type'] ?? 'service',
                'cls_code_id'      => $itemData['cls_code_id'] ?? null,
                'sort_order'       => $i,
            ];

            if (! empty($itemData['id']) && in_array($itemData['id'], $existing)) {
                SalesOrderItem::find($itemData['id'])->update($payload);
                $kept[] = $itemData['id'];
            } else {
                $kept[] = $order->items()->create($payload)->id;
            }
        }

        SalesOrderItem::whereIn('id', array_diff($existing, $kept))->delete();
    }
}

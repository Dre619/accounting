<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\TaxRate;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(private readonly BillService $bills) {}

    public function store(Company $company, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($company, $data) {
            $po = $company->purchaseOrders()->create([
                'contact_id'      => $data['contact_id'],
                'po_number'       => $this->nextNumber($company),
                'status'          => 'draft',
                'order_date'      => $data['order_date'],
                'expected_date'   => $data['expected_date'] ?? null,
                'reference'       => $data['reference'] ?? null,
                'notes'           => $data['notes'] ?? null,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'created_by'      => auth()->id(),
            ]);

            $this->syncItems($po, $data['items'] ?? []);
            $po->recalculate();

            return $po->fresh();
        });
    }

    public function update(PurchaseOrder $po, array $data): PurchaseOrder
    {
        abort_unless(in_array($po->status, ['draft', 'sent']), 422, 'Only draft or sent orders can be edited.');

        return DB::transaction(function () use ($po, $data) {
            $po->update([
                'contact_id'      => $data['contact_id'],
                'order_date'      => $data['order_date'],
                'expected_date'   => $data['expected_date'] ?? null,
                'reference'       => $data['reference'] ?? null,
                'notes'           => $data['notes'] ?? null,
                'discount_amount' => $data['discount_amount'] ?? 0,
            ]);

            $this->syncItems($po, $data['items'] ?? []);
            $po->recalculate();

            return $po->fresh();
        });
    }

    public function send(PurchaseOrder $po): PurchaseOrder
    {
        abort_unless($po->status === 'draft', 422, 'Only draft orders can be sent.');

        $po->update(['status' => 'sent', 'sent_at' => now()]);

        return $po;
    }

    public function cancel(PurchaseOrder $po): PurchaseOrder
    {
        abort_if(in_array($po->status, ['billed', 'cancelled']), 422, 'This order can no longer be cancelled.');

        $po->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return $po;
    }

    /**
     * Turn a purchase order into a draft supplier bill. Stock is received and
     * the ledger is posted when that bill is approved (existing BillService
     * behaviour), so no separate goods-receipt posting is needed here.
     *
     * Pass $lineQuantities [po_item_id => qty] to bill only part of the order;
     * quantities are capped at each line's outstanding balance. When null, the
     * full outstanding balance of every line is billed. The order becomes
     * 'billed' once fully received, otherwise 'partial'.
     */
    public function convertToBill(PurchaseOrder $po, ?array $lineQuantities = null): Bill
    {
        abort_if(in_array($po->status, ['billed', 'cancelled']), 422, 'This order cannot be converted to a bill.');

        return DB::transaction(function () use ($po, $lineQuantities) {
            $po->loadMissing('items');

            $billItems = [];
            foreach ($po->items as $item) {
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

                $billItems[] = [
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

                $item->quantity_received = (float) $item->quantity_received + $qty;
            }

            abort_if(empty($billItems), 422, 'Nothing to bill — enter a quantity to receive.');

            $bill = $this->bills->store($po->company, [
                'contact_id'      => $po->contact_id,
                'issue_date'      => now()->toDateString(),
                'due_date'        => now()->addDays(30)->toDateString(),
                'reference'       => $po->po_number,
                'discount_amount' => 0,
                'items'           => $billItems,
            ]);

            $bill->update(['purchase_order_id' => $po->id]);
            $po->items->each->save();
            $po->update(['status' => $po->isFullyReceived() ? 'billed' : 'partial']);

            return $bill;
        });
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function nextNumber(Company $company): string
    {
        $seq = $company->purchaseOrders()->withTrashed()->count() + 1;

        return 'PO-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function syncItems(PurchaseOrder $po, array $items): void
    {
        $existing = $po->items()->pluck('id')->toArray();
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
                'item_type'        => $itemData['item_type'] ?? 'goods',
                'cls_code_id'      => $itemData['cls_code_id'] ?? null,
                'sort_order'       => $i,
            ];

            if (! empty($itemData['id']) && in_array($itemData['id'], $existing)) {
                PurchaseOrderItem::find($itemData['id'])->update($payload);
                $kept[] = $itemData['id'];
            } else {
                $kept[] = $po->items()->create($payload)->id;
            }
        }

        PurchaseOrderItem::whereIn('id', array_diff($existing, $kept))->delete();
    }
}

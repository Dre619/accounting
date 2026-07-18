<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Owns all stock-quantity math for inventory products: recording immutable
 * stock movements and maintaining the cached quantity_on_hand / average_cost
 * on the product. Uses perpetual inventory with weighted-average costing.
 *
 * GL postings for purchases (DR Inventory / CR AP) and sales COGS
 * (DR COGS / CR Inventory) are owned by BillService / InvoiceService, which
 * pass their journal_entry_id in. Standalone stock adjustments are the
 * exception — they post their own balanced entry here.
 */
class StockService
{
    /**
     * Receive stock in (purchase or opening balance) and recompute the
     * weighted-average unit cost. Returns the recorded movement.
     */
    public function receiveStock(Product $product, float $quantity, float $unitCost, array $opts = []): StockMovement
    {
        abort_if($quantity <= 0, 422, 'Received quantity must be positive.');

        return DB::transaction(function () use ($product, $quantity, $unitCost, $opts) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

            $oldQty = (float) $product->quantity_on_hand;
            $oldAvg = (float) $product->average_cost;
            $newQty = $oldQty + $quantity;

            // Weighted average only moves on receipt.
            $newAvg = $newQty != 0.0
                ? (($oldQty * $oldAvg) + ($quantity * $unitCost)) / $newQty
                : $unitCost;
            $newAvg = round($newAvg, 4);

            return $this->record($product, [
                'type'      => $opts['type'] ?? 'purchase',
                'quantity'  => $quantity,          // positive = in
                'unit_cost' => round($unitCost, 4),
                'new_qty'   => $newQty,
                'new_avg'   => $newAvg,
            ], $opts);
        });
    }

    /**
     * Issue stock out (a sale) at the current weighted-average cost. The
     * returned movement's total_cost is the COGS for the caller to post.
     * Average cost is unchanged by an issue.
     */
    public function issueStock(Product $product, float $quantity, array $opts = []): StockMovement
    {
        abort_if($quantity <= 0, 422, 'Issued quantity must be positive.');

        return DB::transaction(function () use ($product, $quantity, $opts) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

            $avg    = (float) $product->average_cost;
            $newQty = (float) $product->quantity_on_hand - $quantity;

            return $this->record($product, [
                'type'      => $opts['type'] ?? 'sale',
                'quantity'  => -$quantity,         // negative = out
                'unit_cost' => round($avg, 4),
                'new_qty'   => $newQty,
                'new_avg'   => round($avg, 4),     // unchanged
            ], $opts);
        });
    }

    /**
     * Set on-hand to an absolute quantity (stock take) and post the balanced
     * GL entry for the change, valued at current average cost. A shortfall
     * credits Inventory / debits the adjustment account; a surplus reverses it.
     */
    public function adjustStock(Product $product, float $newQuantity, array $opts = []): StockMovement
    {
        return DB::transaction(function () use ($product, $newQuantity, $opts) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

            $currentQty = (float) $product->quantity_on_hand;
            $delta      = $newQuantity - $currentQty;

            abort_if($delta == 0.0, 422, 'Adjustment results in no change to stock on hand.');

            $avg = (float) $product->average_cost;

            $entry = $this->postAdjustmentJournal($product, $delta, $avg, $opts);

            return $this->record($product, [
                'type'             => 'adjustment',
                'quantity'         => $delta,
                'unit_cost'        => round($avg, 4),
                'new_qty'          => $newQuantity,
                'new_avg'          => round($avg, 4),
                'journal_entry_id' => $entry?->id,
            ], $opts);
        });
    }

    /**
     * Seed a new inventory product's opening stock: receives the quantity at the
     * given cost and posts the opening value DR Inventory / CR Retained Earnings.
     */
    public function openingStock(Product $product, float $quantity, float $unitCost, ?string $date = null): void
    {
        if ($quantity <= 0) {
            return;
        }

        DB::transaction(function () use ($product, $quantity, $unitCost, $date) {
            $movement = $this->receiveStock($product, $quantity, $unitCost, [
                'type'        => 'opening',
                'description' => 'Opening balance',
                'date'        => $date,
            ]);

            $value = round($quantity * $unitCost, 2);
            if ($value <= 0) {
                return;
            }

            $company = $product->company;
            $invId   = $product->inventory_account_id ?? $this->accountId($company->id, '1300');
            $eqId    = $this->accountId($company->id, '3100'); // Retained Earnings
            if (! $invId || ! $eqId) {
                return;
            }

            $seq   = $company->journalEntries()->count() + 1;
            $entry = JournalEntry::create([
                'company_id'      => $company->id,
                'entry_number'    => 'JNL-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'entry_date'      => $date ?? now()->toDateString(),
                'description'     => "Opening stock — {$product->name}",
                'status'          => 'posted',
                'source'          => 'opening',
                'sourceable_type' => Product::class,
                'sourceable_id'   => $product->id,
                'created_by'      => auth()->id(),
                'posted_at'       => now(),
            ]);

            JournalLine::insert([
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $invId,
                    'description'      => "Opening stock — {$product->name}",
                    'debit'            => $value,
                    'credit'           => 0,
                    'contact_id'       => null,
                    'sort_order'       => 0,
                    'created_at'       => now(), 'updated_at' => now(),
                ],
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $eqId,
                    'description'      => "Opening stock equity — {$product->name}",
                    'debit'            => 0,
                    'credit'           => $value,
                    'contact_id'       => null,
                    'sort_order'       => 1,
                    'created_at'       => now(), 'updated_at' => now(),
                ],
            ]);

            $movement->update(['journal_entry_id' => $entry->id]);
        });
    }

    /**
     * Undo the stock movements a source document (Invoice/Bill) created — used
     * when the document is voided. Puts sold stock back at its issue cost and
     * removes received stock. Does NOT post GL: the caller's journal reversal
     * already unwinds the ledger; this only corrects quantities.
     */
    public function reverseFor(Model $source): void
    {
        $movements = StockMovement::where('sourceable_type', $source::class)
            ->where('sourceable_id', $source->getKey())
            ->where('type', '!=', 'return')
            ->get();

        foreach ($movements as $mv) {
            $product = $mv->product;
            if (! $product || ! $product->tracksStock()) {
                continue;
            }

            $qty = abs((float) $mv->quantity);
            if ($qty == 0.0) {
                continue;
            }

            $opts = [
                'type'        => 'return',
                'source'      => $source,
                'description' => "Reversal of {$mv->type}",
            ];

            if ((float) $mv->quantity < 0) {
                // Was an outflow (sale) → return stock at the cost it left at.
                $this->receiveStock($product, $qty, (float) $mv->unit_cost, $opts);
            } else {
                // Was an inflow (purchase) → take the stock back out.
                $this->issueStock($product, $qty, $opts);
            }
        }
    }

    // ── internals ────────────────────────────────────────────────────────────

    /**
     * Write the immutable movement row and refresh the product's cached
     * quantity_on_hand / average_cost. Non-inventory products never move stock.
     */
    private function record(Product $product, array $m, array $opts): StockMovement
    {
        abort_unless($product->tracksStock(), 422, 'Only inventory products track stock.');

        $movement = $product->stockMovements()->create([
            'company_id'       => $product->company_id,
            'warehouse_id'     => $opts['warehouse_id'] ?? $this->defaultWarehouseId($product),
            'type'             => $m['type'],
            'quantity'         => $m['quantity'],
            'unit_cost'        => $m['unit_cost'],
            'total_cost'       => round($m['quantity'] * $m['unit_cost'], 2),
            'running_qty'      => $m['new_qty'],
            'running_avg_cost' => $m['new_avg'],
            'sourceable_type'  => isset($opts['source']) ? $opts['source']::class : ($m['sourceable_type'] ?? null),
            'sourceable_id'    => isset($opts['source']) ? $opts['source']->getKey() : ($m['sourceable_id'] ?? null),
            'journal_entry_id' => $m['journal_entry_id'] ?? ($opts['journal_entry_id'] ?? null),
            'description'      => $opts['description'] ?? null,
            'movement_date'    => $opts['date'] ?? now()->toDateString(),
            'created_by'       => $opts['created_by'] ?? auth()->id(),
        ]);

        $product->forceFill([
            'quantity_on_hand' => $m['new_qty'],
            'average_cost'     => $m['new_avg'],
        ])->save();

        return $movement;
    }

    private function postAdjustmentJournal(Product $product, float $delta, float $avg, array $opts): ?JournalEntry
    {
        $value = round(abs($delta) * $avg, 2);
        if ($value == 0.0) {
            return null; // nothing to post (e.g. zero-cost item)
        }

        $company     = $product->company;
        $inventoryId = $product->inventory_account_id ?? $this->accountId($company->id, '1300');
        $adjustId    = $product->cogs_account_id ?? $this->accountId($company->id, '5000');

        if (! $inventoryId || ! $adjustId) {
            return null; // chart of accounts missing the needed accounts
        }

        $seq   = $company->journalEntries()->count() + 1;
        $entry = JournalEntry::create([
            'company_id'      => $company->id,
            'entry_number'    => 'JNL-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'entry_date'      => $opts['date'] ?? now()->toDateString(),
            'description'     => "Stock adjustment — {$product->name}",
            'status'          => 'posted',
            'source'          => 'stock_adjustment',
            'sourceable_type' => Product::class,
            'sourceable_id'   => $product->id,
            'created_by'      => $opts['created_by'] ?? auth()->id(),
            'posted_at'       => now(),
        ]);

        // Surplus (delta > 0): DR Inventory, CR adjustment (income/contra-COGS).
        // Shortfall (delta < 0): DR adjustment (expense), CR Inventory.
        $inventoryDebit = $delta > 0 ? $value : 0;
        $inventoryCredit = $delta > 0 ? 0 : $value;

        JournalLine::insert([
            [
                'journal_entry_id' => $entry->id,
                'account_id'       => $inventoryId,
                'description'      => "Inventory adjustment — {$product->name}",
                'debit'            => $inventoryDebit,
                'credit'           => $inventoryCredit,
                'contact_id'       => null,
                'sort_order'       => 0,
                'created_at'       => now(), 'updated_at' => now(),
            ],
            [
                'journal_entry_id' => $entry->id,
                'account_id'       => $adjustId,
                'description'      => "Stock adjustment offset — {$product->name}",
                'debit'            => $inventoryCredit,
                'credit'           => $inventoryDebit,
                'contact_id'       => null,
                'sort_order'       => 1,
                'created_at'       => now(), 'updated_at' => now(),
            ],
        ]);

        return $entry;
    }

    private function defaultWarehouseId(Product $product): ?int
    {
        return $product->company->defaultWarehouse?->id;
    }

    private function accountId(int $companyId, string $code): ?int
    {
        return Account::where('company_id', $companyId)->where('code', $code)->value('id');
    }
}

<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\TaxRate;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(private readonly StockService $stock) {}

    public function store(Company $company, array $data): Invoice
    {
        return DB::transaction(function () use ($company, $data) {
            $invoice = $company->invoices()->create([
                'contact_id'        => $data['contact_id'],
                'invoice_number'    => $company->nextInvoiceNumber(),
                'status'            => 'draft',
                'issue_date'        => $data['issue_date'],
                'due_date'          => $data['due_date'],
                'reference'         => $data['reference'] ?? null,
                'notes'             => $data['notes'] ?? null,
                'footer'            => $data['footer'] ?? null,
                'discount_amount'   => $data['discount_amount'] ?? 0,
                'receivable_account_id' => $this->defaultReceivableAccount($company)?->id,
                'created_by'        => auth()->id(),
            ]);

            $this->syncItems($invoice, $data['items'] ?? []);
            $invoice->recalculate();

            return $invoice->fresh();
        });
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        abort_unless($invoice->status === 'draft', 422, 'Only draft invoices can be edited.');

        return DB::transaction(function () use ($invoice, $data) {
            $invoice->update([
                'contact_id'      => $data['contact_id'],
                'issue_date'      => $data['issue_date'],
                'due_date'        => $data['due_date'],
                'reference'       => $data['reference'] ?? null,
                'notes'           => $data['notes'] ?? null,
                'footer'          => $data['footer'] ?? null,
                'discount_amount' => $data['discount_amount'] ?? 0,
            ]);

            $this->syncItems($invoice, $data['items'] ?? []);
            $invoice->recalculate();

            return $invoice->fresh();
        });
    }

    public function send(Invoice $invoice): Invoice
    {
        abort_unless(in_array($invoice->status, ['draft', 'sent']), 422, 'Cannot send a paid or voided invoice.');

        $invoice->update([
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        // Create accounting journal entry if not already present
        if ($invoice->journalEntries()->where('source', 'invoice')->doesntExist()) {
            $this->createJournalEntry($invoice);
        }

        return $invoice;
    }

    public function void(Invoice $invoice): Invoice
    {
        abort_unless(! in_array($invoice->status, ['paid', 'void']), 422, 'Cannot void a paid or already voided invoice.');

        // Voiding reverses the full invoice, but any payment already received still
        // credits receivables — which would leave AR negative by the amount paid.
        // Checked on amount_paid rather than status so partial/overdue are both covered.
        abort_if(
            (float) $invoice->amount_paid > 0,
            422,
            'This invoice has payments allocated to it. Unallocate or refund the payment before voiding.'
        );

        $invoice->update([
            'status'     => 'void',
            'voided_at'  => now(),
        ]);

        // Reverse the journal entry
        $original = $invoice->journalEntries()->where('source', 'invoice')->first();
        if ($original) {
            $this->reverseJournalEntry($original, $invoice);
        }

        // Return any issued stock to inventory (GL already unwound above).
        $this->stock->reverseFor($invoice);

        return $invoice;
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function syncItems(Invoice $invoice, array $items): void
    {
        $existing = $invoice->items()->pluck('id')->toArray();
        $kept     = [];

        foreach ($items as $i => $itemData) {
            $taxRate = isset($itemData['tax_rate_id'])
                ? TaxRate::find($itemData['tax_rate_id'])
                : null;

            $gross    = ($itemData['quantity'] ?? 1) * ($itemData['unit_price'] ?? 0);
            $discount = $gross * (($itemData['discount_percent'] ?? 0) / 100);
            $subtotal = round($gross - $discount, 2);
            $tax      = $taxRate ? round($subtotal * ($taxRate->rate / 100), 2) : 0;
            $total    = $subtotal + $tax;

            if (! empty($itemData['id']) && in_array($itemData['id'], $existing)) {
                $item = InvoiceItem::find($itemData['id']);
                $item->update([
                    'account_id'       => $itemData['account_id'] ?? null,
                    'product_id'       => $itemData['product_id'] ?? null,
                    'tax_rate_id'      => $itemData['tax_rate_id'] ?? null,
                    'description'      => $itemData['description'],
                    'quantity'         => $itemData['quantity'] ?? 1,
                    'unit_price'       => $itemData['unit_price'] ?? 0,
                    'discount_percent' => $itemData['discount_percent'] ?? 0,
                    'subtotal'         => $subtotal,
                    'tax_amount'       => $tax,
                    'total'            => $total,
                    'sort_order'       => $i,
                    'item_type'        => $itemData['item_type'] ?? 'service',
                    'cls_code_id'      => $itemData['cls_code_id'] ?? null,
                ]);
                $kept[] = $item->id;
            } else {
                $item = $invoice->items()->create([
                    'account_id'       => $itemData['account_id'] ?? null,
                    'product_id'       => $itemData['product_id'] ?? null,
                    'tax_rate_id'      => $itemData['tax_rate_id'] ?? null,
                    'description'      => $itemData['description'],
                    'quantity'         => $itemData['quantity'] ?? 1,
                    'unit_price'       => $itemData['unit_price'] ?? 0,
                    'discount_percent' => $itemData['discount_percent'] ?? 0,
                    'subtotal'         => $subtotal,
                    'tax_amount'       => $tax,
                    'total'            => $total,
                    'sort_order'       => $i,
                    'item_type'        => $itemData['item_type'] ?? 'service',
                    'cls_code_id'      => $itemData['cls_code_id'] ?? null,
                ]);
                $kept[] = $item->id;
            }
        }

        // Delete removed items
        $toDelete = array_diff($existing, $kept);
        if ($toDelete) {
            InvoiceItem::whereIn('id', $toDelete)->delete();
        }
    }

    public function ensureJournalEntry(Invoice $invoice): void
    {
        if ($invoice->journalEntries()->where('source', 'invoice')->doesntExist()) {
            $this->createJournalEntry($invoice);
        }
    }

    private function createJournalEntry(Invoice $invoice): void
    {
        $company = $invoice->company;

        $entry = JournalEntry::create([
            'company_id'       => $company->id,
            'entry_number'     => $company->nextJournalEntryNumber(),
            'entry_date'       => $invoice->issue_date,
            'description'      => "Invoice {$invoice->invoice_number} — {$invoice->contact->name}",
            'status'           => 'posted',
            'source'           => 'invoice',
            'sourceable_type'  => Invoice::class,
            'sourceable_id'    => $invoice->id,
            'created_by'       => auth()->id(),
            'posted_at'        => now(),
        ]);

        $lines = [];
        $sort  = 0;

        // DR Accounts Receivable — full invoice total
        if ($invoice->receivable_account_id) {
            $lines[] = [
                'journal_entry_id' => $entry->id,
                'account_id'       => $invoice->receivable_account_id,
                'description'      => "Invoice {$invoice->invoice_number}",
                'debit'            => $invoice->total,
                'credit'           => 0,
                'contact_id'       => $invoice->contact_id,
                'sort_order'       => $sort++,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
        }

        // CR Revenue account(s) per line item
        $revenueByAccount = $invoice->items()
            ->whereNotNull('account_id')
            ->selectRaw('account_id, SUM(subtotal) as total')
            ->groupBy('account_id')
            ->get();

        foreach ($revenueByAccount as $row) {
            $lines[] = [
                'journal_entry_id' => $entry->id,
                'account_id'       => $row->account_id,
                'description'      => "Revenue — Invoice {$invoice->invoice_number}",
                'debit'            => 0,
                'credit'           => $row->total,
                'contact_id'       => $invoice->contact_id,
                'sort_order'       => $sort++,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
        }

        // CR VAT Payable (Output VAT)
        if ($invoice->tax_amount > 0) {
            $vatAccount = Account::where('company_id', $company->id)
                ->where('code', '2100')->first();

            if ($vatAccount) {
                $lines[] = [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $vatAccount->id,
                    'description'      => "Output VAT — Invoice {$invoice->invoice_number}",
                    'debit'            => 0,
                    'credit'           => $invoice->tax_amount,
                    'contact_id'       => null,
                    'sort_order'       => $sort++,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
            }
        }

        // Cost of goods sold + inventory relief for inventory-tracked lines.
        // Each such line issues stock at weighted-average cost; the returned
        // movement's cost is the COGS to post (DR COGS / CR Inventory).
        $cogsByAccount = [];
        $invByAccount  = [];

        foreach ($invoice->items()->whereNotNull('product_id')->with('product')->get() as $item) {
            $product = $item->product;
            if (! $product || ! $product->tracksStock()) {
                continue;
            }

            $movement = $this->stock->issueStock($product, (float) $item->quantity, [
                'source'           => $invoice,
                'journal_entry_id' => $entry->id,
                'date'             => $invoice->issue_date,
                'description'      => "COGS — Invoice {$invoice->invoice_number}",
            ]);

            $cogs = round(abs((float) $movement->total_cost), 2);
            if ($cogs <= 0) {
                continue; // stock still moved; nothing to post at zero cost
            }

            $cogsAcc = $product->cogs_account_id ?? $this->accountIdByCode($company->id, '5000');
            $invAcc  = $product->inventory_account_id ?? $this->accountIdByCode($company->id, '1300');
            if (! $cogsAcc || ! $invAcc) {
                continue;
            }

            $cogsByAccount[$cogsAcc] = ($cogsByAccount[$cogsAcc] ?? 0) + $cogs;
            $invByAccount[$invAcc]   = ($invByAccount[$invAcc] ?? 0) + $cogs;
        }

        foreach ($cogsByAccount as $accId => $amount) {
            $lines[] = [
                'journal_entry_id' => $entry->id,
                'account_id'       => $accId,
                'description'      => "COGS — Invoice {$invoice->invoice_number}",
                'debit'            => round($amount, 2),
                'credit'           => 0,
                'contact_id'       => null,
                'sort_order'       => $sort++,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
        }

        foreach ($invByAccount as $accId => $amount) {
            $lines[] = [
                'journal_entry_id' => $entry->id,
                'account_id'       => $accId,
                'description'      => "Inventory relief — Invoice {$invoice->invoice_number}",
                'debit'            => 0,
                'credit'           => round($amount, 2),
                'contact_id'       => null,
                'sort_order'       => $sort++,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
        }

        \App\Models\JournalLine::insert($lines);
    }

    private function accountIdByCode(int $companyId, string $code): ?int
    {
        return Account::where('company_id', $companyId)->where('code', $code)->value('id');
    }

    private function reverseJournalEntry(JournalEntry $original, Invoice $invoice): void
    {
        $company = $invoice->company;

        $reversal = JournalEntry::create([
            'company_id'       => $company->id,
            'entry_number'     => $company->nextJournalEntryNumber(),
            'entry_date'       => now()->toDateString(),
            'description'      => "Void reversal — Invoice {$invoice->invoice_number}",
            'status'           => 'posted',
            'source'           => 'invoice',
            'sourceable_type'  => Invoice::class,
            'sourceable_id'    => $invoice->id,
            'created_by'       => auth()->id(),
            'posted_at'        => now(),
        ]);

        $lines = $original->lines->map(fn ($line) => [
            'journal_entry_id' => $reversal->id,
            'account_id'       => $line->account_id,
            'description'      => $line->description . ' (reversal)',
            'debit'            => $line->credit,  // swap
            'credit'           => $line->debit,
            'contact_id'       => $line->contact_id,
            'sort_order'       => $line->sort_order,
            'created_at'       => now(),
            'updated_at'       => now(),
        ])->toArray();

        \App\Models\JournalLine::insert($lines);
    }

    private function defaultReceivableAccount(Company $company): ?Account
    {
        return Account::where('company_id', $company->id)
            ->where('code', '1200')
            ->first();
    }
}

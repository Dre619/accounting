<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\TaxRate;
use Illuminate\Support\Facades\DB;

class BillService
{
    public function store(Company $company, array $data): Bill
    {
        return DB::transaction(function () use ($company, $data) {
            $bill = $company->bills()->create([
                'contact_id'      => $data['contact_id'],
                'bill_number'     => $data['bill_number'] ?? null,
                'reference'       => $data['reference'] ?? null,
                'status'          => 'draft',
                'issue_date'      => $data['issue_date'],
                'due_date'        => $data['due_date'],
                'notes'           => $data['notes'] ?? null,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'payable_account_id' => $this->defaultPayableAccount($company)?->id,
                'created_by'      => auth()->id(),
            ]);

            $this->syncItems($bill, $data['items'] ?? []);
            $bill->recalculate();

            return $bill->fresh();
        });
    }

    public function update(Bill $bill, array $data): Bill
    {
        abort_unless($bill->status === 'draft', 422, 'Only draft bills can be edited.');

        return DB::transaction(function () use ($bill, $data) {
            $bill->update([
                'contact_id'      => $data['contact_id'],
                'bill_number'     => $data['bill_number'] ?? null,
                'reference'       => $data['reference'] ?? null,
                'issue_date'      => $data['issue_date'],
                'due_date'        => $data['due_date'],
                'notes'           => $data['notes'] ?? null,
                'discount_amount' => $data['discount_amount'] ?? 0,
            ]);

            $this->syncItems($bill, $data['items'] ?? []);
            $bill->recalculate();

            return $bill->fresh();
        });
    }

    public function approve(Bill $bill): Bill
    {
        abort_unless($bill->status === 'draft', 422, 'Only draft bills can be approved.');

        $bill->update([
            'status'      => 'approved',
            'approved_at' => now(),
        ]);

        if ($bill->journalEntries()->where('source', 'bill')->doesntExist()) {
            $this->createJournalEntry($bill);
        }

        return $bill;
    }

    public function void(Bill $bill): Bill
    {
        abort_unless(! in_array($bill->status, ['paid', 'void']), 422, 'Cannot void a paid or already voided bill.');

        $bill->update([
            'status'     => 'void',
            'voided_at'  => now(),
        ]);

        $original = $bill->journalEntries()->where('source', 'bill')->first();
        if ($original) {
            $this->reverseJournalEntry($original, $bill);
        }

        return $bill;
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function syncItems(Bill $bill, array $items): void
    {
        $existing = $bill->items()->pluck('id')->toArray();
        $kept     = [];

        foreach ($items as $i => $itemData) {
            $taxRate  = isset($itemData['tax_rate_id']) ? TaxRate::find($itemData['tax_rate_id']) : null;
            $gross    = ($itemData['quantity'] ?? 1) * ($itemData['unit_price'] ?? 0);
            $discount = $gross * (($itemData['discount_percent'] ?? 0) / 100);
            $subtotal = round($gross - $discount, 2);
            $tax      = $taxRate ? round($subtotal * ($taxRate->rate / 100), 2) : 0;
            $total    = $subtotal + $tax;

            $payload = [
                'account_id'       => $itemData['account_id'] ?? null,
                'tax_rate_id'      => $itemData['tax_rate_id'] ?? null,
                'description'      => $itemData['description'],
                'quantity'         => $itemData['quantity'] ?? 1,
                'unit_price'       => $itemData['unit_price'] ?? 0,
                'discount_percent' => $itemData['discount_percent'] ?? 0,
                'subtotal'         => $subtotal,
                'tax_amount'       => $tax,
                'total'            => $total,
                'sort_order'       => $i,
            ];

            if (! empty($itemData['id']) && in_array($itemData['id'], $existing)) {
                BillItem::find($itemData['id'])->update($payload);
                $kept[] = $itemData['id'];
            } else {
                $kept[] = $bill->items()->create($payload)->id;
            }
        }

        BillItem::whereIn('id', array_diff($existing, $kept))->delete();
    }

    private function createJournalEntry(Bill $bill): void
    {
        $company = $bill->company;
        $seq     = $company->journalEntries()->count() + 1;

        $entry = JournalEntry::create([
            'company_id'      => $company->id,
            'entry_number'    => 'JNL-' . str_pad($seq, 4, '0', STR_PAD_LEFT),
            'entry_date'      => $bill->issue_date,
            'description'     => "Bill {$bill->bill_number} — {$bill->contact->name}",
            'status'          => 'posted',
            'source'          => 'bill',
            'sourceable_type' => Bill::class,
            'sourceable_id'   => $bill->id,
            'created_by'      => auth()->id(),
            'posted_at'       => now(),
        ]);

        $lines = [];
        $sort  = 0;

        // DR Expense accounts per line item
        $expenseByAccount = $bill->items()
            ->whereNotNull('account_id')
            ->selectRaw('account_id, SUM(subtotal) as total')
            ->groupBy('account_id')
            ->get();

        foreach ($expenseByAccount as $row) {
            $lines[] = [
                'journal_entry_id' => $entry->id,
                'account_id'       => $row->account_id,
                'description'      => "Expense — Bill {$bill->bill_number}",
                'debit'            => $row->total,
                'credit'           => 0,
                'contact_id'       => $bill->contact_id,
                'sort_order'       => $sort++,
                'created_at'       => now(), 'updated_at' => now(),
            ];
        }

        // DR VAT Receivable (Input VAT account 1500)
        if ($bill->tax_amount > 0) {
            $vatAccount = Account::where('company_id', $company->id)->where('code', '1500')->first();
            if ($vatAccount) {
                $lines[] = [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $vatAccount->id,
                    'description'      => "Input VAT — Bill {$bill->bill_number}",
                    'debit'            => $bill->tax_amount,
                    'credit'           => 0,
                    'contact_id'       => null,
                    'sort_order'       => $sort++,
                    'created_at'       => now(), 'updated_at' => now(),
                ];
            }
        }

        // CR Accounts Payable (account 2000)
        if ($bill->payable_account_id) {
            $lines[] = [
                'journal_entry_id' => $entry->id,
                'account_id'       => $bill->payable_account_id,
                'description'      => "Payable — Bill {$bill->bill_number}",
                'debit'            => 0,
                'credit'           => $bill->total,
                'contact_id'       => $bill->contact_id,
                'sort_order'       => $sort++,
                'created_at'       => now(), 'updated_at' => now(),
            ];
        }

        \App\Models\JournalLine::insert($lines);
    }

    private function reverseJournalEntry(JournalEntry $original, Bill $bill): void
    {
        $company = $bill->company;
        $seq     = $company->journalEntries()->count() + 1;

        $reversal = JournalEntry::create([
            'company_id'      => $company->id,
            'entry_number'    => 'JNL-' . str_pad($seq, 4, '0', STR_PAD_LEFT),
            'entry_date'      => now()->toDateString(),
            'description'     => "Void reversal — Bill {$bill->bill_number}",
            'status'          => 'posted',
            'source'          => 'bill',
            'sourceable_type' => Bill::class,
            'sourceable_id'   => $bill->id,
            'created_by'      => auth()->id(),
            'posted_at'       => now(),
        ]);

        $lines = $original->lines->map(fn ($l) => [
            'journal_entry_id' => $reversal->id,
            'account_id'       => $l->account_id,
            'description'      => $l->description . ' (reversal)',
            'debit'            => $l->credit,
            'credit'           => $l->debit,
            'contact_id'       => $l->contact_id,
            'sort_order'       => $l->sort_order,
            'created_at'       => now(), 'updated_at' => now(),
        ])->toArray();

        \App\Models\JournalLine::insert($lines);
    }

    private function defaultPayableAccount(Company $company): ?Account
    {
        return Account::where('company_id', $company->id)->where('code', '2000')->first();
    }
}

<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function record(Company $company, array $data): Payment
    {
        return DB::transaction(function () use ($company, $data) {
            $payment = $company->payments()->create([
                'contact_id'             => $data['contact_id'] ?? null,
                'type'                   => $data['type'],
                'payment_number'         => $this->nextPaymentNumber($company, $data['type']),
                'payment_date'           => $data['payment_date'],
                'amount'                 => $data['amount'],
                'withholding_tax_amount' => $data['withholding_tax_amount'] ?? 0,
                'method'                 => $data['method'],
                'reference'              => $data['reference'] ?? null,
                'deposit_account_id'     => $data['deposit_account_id'],
                'notes'                  => $data['notes'] ?? null,
                'created_by'             => auth()->id(),
            ]);

            // Apply allocations
            foreach ($data['allocations'] ?? [] as $alloc) {
                if (empty($alloc['amount']) || $alloc['amount'] <= 0) {
                    continue;
                }

                PaymentAllocation::create([
                    'payment_id'       => $payment->id,
                    'allocatable_type' => $alloc['type'] === 'invoice' ? Invoice::class : Bill::class,
                    'allocatable_id'   => $alloc['id'],
                    'amount'           => $alloc['amount'],
                ]);

                $this->updateDocumentStatus($alloc['type'], $alloc['id'], $alloc['amount']);
            }

            $this->createJournalEntry($payment, $company);

            return $payment;
        });
    }

    public function destroy(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            // Reverse allocations
            foreach ($payment->allocations as $alloc) {
                $this->reverseDocumentAllocation($alloc);
            }

            // Reverse journal entry
            $original = $payment->journalEntries()->where('source', 'payment')->first();
            if ($original) {
                $this->reverseJournalEntry($original, $payment);
            }

            $payment->allocations()->delete();
            $payment->delete();
        });
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function updateDocumentStatus(string $type, int $id, float $amount): void
    {
        if ($type === 'invoice') {
            $doc = Invoice::find($id);
        } else {
            $doc = Bill::find($id);
        }

        if (! $doc) {
            return;
        }

        $doc->amount_paid = $doc->amount_paid + $amount;
        $doc->amount_due  = max(0, $doc->total - $doc->amount_paid - $doc->withholding_tax_amount);

        if ($doc->amount_due <= 0) {
            $doc->status = 'paid';
        } elseif ($doc->amount_paid > 0) {
            $doc->status = 'partial';
        }

        $doc->save();
    }

    private function reverseDocumentAllocation(PaymentAllocation $alloc): void
    {
        $doc = $alloc->allocatable;
        if (! $doc) {
            return;
        }

        $doc->amount_paid = max(0, $doc->amount_paid - $alloc->amount);
        $doc->amount_due  = $doc->total - $doc->amount_paid - $doc->withholding_tax_amount;
        $doc->status      = $doc->amount_paid > 0 ? 'partial' : ($doc instanceof Invoice ? 'sent' : 'approved');
        $doc->save();
    }

    private function createJournalEntry(Payment $payment, Company $company): void
    {
        $seq   = $company->journalEntries()->count() + 1;
        $label = $payment->type === 'receipt' ? 'Receipt' : 'Payment';

        $entry = JournalEntry::create([
            'company_id'      => $company->id,
            'entry_number'    => 'JNL-' . str_pad($seq, 4, '0', STR_PAD_LEFT),
            'entry_date'      => $payment->payment_date,
            'description'     => "{$label} {$payment->payment_number}"
                . ($payment->contact ? " — {$payment->contact->name}" : ''),
            'status'          => 'posted',
            'source'          => 'payment',
            'sourceable_type' => Payment::class,
            'sourceable_id'   => $payment->id,
            'created_by'      => auth()->id(),
            'posted_at'       => now(),
        ]);

        $lines = [];

        if ($payment->type === 'receipt') {
            // DR Bank/Cash (deposit account)
            $lines[] = ['debit' => $payment->amount, 'credit' => 0, 'account_id' => $payment->deposit_account_id, 'sort_order' => 0];
            // CR Accounts Receivable (1200)
            $arAccount = Account::where('company_id', $company->id)->where('code', '1200')->first();
            if ($arAccount) {
                $lines[] = ['debit' => 0, 'credit' => $payment->amount, 'account_id' => $arAccount->id, 'sort_order' => 1];
            }
        } else {
            // DR Accounts Payable (2000)
            $apAccount = Account::where('company_id', $company->id)->where('code', '2000')->first();
            if ($apAccount) {
                $lines[] = ['debit' => $payment->amount, 'credit' => 0, 'account_id' => $apAccount->id, 'sort_order' => 0];
            }
            // CR Bank/Cash (deposit account)
            $lines[] = ['debit' => 0, 'credit' => $payment->amount, 'account_id' => $payment->deposit_account_id, 'sort_order' => 1];
        }

        // WHT line if applicable
        if ($payment->withholding_tax_amount > 0) {
            $whtAccount = Account::where('company_id', $company->id)->where('code', '2200')->first();
            if ($whtAccount) {
                $lines[] = ['debit' => 0, 'credit' => $payment->withholding_tax_amount, 'account_id' => $whtAccount->id, 'sort_order' => 2];
            }
        }

        $rows = array_map(fn ($l) => array_merge($l, [
            'journal_entry_id' => $entry->id,
            'description'      => $entry->description,
            'contact_id'       => $payment->contact_id,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]), $lines);

        \App\Models\JournalLine::insert($rows);
    }

    private function reverseJournalEntry(JournalEntry $original, Payment $payment): void
    {
        $company = $payment->company;
        $seq     = $company->journalEntries()->count() + 1;

        $reversal = JournalEntry::create([
            'company_id'      => $company->id,
            'entry_number'    => 'JNL-' . str_pad($seq, 4, '0', STR_PAD_LEFT),
            'entry_date'      => now()->toDateString(),
            'description'     => "Reversal — {$original->description}",
            'status'          => 'posted',
            'source'          => 'payment',
            'sourceable_type' => Payment::class,
            'sourceable_id'   => $payment->id,
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

    private function nextPaymentNumber(Company $company, string $type): string
    {
        $prefix = $type === 'receipt' ? 'REC' : 'PAY';
        $count  = $company->payments()->where('type', $type)->count() + 1;
        return $prefix . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}

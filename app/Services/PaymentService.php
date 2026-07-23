<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Support\Facades\DB;
use App\Services\InvoiceService;

class PaymentService
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

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

            // Apply allocations. Withholding tax also settles the document, so it
            // is spread across the allocations in proportion to their amounts —
            // otherwise the ledger (which grosses the receivable up by the WHT)
            // and the document's amount_due would disagree.
            $valid        = collect($data['allocations'] ?? [])->filter(fn ($a) => ! empty($a['amount']) && $a['amount'] > 0)->values();
            $allocTotal   = (float) $valid->sum('amount');
            $paymentWht   = (float) ($data['withholding_tax_amount'] ?? 0);
            $allocatedWht = 0.0;
            $hasAllocations = false;

            foreach ($valid as $i => $alloc) {
                // Ensure invoice has its AR journal posted before we credit AR in the payment journal
                if ($alloc['type'] === 'invoice') {
                    $inv = Invoice::find($alloc['id']);
                    if ($inv) {
                        $this->invoiceService->ensureJournalEntry($inv);
                    }
                }

                PaymentAllocation::create([
                    'payment_id'       => $payment->id,
                    'allocatable_type' => $alloc['type'] === 'invoice' ? Invoice::class : Bill::class,
                    'allocatable_id'   => $alloc['id'],
                    'amount'           => $alloc['amount'],
                ]);

                // Give the last allocation the rounding remainder so shares sum to the WHT exactly.
                $whtShare = $allocTotal > 0
                    ? ($i === $valid->count() - 1
                        ? round($paymentWht - $allocatedWht, 2)
                        : round($paymentWht * $alloc['amount'] / $allocTotal, 2))
                    : 0.0;
                $allocatedWht += $whtShare;

                $this->updateDocumentStatus($alloc['type'], $alloc['id'], (float) $alloc['amount'], $whtShare);
                $hasAllocations = true;
            }

            $this->createJournalEntry($payment, $company, $hasAllocations);

            return $payment;
        });
    }

    /**
     * Add allocations to an existing payment that was recorded without allocations.
     * Reverses the Customer Deposits credit and posts to AR, then marks invoices paid.
     */
    public function allocate(Payment $payment, array $allocations): void
    {
        DB::transaction(function () use ($payment, $allocations) {
            $company   = $payment->company;
            $allocated = 0;

            foreach ($allocations as $alloc) {
                if (empty($alloc['amount']) || $alloc['amount'] <= 0) {
                    continue;
                }

                $available = $payment->unallocated_amount;
                $amount    = min((float) $alloc['amount'], $available);

                if ($amount <= 0) {
                    break;
                }

                // Ensure invoice has its AR journal before we move Customer Deposits → AR
                if ($alloc['type'] === 'invoice') {
                    $inv = Invoice::find($alloc['id']);
                    if ($inv) {
                        $this->invoiceService->ensureJournalEntry($inv);
                    }
                }

                PaymentAllocation::create([
                    'payment_id'       => $payment->id,
                    'allocatable_type' => $alloc['type'] === 'invoice' ? Invoice::class : Bill::class,
                    'allocatable_id'   => $alloc['id'],
                    'amount'           => $amount,
                ]);

                $this->updateDocumentStatus($alloc['type'], $alloc['id'], $amount);
                $allocated += $amount;
            }

            if ($allocated <= 0) {
                return;
            }

            // Post adjustment journal: DR Customer Deposits, CR AR (for receipts)
            //                          DR AP, CR Customer Deposits (for payments — supplier advance)
            $this->createAllocationAdjustmentEntry($payment, $company, $allocated);
        });
    }

    public function destroy(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            // Reverse allocations
            foreach ($payment->allocations as $alloc) {
                $this->reverseDocumentAllocation($alloc);
            }

            // Reverse all journal entries for this payment
            foreach ($payment->journalEntries()->where('source', 'payment')->get() as $entry) {
                $this->reverseJournalEntry($entry, $payment);
            }

            $payment->allocations()->delete();
            $payment->delete();
        });
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function updateDocumentStatus(string $type, int $id, float $amount, float $whtShare = 0.0): void
    {
        $doc = $type === 'invoice' ? Invoice::find($id) : Bill::find($id);

        if (! $doc) {
            return;
        }

        // WHT withheld on this settlement also reduces what is owed — accumulate it
        // on the document so amount_due matches the grossed-up receivable posting.
        $doc->withholding_tax_amount = (float) $doc->withholding_tax_amount + $whtShare;
        $doc->amount_paid = $doc->amount_paid + $amount;
        $doc->amount_due  = max(0, $doc->total - $doc->amount_paid - $doc->withholding_tax_amount);

        // Never move a voided document back into a live status.
        if ($doc->status !== 'void') {
            if ($doc->amount_due <= 0) {
                $doc->status = 'paid';
            } elseif ($doc->amount_paid > 0) {
                $doc->status = 'partial';
            }
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

        // A voided document stays voided. Recomputing its status from amount_paid
        // would resurrect it to 'sent', letting it be voided a second time and
        // posting a duplicate reversal that double-credits receivables.
        if ($doc->status !== 'void') {
            $doc->status = $doc->amount_paid > 0 ? 'partial' : ($doc instanceof Invoice ? 'sent' : 'approved');
        }

        $doc->save();
    }

    private function createJournalEntry(Payment $payment, Company $company, bool $hasAllocations): void
    {
        $label = $payment->type === 'receipt' ? 'Receipt' : 'Payment';

        $entry = JournalEntry::create([
            'company_id'      => $company->id,
            'entry_number'    => $company->nextJournalEntryNumber(),
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

        // Withholding tax differs by direction:
        //  - receipt: the customer withheld tax from what they paid us, so it is a
        //    WHT RECEIVABLE (asset, code 1600) we can offset against income tax.
        //  - payment: we withheld tax from the supplier, so it is a WHT PAYABLE
        //    (liability, code 2200) we owe ZRA.
        // Either way the settled document (AR/AP) is the gross — cash plus WHT —
        // so the WHT line has a real offset and the entry always balances.
        $cash  = (float) $payment->amount;
        $wht   = (float) $payment->withholding_tax_amount;
        $whtId = $wht > 0
            ? Account::where('company_id', $company->id)
                ->where('code', $payment->type === 'receipt' ? '1600' : '2200')->value('id')
            : null;
        $wht     = $whtId ? $wht : 0.0; // only gross up if the WHT account exists
        $settled = round($cash + $wht, 2);

        if ($payment->type === 'receipt') {
            // DR Bank/Cash (net received)
            $lines[] = ['debit' => $cash, 'credit' => 0, 'account_id' => $payment->deposit_account_id, 'sort_order' => 0];
            // DR WHT Receivable (tax the customer withheld)
            if ($wht > 0) {
                $lines[] = ['debit' => $wht, 'credit' => 0, 'account_id' => $whtId, 'sort_order' => 1];
            }
            // CR the settled document: AR when allocated, else Customer Deposits (2050)
            $creditId = Account::where('company_id', $company->id)
                ->where('code', $hasAllocations ? '1200' : '2050')->value('id');
            if ($creditId) {
                $lines[] = ['debit' => 0, 'credit' => $settled, 'account_id' => $creditId, 'sort_order' => 2];
            }
        } else {
            // DR the settled document: Accounts Payable (2000), allocated or advance
            $apId = Account::where('company_id', $company->id)->where('code', '2000')->value('id');
            if ($apId) {
                $lines[] = ['debit' => $settled, 'credit' => 0, 'account_id' => $apId, 'sort_order' => 0];
            }
            // CR Bank/Cash (net paid)
            $lines[] = ['debit' => 0, 'credit' => $cash, 'account_id' => $payment->deposit_account_id, 'sort_order' => 1];
            // CR WHT Payable (tax we withheld from the supplier)
            if ($wht > 0) {
                $lines[] = ['debit' => 0, 'credit' => $wht, 'account_id' => $whtId, 'sort_order' => 2];
            }
        }

        $rows = array_map(fn ($l) => array_merge($l, [
            'journal_entry_id' => $entry->id,
            'description'      => $entry->description,
            'contact_id'       => $payment->contact_id,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]), $lines);

        JournalLine::insert($rows);
    }

    /**
     * When allocating after the fact, move the amount from Customer Deposits → AR.
     * Receipt: DR Customer Deposits (2050), CR AR (1200)
     * Payment: DR AP (2000), CR Supplier Advances — currently both use AP so no-op here; extend if needed.
     */
    private function createAllocationAdjustmentEntry(Payment $payment, Company $company, float $amount): void
    {
        if ($payment->type !== 'receipt') {
            return; // AP payments post correctly already
        }


        $entry = JournalEntry::create([
            'company_id'      => $company->id,
            'entry_number'    => $company->nextJournalEntryNumber(),
            'entry_date'      => now()->toDateString(),
            'description'     => "Allocation — {$payment->payment_number}"
                . ($payment->contact ? " — {$payment->contact->name}" : ''),
            'status'          => 'posted',
            'source'          => 'payment',
            'sourceable_type' => Payment::class,
            'sourceable_id'   => $payment->id,
            'created_by'      => auth()->id(),
            'posted_at'       => now(),
        ]);

        $cdAccount = Account::where('company_id', $company->id)->where('code', '2050')->first();
        $arAccount = Account::where('company_id', $company->id)->where('code', '1200')->first();

        $lines = [];
        if ($cdAccount) {
            $lines[] = ['debit' => $amount, 'credit' => 0, 'account_id' => $cdAccount->id, 'sort_order' => 0]; // DR Customer Deposits
        }
        if ($arAccount) {
            $lines[] = ['debit' => 0, 'credit' => $amount, 'account_id' => $arAccount->id, 'sort_order' => 1]; // CR AR
        }

        $rows = array_map(fn ($l) => array_merge($l, [
            'journal_entry_id' => $entry->id,
            'description'      => $entry->description,
            'contact_id'       => $payment->contact_id,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]), $lines);

        JournalLine::insert($rows);
    }

    private function reverseJournalEntry(JournalEntry $original, Payment $payment): void
    {
        $company = $payment->company;

        $reversal = JournalEntry::create([
            'company_id'      => $company->id,
            'entry_number'    => $company->nextJournalEntryNumber(),
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
            'created_at'       => now(),
            'updated_at'       => now(),
        ])->toArray();

        JournalLine::insert($lines);
    }

    private function nextPaymentNumber(Company $company, string $type): string
    {
        $prefix = $type === 'receipt' ? 'REC' : 'PAY';
        $count  = $company->payments()->where('type', $type)->count() + 1;
        return $prefix . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}

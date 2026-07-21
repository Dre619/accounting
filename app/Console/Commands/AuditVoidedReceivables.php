<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Finds invoices that were voided while carrying an allocated payment.
 *
 * Voiding reversed the full invoice, but the payment's credit to accounts
 * receivable was left standing — so AR is understated (negative) by the amount
 * received. The money is a genuine customer credit, so the correction moves it
 * from receivables to Customer Deposits rather than making it disappear.
 *
 * Reports by default; only writes when --fix is passed.
 */
class AuditVoidedReceivables extends Command
{
    protected $signature = 'invoices:audit-voided-receivables
                            {--fix : Post correcting journal entries}
                            {--company= : Limit to one company id}';

    protected $description = 'Report (and optionally correct) receivables left behind by voided part-paid invoices';

    public function handle(): int
    {
        $invoices = Invoice::query()
            ->where('status', 'void')
            ->where('amount_paid', '>', 0)
            ->when($this->option('company'), fn ($q, $id) => $q->where('company_id', $id))
            ->with('company:id,name', 'contact:id,name')
            ->orderBy('company_id')
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('No voided invoices with allocated payments found. Nothing to correct.');

            return self::SUCCESS;
        }

        $this->warn("Found {$invoices->count()} voided invoice(s) with payments still allocated:");
        $this->table(
            ['Company', 'Invoice', 'Contact', 'Paid (residual AR)', 'Corrected?'],
            $invoices->map(fn (Invoice $i) => [
                $i->company?->name ?? $i->company_id,
                $i->invoice_number,
                $i->contact?->name ?? '—',
                number_format((float) $i->amount_paid, 2),
                $this->correctionFor($i) ? 'yes' : 'no',
            ])->all()
        );

        $total = $invoices->sum(fn (Invoice $i) => (float) $i->amount_paid);
        $this->line('Total receivable understatement: ' . number_format($total, 2));

        if (! $this->option('fix')) {
            $this->newLine();
            $this->comment('Dry run. Re-run with --fix to post correcting entries (DR Accounts Receivable / CR Customer Deposits).');

            return self::SUCCESS;
        }

        if (! $this->confirm('Post correcting journal entries for the invoices listed above?', false)) {
            $this->info('Aborted. Nothing was written.');

            return self::SUCCESS;
        }

        $posted = 0;
        foreach ($invoices as $invoice) {
            if ($this->correctionFor($invoice)) {
                continue; // already corrected
            }
            if ($this->correct($invoice)) {
                $posted++;
            }
        }

        $this->info("Posted {$posted} correcting entr" . ($posted === 1 ? 'y' : 'ies') . '.');

        return self::SUCCESS;
    }

    private function correct(Invoice $invoice): bool
    {
        $company     = $invoice->company;
        $receivable  = $invoice->receivable_account_id ?? $this->accountId($invoice->company_id, '1200');
        $deposits    = $this->accountId($invoice->company_id, '2050');
        $amount      = round((float) $invoice->amount_paid, 2);

        if (! $receivable || ! $deposits) {
            $this->error("Skipped {$invoice->invoice_number}: missing account 1200 or 2050.");

            return false;
        }

        DB::transaction(function () use ($company, $invoice, $receivable, $deposits, $amount) {

            $entry = JournalEntry::create([
                'company_id'      => $invoice->company_id,
                'entry_number'    => $company->nextJournalEntryNumber(),
                'entry_date'      => now()->toDateString(),
                'description'     => $this->correctionDescription($invoice),
                'status'          => 'posted',
                'source'          => 'manual',
                'sourceable_type' => Invoice::class,
                'sourceable_id'   => $invoice->id,
                'posted_at'       => now(),
            ]);

            JournalLine::insert([
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $receivable,
                    'description'      => 'Clear residual receivable on voided invoice',
                    'debit'            => $amount,
                    'credit'           => 0,
                    'contact_id'       => $invoice->contact_id,
                    'sort_order'       => 0,
                    'created_at'       => now(), 'updated_at' => now(),
                ],
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $deposits,
                    'description'      => 'Customer credit from voided invoice',
                    'debit'            => 0,
                    'credit'           => $amount,
                    'contact_id'       => $invoice->contact_id,
                    'sort_order'       => 1,
                    'created_at'       => now(), 'updated_at' => now(),
                ],
            ]);
        });

        return true;
    }

    private function correctionDescription(Invoice $invoice): string
    {
        return "Void correction — {$invoice->invoice_number} residual receivable to customer deposits";
    }

    private function correctionFor(Invoice $invoice): ?JournalEntry
    {
        return JournalEntry::where('company_id', $invoice->company_id)
            ->where('description', $this->correctionDescription($invoice))
            ->first();
    }

    private function accountId(int $companyId, string $code): ?int
    {
        return Account::where('company_id', $companyId)->where('code', $code)->value('id');
    }
}

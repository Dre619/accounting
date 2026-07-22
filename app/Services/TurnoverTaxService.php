<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\TaxRate;
use Illuminate\Support\Facades\DB;

/**
 * Turnover Tax is a final tax on gross turnover, charged in lieu of income tax
 * for businesses under the ZRA threshold. It is a period computation (monthly),
 * not a per-line tax like VAT.
 *
 * The rate is read from the company record — never hard-coded — because Zambian
 * TOT rates and thresholds are reset by each annual Finance Act.
 */
class TurnoverTaxService
{
    /**
     * Turnover and tax due for a period.
     *
     * Turnover is taken from the INVOICES ISSUED in the period, excluding voided
     * ones — not from journal entry dates. Voiding a prior-period sale posts its
     * reversal on the day of the void, so a ledger-date basis would both overstate
     * the original month and push the current month negative. Turnover tax is a
     * tax on gross turnover and cannot be negative: a cancelled sale belongs to
     * the month it was raised, and is handled by amending that month's return.
     */
    public function compute(Company $company, string $from, string $to): array
    {
        $turnover = (float) DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('accounts', 'accounts.id', '=', 'invoice_items.account_id')
            ->where('invoices.company_id', $company->id)
            ->whereNotIn('invoices.status', ['void', 'draft'])
            ->where('accounts.type', 'income')
            ->where('accounts.subtype', 'operating_income')
            ->whereDate('invoices.issue_date', '>=', $from)
            ->whereDate('invoices.issue_date', '<=', $to)
            ->whereNull('invoices.deleted_at')
            ->sum('invoice_items.subtotal');

        $turnover   = round($turnover, 2);
        $resolution = $this->resolveRate($company, $from, $to);
        $rate       = $resolution['rate']?->rate !== null ? (float) $resolution['rate']->rate : null;
        $tax        = $rate !== null ? round($turnover * $rate / 100, 2) : null;

        // If the period was already posted, surface any drift so an amendment
        // can be filed — typically caused by voiding a sale after filing.
        $entry     = $this->postedEntry($company, $from, $to);
        $postedTax = $entry ? round((float) $entry->lines->sum('debit'), 2) : null;

        return [
            'turnover'   => $turnover,
            'rate'       => $rate,
            'rate_name'  => $resolution['rate']?->name,
            'rate_error' => $resolution['error'],
            'tax'        => $tax,
            'from'       => $from,
            'to'         => $to,
            'posted'     => $entry !== null,
            'posted_tax' => $postedTax,
            'amended'    => $postedTax !== null && $tax !== null && abs($postedTax - $tax) > 0.005,
        ];
    }

    /**
     * Find the single turnover tax rate covering the whole period.
     *
     * Returns an 'ambiguous' error when the period straddles a rate change
     * rather than silently picking one — the correct action is to file separate
     * returns either side of the change.
     */
    public function resolveRate(Company $company, string $from, string $to): array
    {
        $candidates = TaxRate::where('company_id', $company->id)
            ->turnover()->active()
            ->overlapping($from, $to)
            ->orderByRaw('effective_from is null desc, effective_from asc')
            ->get();

        return match ($candidates->count()) {
            0       => ['rate' => null, 'error' => 'none',      'candidates' => $candidates],
            1       => ['rate' => $candidates->first(), 'error' => null, 'candidates' => $candidates],
            default => ['rate' => null, 'error' => 'ambiguous', 'candidates' => $candidates],
        };
    }

    /**
     * Post the period's turnover tax: DR Turnover Tax Expense / CR TOT Payable.
     */
    public function post(Company $company, string $from, string $to): JournalEntry
    {
        abort_unless($company->isOnTurnoverTax(), 422, 'This company is not on the turnover tax regime.');
        abort_if($this->postedEntry($company, $from, $to) !== null, 422, 'Turnover tax for this period is already posted.');

        $computed = $this->compute($company, $from, $to);

        abort_if($computed['rate_error'] === 'none', 422, 'No turnover tax rate covers this period. Add one before posting.');
        abort_if(
            $computed['rate_error'] === 'ambiguous',
            422,
            'This period spans a turnover tax rate change — file separate returns for each rate period.'
        );
        abort_if($computed['tax'] === null || $computed['tax'] <= 0, 422, 'There is no turnover tax to post for this period.');

        $expenseId = $this->accountId($company, '8000');
        $payableId = $this->accountId($company, '2150');
        abort_if(! $expenseId || ! $payableId, 422, 'Turnover tax accounts (8000 / 2150) are missing from the chart of accounts.');

        return DB::transaction(function () use ($company, $computed, $from, $to, $expenseId, $payableId) {

            $entry = JournalEntry::create([
                'company_id'   => $company->id,
                'entry_number' => $company->nextJournalEntryNumber(),
                'entry_date'   => $to,
                'description'  => "Turnover tax — {$from} to {$to}",
                'status'       => 'posted',
                'source'       => 'tax',
                'created_by'   => auth()->id(),
                'posted_at'    => now(),
            ]);

            JournalLine::insert([
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $expenseId,
                    'description'      => "Turnover tax {$computed['rate']}% on {$computed['turnover']}",
                    'debit'            => $computed['tax'],
                    'credit'           => 0,
                    'contact_id'       => null,
                    'sort_order'       => 0,
                    'created_at'       => now(), 'updated_at' => now(),
                ],
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $payableId,
                    'description'      => "Turnover tax payable — {$from} to {$to}",
                    'debit'            => 0,
                    'credit'           => $computed['tax'],
                    'contact_id'       => null,
                    'sort_order'       => 1,
                    'created_at'       => now(), 'updated_at' => now(),
                ],
            ]);

            return $entry;
        });
    }

    /** An already-posted turnover tax entry for exactly this period, if any. */
    private function postedEntry(Company $company, string $from, string $to): ?JournalEntry
    {
        return $company->journalEntries()
            ->where('source', 'tax')
            ->where('description', "Turnover tax — {$from} to {$to}")
            ->first();
    }

    private function accountId(Company $company, string $code): ?int
    {
        return Account::where('company_id', $company->id)->where('code', $code)->value('id');
    }
}

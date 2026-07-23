<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Standing integrity checks over a company's ledger. Catches whole classes of
 * corruption at once instead of one bug at a time:
 *
 *   ERRORS (always wrong — should be zero):
 *     - a posted journal entry whose debits ≠ credits
 *     - a company trial balance that does not balance
 *     - a control account sitting on the wrong side of zero
 *
 *   WARNINGS (investigate — usually a bug, occasionally benign):
 *     - AR control not tying to outstanding invoices
 *     - AP control not tying to outstanding bills
 *     - Inventory control not tying to stock valuation
 *
 * Each finding is ['check','severity','ok','message','expected','actual','difference'].
 */
class LedgerReconciliationService
{
    private const EPSILON = 0.01;

    /** Control accounts that must never sit on the wrong side of zero. */
    private const NON_NEGATIVE_CONTROLS = [
        '1200' => 'Accounts Receivable',
        '2000' => 'Accounts Payable',
        '1300' => 'Inventory',
        '2100' => 'VAT Payable',
        '2150' => 'Turnover Tax Payable',
        '2250' => 'Income Tax Payable',
    ];

    public function check(Company $company): Collection
    {
        return collect([
            ...$this->unbalancedEntries($company),
            $this->trialBalance($company),
            ...$this->negativeControlAccounts($company),
            $this->arSubledger($company),
            $this->apSubledger($company),
            $this->inventorySubledger($company),
        ])->filter();
    }

    /** Every posted journal entry must have equal debits and credits. */
    private function unbalancedEntries(Company $company): array
    {
        // Grouped sums fetched then compared in PHP — avoids a HAVING clause with
        // a bound parameter, which does not behave consistently across drivers.
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.company_id', $company->id)
            ->where('journal_entries.status', 'posted')
            ->whereNull('journal_entries.deleted_at')
            ->groupBy('journal_entries.id', 'journal_entries.entry_number')
            ->get([
                'journal_entries.entry_number',
                DB::raw('SUM(journal_lines.debit) as d'),
                DB::raw('SUM(journal_lines.credit) as c'),
            ])
            ->filter(fn ($row) => abs((float) $row->d - (float) $row->c) > self::EPSILON)
            ->map(fn ($row) => $this->finding(
                'entry-balanced', 'error', false,
                "Journal entry {$row->entry_number} is not balanced",
                (float) $row->d, (float) $row->c,
            ))
            ->values()
            ->all();
    }

    /** Company-wide posted debits must equal posted credits. */
    private function trialBalance(Company $company): array
    {
        $t = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.company_id', $company->id)
            ->where('journal_entries.status', 'posted')
            ->whereNull('journal_entries.deleted_at')
            ->selectRaw('COALESCE(SUM(journal_lines.debit),0) as d, COALESCE(SUM(journal_lines.credit),0) as c')
            ->first();

        return $this->finding(
            'trial-balance', 'error', abs($t->d - $t->c) <= self::EPSILON,
            'Trial balance: total debits vs credits', (float) $t->d, (float) $t->c,
        );
    }

    /** Control accounts that must not go negative. */
    private function negativeControlAccounts(Company $company): array
    {
        $findings = [];

        foreach (self::NON_NEGATIVE_CONTROLS as $code => $name) {
            $account = Account::where('company_id', $company->id)->where('code', $code)->first();
            if (! $account) {
                continue;
            }

            $balance = round((float) $account->balance, 2);
            if ($balance < -self::EPSILON) {
                $findings[] = $this->finding(
                    'control-sign', 'error', false,
                    "{$code} {$name} has a negative balance",
                    0.0, $balance,
                );
            }
        }

        return $findings;
    }

    /** AR control account should equal the sum of outstanding invoices. */
    private function arSubledger(Company $company): array
    {
        $account = Account::where('company_id', $company->id)->where('code', '1200')->first();
        if (! $account) {
            return [];
        }

        $subledger = round((float) $company->invoices()
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->sum('amount_due'), 2);

        $balance = round((float) $account->balance, 2);

        return $this->finding(
            'ar-subledger', 'warning', abs($balance - $subledger) <= self::EPSILON,
            'Accounts Receivable control vs outstanding invoices', $subledger, $balance,
        );
    }

    /** AP control account should equal the sum of outstanding bills. */
    private function apSubledger(Company $company): array
    {
        $account = Account::where('company_id', $company->id)->where('code', '2000')->first();
        if (! $account) {
            return [];
        }

        $subledger = round((float) $company->bills()
            ->whereIn('status', ['approved', 'partial', 'overdue'])
            ->sum('amount_due'), 2);

        $balance = round((float) $account->balance, 2);

        return $this->finding(
            'ap-subledger', 'warning', abs($balance - $subledger) <= self::EPSILON,
            'Accounts Payable control vs outstanding bills', $subledger, $balance,
        );
    }

    /** Inventory control account should equal stock on hand at average cost. */
    private function inventorySubledger(Company $company): array
    {
        $account = Account::where('company_id', $company->id)->where('code', '1300')->first();
        if (! $account) {
            return [];
        }

        $valuation = round((float) $company->products()
            ->where('type', 'inventory')
            ->get()
            ->sum(fn ($p) => (float) $p->quantity_on_hand * (float) $p->average_cost), 2);

        $balance = round((float) $account->balance, 2);

        return $this->finding(
            'inventory-subledger', 'warning', abs($balance - $valuation) <= self::EPSILON,
            'Inventory control vs stock valuation', $valuation, $balance,
        );
    }

    private function finding(string $check, string $severity, bool $ok, string $message, float $expected, float $actual): array
    {
        return [
            'check'      => $check,
            'severity'   => $severity,
            'ok'         => $ok,
            'message'    => $message,
            'expected'   => round($expected, 2),
            'actual'     => round($actual, 2),
            'difference' => round($actual - $expected, 2),
        ];
    }
}

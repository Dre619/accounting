<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\LedgerReconciliationService;
use Illuminate\Console\Command;

/**
 * Runs the ledger integrity checks across companies. Exits non-zero if any
 * ERROR-level finding exists, so it can gate a deploy or fire a cron alert.
 */
class ReconcileLedger extends Command
{
    protected $signature = 'ledger:reconcile
                            {--company= : Limit to one company id}
                            {--errors-only : Hide passing checks and warnings}';

    protected $description = 'Check ledger integrity: trial balance, control-account signs, and subledger ties';

    public function handle(LedgerReconciliationService $service): int
    {
        $companies = Company::query()
            ->when($this->option('company'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('id')
            ->get();

        if ($companies->isEmpty()) {
            $this->warn('No companies to check.');

            return self::SUCCESS;
        }

        $totalErrors = 0;
        $totalWarnings = 0;

        foreach ($companies as $company) {
            $findings = $service->check($company);
            $errors   = $findings->where('severity', 'error')->where('ok', false);
            $warnings = $findings->where('severity', 'warning')->where('ok', false);
            $totalErrors += $errors->count();
            $totalWarnings += $warnings->count();

            $show = $this->option('errors-only')
                ? $errors
                : $findings->reject(fn ($f) => $f['ok'] && $this->option('errors-only'));

            if ($this->option('errors-only') && $errors->isEmpty() && $warnings->isEmpty()) {
                continue;
            }

            $this->newLine();
            $this->line("<options=bold>Company {$company->id} — {$company->name}</>");

            $this->table(
                ['Check', 'Result', 'Expected', 'Actual', 'Diff', 'Detail'],
                $findings->map(fn ($f) => [
                    $f['check'],
                    $f['ok'] ? '<fg=green>ok</>' : ($f['severity'] === 'error' ? '<fg=red>ERROR</>' : '<fg=yellow>warn</>'),
                    number_format($f['expected'], 2),
                    number_format($f['actual'], 2),
                    number_format($f['difference'], 2),
                    $f['message'],
                ])->all()
            );
        }

        $this->newLine();
        if ($totalErrors > 0) {
            $this->error("Reconciliation found {$totalErrors} error(s) and {$totalWarnings} warning(s).");

            return self::FAILURE;
        }

        if ($totalWarnings > 0) {
            $this->warn("Reconciliation clean of errors, with {$totalWarnings} warning(s) to review.");

            return self::SUCCESS;
        }

        $this->info('Ledger reconciled — all checks passed.');

        return self::SUCCESS;
    }
}

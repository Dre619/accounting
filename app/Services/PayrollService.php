<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\PayrollRun;

class PayrollService
{
    // ── Zambia statutory rates ────────────────────────────────────────────────

    /** Monthly PAYE bands: [ceiling, rate] */
    private const PAYE_BANDS = [
        [4_800.00,  0.000],
        [6_900.00,  0.200],
        [9_200.00,  0.300],
        [PHP_INT_MAX, 0.375],
    ];

    private const NAPSA_RATE            = 0.05;   // 5% employee & employer each
    private const NAPSA_MONTHLY_CEILING = 1_221.80; // per party (ZRA 2024/25)

    private const NHIMA_RATE = 0.01;              // 1% employee & employer each

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Calculate all statutory deductions for a given gross monthly salary.
     */
    public function calculateDeductions(float $gross): array
    {
        $paye           = $this->calcPaye($gross);
        $napsaEmployee  = min(round($gross * self::NAPSA_RATE, 2), self::NAPSA_MONTHLY_CEILING);
        $napsaEmployer  = $napsaEmployee; // symmetric
        $nhimaEmployee  = round($gross * self::NHIMA_RATE, 2);
        $nhimaEmployer  = $nhimaEmployee;
        $totalDeductions = $paye + $napsaEmployee + $nhimaEmployee;
        $netSalary       = $gross - $totalDeductions;

        return [
            'paye'             => $paye,
            'napsa_employee'   => $napsaEmployee,
            'napsa_employer'   => $napsaEmployer,
            'nhima_employee'   => $nhimaEmployee,
            'nhima_employer'   => $nhimaEmployer,
            'other_deductions' => 0.00,
            'total_deductions' => $totalDeductions,
            'net_salary'       => max(0, $netSalary),
        ];
    }

    /**
     * Generate/refresh payslips for all active employees and update run totals.
     * Safe to call multiple times — uses updateOrCreate.
     */
    public function process(PayrollRun $run): void
    {
        $employees = $run->company->employees()->active()->get();

        foreach ($employees as $employee) {
            $gross       = (float) $employee->basic_salary;
            $deductions  = $this->calculateDeductions($gross);

            $run->payslips()->updateOrCreate(
                ['employee_id' => $employee->id],
                array_merge(['basic_salary' => $gross, 'gross_salary' => $gross], $deductions)
            );
        }

        $this->recalcTotals($run);
    }

    /**
     * Approve the payroll run and post the accounting journal entry.
     */
    public function approve(PayrollRun $run, int $userId): void
    {
        abort_unless($run->status === 'draft', 422, 'This payroll run is already approved.');

        $run->update([
            'status'      => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        $this->postJournal($run);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function calcPaye(float $gross): float
    {
        $paye = 0.0;
        $prev = 0.0;

        foreach (self::PAYE_BANDS as [$cap, $rate]) {
            if ($gross <= $prev) break;
            $paye += (min($gross, $cap) - $prev) * $rate;
            $prev  = $cap;
            if ($gross <= $cap) break;
        }

        return round($paye, 2);
    }

    private function recalcTotals(PayrollRun $run): void
    {
        $run->refresh();
        $payslips = $run->payslips;

        $run->update([
            'total_gross'          => $payslips->sum('gross_salary'),
            'total_paye'           => $payslips->sum('paye'),
            'total_napsa_employee' => $payslips->sum('napsa_employee'),
            'total_napsa_employer' => $payslips->sum('napsa_employer'),
            'total_nhima_employee' => $payslips->sum('nhima_employee'),
            'total_nhima_employer' => $payslips->sum('nhima_employer'),
            'total_net'            => $payslips->sum('net_salary'),
        ]);
    }

    private function postJournal(PayrollRun $run): void
    {
        $company = $run->company;
        $seq     = $company->journalEntries()->count() + 1;

        $entry = JournalEntry::create([
            'company_id'      => $company->id,
            'entry_number'    => 'JNL-' . str_pad($seq, 4, '0', STR_PAD_LEFT),
            'entry_date'      => $run->period_end,
            'description'     => "Payroll — {$run->period}",
            'status'          => 'posted',
            'source'          => 'payroll',
            'sourceable_type' => PayrollRun::class,
            'sourceable_id'   => $run->id,
            'created_by'      => auth()->id(),
            'posted_at'       => now(),
        ]);

        $lines = [];
        $sort  = 0;

        $acct = fn (string $code) => Account::where('company_id', $company->id)
            ->where('code', $code)->first();

        // Total employer cost = gross + employer NAPSA + employer NHIMA
        $employerCost = (float) $run->total_gross
            + (float) $run->total_napsa_employer
            + (float) $run->total_nhima_employer;

        // DR 6000 Salaries & Wages (gross payroll cost)
        if ($a = $acct('6000')) {
            $lines[] = $this->line($entry->id, $a->id, "Salaries — {$run->period}", $employerCost - (float) $run->total_napsa_employer - (float) $run->total_nhima_employer, 0, $sort++);
        }

        // DR 6010 NAPSA Contribution (employer's share of NAPSA + NHIMA)
        if ($a = $acct('6010')) {
            $lines[] = $this->line($entry->id, $a->id, "Employer NAPSA/NHIMA — {$run->period}", (float) $run->total_napsa_employer + (float) $run->total_nhima_employer, 0, $sort++);
        }

        // CR 2300 PAYE Payable
        if ($run->total_paye > 0 && ($a = $acct('2300'))) {
            $lines[] = $this->line($entry->id, $a->id, "PAYE — {$run->period}", 0, (float) $run->total_paye, $sort++);
        }

        // CR 2400 NAPSA Payable (employee + employer)
        $totalNapsa = (float) $run->total_napsa_employee + (float) $run->total_napsa_employer;
        if ($totalNapsa > 0 && ($a = $acct('2400'))) {
            $lines[] = $this->line($entry->id, $a->id, "NAPSA — {$run->period}", 0, $totalNapsa, $sort++);
        }

        // CR 2450 NHIMA Payable (employee + employer)
        $totalNhima = (float) $run->total_nhima_employee + (float) $run->total_nhima_employer;
        if ($totalNhima > 0 && ($a = $acct('2450'))) {
            $lines[] = $this->line($entry->id, $a->id, "NHIMA — {$run->period}", 0, $totalNhima, $sort++);
        }

        // CR 2550 Salaries Payable (net)
        if ($run->total_net > 0 && ($a = $acct('2550'))) {
            $lines[] = $this->line($entry->id, $a->id, "Net Salaries — {$run->period}", 0, (float) $run->total_net, $sort++);
        }

        if ($lines) {
            JournalLine::insert($lines);
        }
    }

    private function line(int $entryId, int $accountId, string $desc, float $debit, float $credit, int $sort): array
    {
        return [
            'journal_entry_id' => $entryId,
            'account_id'       => $accountId,
            'description'      => $desc,
            'debit'            => $debit,
            'credit'           => $credit,
            'contact_id'       => null,
            'sort_order'       => $sort,
            'created_at'       => now(),
            'updated_at'       => now(),
        ];
    }
}

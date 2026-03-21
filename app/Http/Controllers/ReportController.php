<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('reports/Index');
    }

    public function profitLoss(Request $request): Response
    {
        return Inertia::render('reports/ProfitLoss', $this->profitLossData($request));
    }

    public function balanceSheet(Request $request): Response
    {
        return Inertia::render('reports/BalanceSheet', $this->balanceSheetData($request));
    }

    public function vatSummary(Request $request): Response
    {
        return Inertia::render('reports/VatSummary', $this->vatSummaryData($request));
    }

    public function agedReceivables(Request $request): Response
    {
        $company   = $request->user()->currentCompany;
        $companyId = $company->id;

        $asOf = $request->filled('as_of')
            ? Carbon::parse($request->input('as_of'))
            : now();

        $invoices = DB::table('invoices')
            ->join('contacts', 'contacts.id', '=', 'invoices.contact_id')
            ->where('invoices.company_id', $companyId)
            ->whereIn('invoices.status', ['sent', 'partial', 'overdue'])
            ->where('invoices.amount_due', '>', 0)
            ->whereNull('invoices.deleted_at')
            ->orderBy('invoices.due_date')
            ->get(['invoices.id', 'invoices.invoice_number', 'contacts.name as contact', 'invoices.due_date', 'invoices.total', 'invoices.amount_due']);

        $rows   = [];
        $totals = ['current' => 0.0, '1-30' => 0.0, '31-60' => 0.0, '61-90' => 0.0, '90+' => 0.0];

        foreach ($invoices as $inv) {
            $dueDate    = Carbon::parse($inv->due_date);
            $daysOverdue = $dueDate->lt($asOf) ? (int) $asOf->diffInDays($dueDate) : 0;
            $bucket      = $this->ageBucket($daysOverdue);

            $rows[]             = [
                'id'          => $inv->id,
                'number'      => $inv->invoice_number,
                'contact'     => $inv->contact,
                'due_date'    => $inv->due_date,
                'total'       => (float) $inv->total,
                'amount_due'  => (float) $inv->amount_due,
                'days_overdue' => $daysOverdue,
                'bucket'      => $bucket,
            ];
            $totals[$bucket] += (float) $inv->amount_due;
        }

        $totals = array_map(fn ($v) => round($v, 2), $totals);

        return Inertia::render('reports/AgedReceivables', [
            'rows'    => $rows,
            'totals'  => $totals,
            'asOf'    => $asOf->toDateString(),
            'company' => $company->only('name', 'currency'),
        ]);
    }

    public function agedPayables(Request $request): Response
    {
        $company   = $request->user()->currentCompany;
        $companyId = $company->id;

        $asOf = $request->filled('as_of')
            ? Carbon::parse($request->input('as_of'))
            : now();

        $bills = DB::table('bills')
            ->join('contacts', 'contacts.id', '=', 'bills.contact_id')
            ->where('bills.company_id', $companyId)
            ->whereIn('bills.status', ['approved', 'partial', 'overdue'])
            ->where('bills.amount_due', '>', 0)
            ->whereNull('bills.deleted_at')
            ->orderBy('bills.due_date')
            ->get(['bills.id', 'bills.bill_number', 'contacts.name as contact', 'bills.due_date', 'bills.total', 'bills.amount_due']);

        $rows   = [];
        $totals = ['current' => 0.0, '1-30' => 0.0, '31-60' => 0.0, '61-90' => 0.0, '90+' => 0.0];

        foreach ($bills as $bill) {
            $dueDate     = Carbon::parse($bill->due_date);
            $daysOverdue = $dueDate->lt($asOf) ? (int) $asOf->diffInDays($dueDate) : 0;
            $bucket      = $this->ageBucket($daysOverdue);

            $rows[]          = [
                'id'          => $bill->id,
                'number'      => $bill->bill_number ?? '—',
                'contact'     => $bill->contact,
                'due_date'    => $bill->due_date,
                'total'       => (float) $bill->total,
                'amount_due'  => (float) $bill->amount_due,
                'days_overdue' => $daysOverdue,
                'bucket'      => $bucket,
            ];
            $totals[$bucket] += (float) $bill->amount_due;
        }

        $totals = array_map(fn ($v) => round($v, 2), $totals);

        return Inertia::render('reports/AgedPayables', [
            'rows'    => $rows,
            'totals'  => $totals,
            'asOf'    => $asOf->toDateString(),
            'company' => $company->only('name', 'currency'),
        ]);
    }

    // ── Print views ──────────────────────────────────────────────────────────

    public function profitLossPrint(Request $request): \Illuminate\Contracts\View\View
    {
        $data = $this->profitLossData($request);
        return view('reports.profit-loss', $data);
    }

    public function balanceSheetPrint(Request $request): \Illuminate\Contracts\View\View
    {
        $data = $this->balanceSheetData($request);
        return view('reports.balance-sheet', $data);
    }

    public function vatSummaryPrint(Request $request): \Illuminate\Contracts\View\View
    {
        $data = $this->vatSummaryData($request);
        return view('reports.vat-summary', $data);
    }

    // ── CSV exports ───────────────────────────────────────────────────────────

    public function profitLossCsv(Request $request): StreamedResponse
    {
        $data = $this->profitLossData($request);
        $filename = "profit-loss-{$data['from']}-to-{$data['to']}.csv";

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Profit & Loss Report']);
            fputcsv($out, ['Period:', $data['from'] . ' to ' . $data['to']]);
            fputcsv($out, []);
            fputcsv($out, ['INCOME']);
            fputcsv($out, ['Code', 'Account', 'Amount (ZMW)']);
            foreach ($data['income'] as $row) {
                fputcsv($out, [$row['code'], $row['name'], number_format($row['balance'], 2, '.', '')]);
            }
            fputcsv($out, ['', 'Total Income', number_format($data['totalIncome'], 2, '.', '')]);
            fputcsv($out, []);
            fputcsv($out, ['EXPENSES']);
            fputcsv($out, ['Code', 'Account', 'Amount (ZMW)']);
            foreach ($data['expenses'] as $row) {
                fputcsv($out, [$row['code'], $row['name'], number_format($row['balance'], 2, '.', '')]);
            }
            fputcsv($out, ['', 'Total Expenses', number_format($data['totalExpenses'], 2, '.', '')]);
            fputcsv($out, []);
            fputcsv($out, ['', 'Net Profit / (Loss)', number_format($data['netProfit'], 2, '.', '')]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function balanceSheetCsv(Request $request): StreamedResponse
    {
        $data = $this->balanceSheetData($request);
        $filename = "balance-sheet-{$data['asOf']}.csv";

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Balance Sheet Report']);
            fputcsv($out, ['As of:', $data['asOf']]);
            fputcsv($out, []);
            fputcsv($out, ['ASSETS']);
            foreach (['current' => 'Current Assets', 'fixed' => 'Fixed Assets', 'other' => 'Other Assets'] as $key => $label) {
                if (!empty($data['assets'][$key])) {
                    fputcsv($out, [$label]);
                    foreach ($data['assets'][$key] as $row) {
                        fputcsv($out, [$row['code'], $row['name'], number_format($row['balance'], 2, '.', '')]);
                    }
                }
            }
            fputcsv($out, ['', 'Total Assets', number_format($data['totalAssets'], 2, '.', '')]);
            fputcsv($out, []);
            fputcsv($out, ['LIABILITIES']);
            foreach (['current' => 'Current Liabilities', 'long_term' => 'Long-term Liabilities'] as $key => $label) {
                if (!empty($data['liabilities'][$key])) {
                    fputcsv($out, [$label]);
                    foreach ($data['liabilities'][$key] as $row) {
                        fputcsv($out, [$row['code'], $row['name'], number_format($row['balance'], 2, '.', '')]);
                    }
                }
            }
            fputcsv($out, ['', 'Total Liabilities', number_format($data['totalLiabilities'], 2, '.', '')]);
            fputcsv($out, []);
            fputcsv($out, ['EQUITY']);
            foreach ($data['equity'] as $row) {
                fputcsv($out, [$row['code'], $row['name'], number_format($row['balance'], 2, '.', '')]);
            }
            fputcsv($out, ['', 'Retained Earnings', number_format($data['retainedEarnings'], 2, '.', '')]);
            fputcsv($out, ['', 'Total Equity', number_format($data['totalEquity'], 2, '.', '')]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function vatSummaryCsv(Request $request): StreamedResponse
    {
        $data = $this->vatSummaryData($request);
        $filename = "vat-summary-{$data['from']}-to-{$data['to']}.csv";

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['VAT Summary Report']);
            fputcsv($out, ['Period:', $data['from'] . ' to ' . $data['to']]);
            fputcsv($out, []);
            fputcsv($out, ['OUTPUT VAT (Sales / Invoices)']);
            fputcsv($out, ['Invoice #', 'Date', 'Subtotal', 'VAT', 'Total']);
            foreach ($data['invoices'] as $inv) {
                fputcsv($out, [
                    $inv->invoice_number, $inv->issue_date,
                    number_format($inv->subtotal, 2, '.', ''),
                    number_format($inv->tax_amount, 2, '.', ''),
                    number_format($inv->total, 2, '.', ''),
                ]);
            }
            fputcsv($out, ['', '', '', 'Output VAT', number_format($data['outputVat'], 2, '.', '')]);
            fputcsv($out, []);
            fputcsv($out, ['INPUT VAT (Purchases / Bills)']);
            fputcsv($out, ['Bill #', 'Date', 'Subtotal', 'VAT', 'Total']);
            foreach ($data['bills'] as $bill) {
                fputcsv($out, [
                    $bill->bill_number ?? '—', $bill->issue_date,
                    number_format($bill->subtotal, 2, '.', ''),
                    number_format($bill->tax_amount, 2, '.', ''),
                    number_format($bill->total, 2, '.', ''),
                ]);
            }
            fputcsv($out, ['', '', '', 'Input VAT', number_format($data['inputVat'], 2, '.', '')]);
            fputcsv($out, []);
            fputcsv($out, ['', '', '', 'NET VAT PAYABLE', number_format($data['vatPayable'], 2, '.', '')]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    // ── Shared data builders ──────────────────────────────────────────────────

    private function profitLossData(Request $request): array
    {
        $company   = $request->user()->currentCompany;
        $companyId = $company->id;
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : now()->startOfYear();
        $to   = $request->filled('to')   ? Carbon::parse($request->input('to'))->endOfDay()     : now()->endOfDay();

        $rows = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->whereIn('accounts.type', ['income', 'expense'])
            ->whereBetween('journal_entries.entry_date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('journal_entries.deleted_at')->whereNull('accounts.deleted_at')
            ->selectRaw('accounts.id, accounts.code, accounts.name, accounts.type, accounts.subtype, SUM(journal_lines.debit) as total_debit, SUM(journal_lines.credit) as total_credit')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type', 'accounts.subtype')
            ->orderBy('accounts.code')->get();

        $income = $expenses = [];
        foreach ($rows as $row) {
            $balance = $row->type === 'income'
                ? round($row->total_credit - $row->total_debit, 2)
                : round($row->total_debit - $row->total_credit, 2);
            $data = ['code' => $row->code, 'name' => $row->name, 'subtype' => $row->subtype, 'balance' => $balance];
            $row->type === 'income' ? $income[] = $data : $expenses[] = $data;
        }

        $totalIncome   = array_sum(array_column($income, 'balance'));
        $totalExpenses = array_sum(array_column($expenses, 'balance'));
        return [
            'income' => $income, 'expenses' => $expenses,
            'totalIncome' => round($totalIncome, 2), 'totalExpenses' => round($totalExpenses, 2),
            'netProfit' => round($totalIncome - $totalExpenses, 2),
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'company' => $company->only('name', 'currency'),
        ];
    }

    private function balanceSheetData(Request $request): array
    {
        $company   = $request->user()->currentCompany;
        $companyId = $company->id;
        $asOf = $request->filled('as_of') ? Carbon::parse($request->input('as_of'))->endOfDay() : now()->endOfDay();

        $plRows = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.company_id', $companyId)->where('journal_entries.status', 'posted')
            ->whereIn('accounts.type', ['income', 'expense'])
            ->where('journal_entries.entry_date', '<=', $asOf->toDateString())
            ->whereNull('journal_entries.deleted_at')->whereNull('accounts.deleted_at')
            ->selectRaw('accounts.type, SUM(journal_lines.debit) as total_debit, SUM(journal_lines.credit) as total_credit')
            ->groupBy('accounts.type')->get()->keyBy('type');

        $retainedEarnings = 0;
        if (isset($plRows['income']))  $retainedEarnings += $plRows['income']->total_credit - $plRows['income']->total_debit;
        if (isset($plRows['expense'])) $retainedEarnings -= $plRows['expense']->total_debit - $plRows['expense']->total_credit;

        $rows = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.company_id', $companyId)->where('journal_entries.status', 'posted')
            ->whereIn('accounts.type', ['asset', 'liability', 'equity'])
            ->where('journal_entries.entry_date', '<=', $asOf->toDateString())
            ->whereNull('journal_entries.deleted_at')->whereNull('accounts.deleted_at')
            ->selectRaw('accounts.id, accounts.code, accounts.name, accounts.type, accounts.subtype, accounts.opening_balance, SUM(journal_lines.debit) as total_debit, SUM(journal_lines.credit) as total_credit')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type', 'accounts.subtype', 'accounts.opening_balance')
            ->orderBy('accounts.code')->get();

        $assets = ['current' => [], 'fixed' => [], 'other' => []];
        $liabilities = ['current' => [], 'long_term' => []];
        $equity = [];
        foreach ($rows as $row) {
            $opening = (float) $row->opening_balance;
            $balance = $row->type === 'asset'
                ? round($opening + $row->total_debit - $row->total_credit, 2)
                : round($opening + $row->total_credit - $row->total_debit, 2);
            $data = ['code' => $row->code, 'name' => $row->name, 'subtype' => $row->subtype, 'balance' => $balance];
            match ($row->subtype) {
                'current_asset' => $assets['current'][] = $data,
                'fixed_asset'   => $assets['fixed'][] = $data,
                'other_asset'   => $assets['other'][] = $data,
                'current_liability'   => $liabilities['current'][] = $data,
                'long_term_liability' => $liabilities['long_term'][] = $data,
                default               => $equity[] = $data,
            };
        }

        $totalAssets = array_sum(array_column($assets['current'], 'balance')) + array_sum(array_column($assets['fixed'], 'balance')) + array_sum(array_column($assets['other'], 'balance'));
        $totalLiabilities = array_sum(array_column($liabilities['current'], 'balance')) + array_sum(array_column($liabilities['long_term'], 'balance'));
        $totalEquity = array_sum(array_column($equity, 'balance')) + $retainedEarnings;

        return [
            'assets' => $assets, 'liabilities' => $liabilities, 'equity' => $equity,
            'retainedEarnings' => round($retainedEarnings, 2),
            'totalAssets' => round($totalAssets, 2), 'totalLiabilities' => round($totalLiabilities, 2), 'totalEquity' => round($totalEquity, 2),
            'asOf' => $asOf->toDateString(),
            'company' => $company->only('name', 'currency'),
        ];
    }

    private function vatSummaryData(Request $request): array
    {
        $company   = $request->user()->currentCompany;
        $companyId = $company->id;
        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : now()->startOfQuarter();
        $to   = $request->filled('to')   ? Carbon::parse($request->input('to'))   : now();

        $invoices = DB::table('invoices')->where('company_id', $companyId)
            ->whereNotIn('status', ['void', 'draft'])
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('deleted_at')->orderBy('issue_date')
            ->get(['id', 'invoice_number', 'issue_date', 'subtotal', 'tax_amount', 'total']);

        $bills = DB::table('bills')->where('company_id', $companyId)
            ->whereNotIn('status', ['void', 'draft'])
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('deleted_at')->orderBy('issue_date')
            ->get(['id', 'bill_number', 'issue_date', 'subtotal', 'tax_amount', 'total']);

        return [
            'invoices' => $invoices, 'bills' => $bills,
            'outputVat' => round((float) $invoices->sum('tax_amount'), 2),
            'inputVat'  => round((float) $bills->sum('tax_amount'), 2),
            'vatPayable' => round((float) $invoices->sum('tax_amount') - (float) $bills->sum('tax_amount'), 2),
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'company' => $company->only('name', 'currency'),
        ];
    }

    private function ageBucket(int $daysOverdue): string
    {
        if ($daysOverdue <= 0)  return 'current';
        if ($daysOverdue <= 30) return '1-30';
        if ($daysOverdue <= 60) return '31-60';
        if ($daysOverdue <= 90) return '61-90';
        return '90+';
    }
}

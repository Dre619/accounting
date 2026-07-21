<?php

namespace App\Http\Controllers;

use App\Services\DocumentPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('reports/Index', [
            'taxRegime' => $request->user()->currentCompany->tax_regime,
        ]);
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

    public function inventoryValuation(Request $request): Response
    {
        return Inertia::render('reports/InventoryValuation', $this->inventoryValuationData($request));
    }

    public function stockMovements(Request $request): Response
    {
        return Inertia::render('reports/StockMovements', $this->stockMovementsData($request));
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

    public function profitLossPrint(Request $request, DocumentPdfService $pdf): \Symfony\Component\HttpFoundation\Response
    {
        $data = $this->profitLossData($request);
        $data['logoSrc'] = $pdf->logoDataUri($request->user()->currentCompany->logo_path);
        return $pdf->streamInline('reports.profit-loss', $data, "Profit-Loss-{$data['from']}-to-{$data['to']}.pdf");
    }

    public function balanceSheetPrint(Request $request, DocumentPdfService $pdf): \Symfony\Component\HttpFoundation\Response
    {
        $data = $this->balanceSheetData($request);
        $data['logoSrc'] = $pdf->logoDataUri($request->user()->currentCompany->logo_path);
        return $pdf->streamInline('reports.balance-sheet', $data, "Balance-Sheet-{$data['asOf']}.pdf");
    }

    public function vatSummaryPrint(Request $request, DocumentPdfService $pdf): \Symfony\Component\HttpFoundation\Response
    {
        $data = $this->vatSummaryData($request);
        $data['logoSrc'] = $pdf->logoDataUri($request->user()->currentCompany->logo_path);
        return $pdf->streamInline('reports.vat-summary', $data, "VAT-Summary-{$data['from']}-to-{$data['to']}.pdf");
    }

    /**
     * Stock on hand valued at weighted-average cost, reconciled against the
     * 1300 Inventory control account balance in the general ledger.
     */
    private function inventoryValuationData(Request $request): array
    {
        $company = $request->user()->currentCompany;

        $rows = $company->products()
            ->where('type', 'inventory')
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'quantity_on_hand', 'average_cost'])
            ->map(function ($p) {
                $qty = (float) $p->quantity_on_hand;
                $avg = (float) $p->average_cost;

                return [
                    'id'           => $p->id,
                    'sku'          => $p->sku,
                    'name'         => $p->name,
                    'quantity'     => $qty,
                    'average_cost' => $avg,
                    'value'        => round($qty * $avg, 2),
                ];
            })
            ->values();

        $totalValue = round($rows->sum('value'), 2);

        $inventoryAccount = $company->accounts()->where('code', '1300')->first();
        $glBalance = $inventoryAccount ? round((float) $inventoryAccount->balance, 2) : null;

        return [
            'rows'        => $rows,
            'totalValue'  => $totalValue,
            'glBalance'   => $glBalance,
            'variance'    => $glBalance !== null ? round($totalValue - $glBalance, 2) : null,
            'generatedAt' => now()->toDateString(),
            'company'     => $company->only('name', 'address', 'city', 'tpin'),
        ];
    }

    /**
     * A filterable audit trail of every stock movement in a period, with in/out
     * totals. Filter by product, movement type and date range.
     */
    private function stockMovementsData(Request $request): array
    {
        $company = $request->user()->currentCompany;

        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->toDateString()
            : now()->startOfMonth()->toDateString();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->toDateString()
            : now()->toDateString();

        $productId = $request->integer('product_id') ?: null;
        $type      = $request->query('type', 'all');

        $movements = \App\Models\StockMovement::where('company_id', $company->id)
            ->whereDate('movement_date', '>=', $from)
            ->whereDate('movement_date', '<=', $to)
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->when($type !== 'all', fn ($q) => $q->where('type', $type))
            ->with('product:id,name,sku')
            ->orderBy('movement_date')
            ->orderBy('id')
            ->get()
            ->map(fn ($m) => [
                'id'         => $m->id,
                'date'       => $m->movement_date->toDateString(),
                'product'    => $m->product?->name ?? '—',
                'sku'        => $m->product?->sku,
                'type'       => $m->type,
                'quantity'   => (float) $m->quantity,
                'unit_cost'  => (float) $m->unit_cost,
                'value'      => (float) $m->total_cost,
                'balance'    => (float) $m->running_qty,
                'note'       => $m->description,
            ])
            ->values();

        $qtyIn    = $movements->where('quantity', '>', 0)->sum('quantity');
        $qtyOut   = $movements->where('quantity', '<', 0)->sum('quantity');
        $valueIn  = round($movements->where('quantity', '>', 0)->sum('value'), 2);
        $valueOut = round($movements->where('quantity', '<', 0)->sum('value'), 2);

        return [
            'movements' => $movements,
            'filters'   => [
                'from'       => $from,
                'to'         => $to,
                'product_id' => $productId,
                'type'       => $type,
            ],
            'products'  => $company->products()->where('type', 'inventory')
                ->orderBy('name')->get(['id', 'name']),
            'totals'    => [
                'qty_in'    => round((float) $qtyIn, 3),
                'qty_out'   => round((float) $qtyOut, 3),
                'value_in'  => $valueIn,
                'value_out' => $valueOut,
            ],
        ];
    }

    // ── CSV exports ───────────────────────────────────────────────────────────

    public function stockMovementsCsv(Request $request): StreamedResponse
    {
        $data = $this->stockMovementsData($request);
        $filename = "stock-movements-{$data['filters']['from']}-to-{$data['filters']['to']}.csv";

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Stock Movements']);
            fputcsv($out, ['Period:', $data['filters']['from'] . ' to ' . $data['filters']['to']]);
            fputcsv($out, []);
            fputcsv($out, ['Date', 'Product', 'SKU', 'Type', 'Quantity', 'Unit Cost', 'Value', 'Balance', 'Note']);
            foreach ($data['movements'] as $m) {
                fputcsv($out, [
                    $m['date'], $m['product'], $m['sku'], $m['type'],
                    $m['quantity'], number_format($m['unit_cost'], 4, '.', ''),
                    number_format($m['value'], 2, '.', ''), $m['balance'], $m['note'],
                ]);
            }
            fputcsv($out, []);
            fputcsv($out, ['', '', '', 'Total in', $data['totals']['qty_in'], '', number_format($data['totals']['value_in'], 2, '.', '')]);
            fputcsv($out, ['', '', '', 'Total out', $data['totals']['qty_out'], '', number_format($data['totals']['value_out'], 2, '.', '')]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function inventoryValuationCsv(Request $request): StreamedResponse
    {
        $data = $this->inventoryValuationData($request);
        $filename = "inventory-valuation-{$data['generatedAt']}.csv";

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Inventory Valuation']);
            fputcsv($out, ['As of:', $data['generatedAt']]);
            fputcsv($out, []);
            fputcsv($out, ['SKU', 'Product', 'Quantity', 'Avg Cost (ZMW)', 'Value (ZMW)']);
            foreach ($data['rows'] as $row) {
                fputcsv($out, [
                    $row['sku'],
                    $row['name'],
                    $row['quantity'],
                    number_format($row['average_cost'], 4, '.', ''),
                    number_format($row['value'], 2, '.', ''),
                ]);
            }
            fputcsv($out, []);
            fputcsv($out, ['', '', '', 'Total stock value', number_format($data['totalValue'], 2, '.', '')]);
            if ($data['glBalance'] !== null) {
                fputcsv($out, ['', '', '', 'GL Inventory (1300)', number_format($data['glBalance'], 2, '.', '')]);
                fputcsv($out, ['', '', '', 'Variance', number_format($data['variance'], 2, '.', '')]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

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
            fputcsv($out, ['', 'Profit Before Tax', number_format($data['profitBeforeTax'], 2, '.', '')]);
            if ($data['taxes']) {
                fputcsv($out, []);
                fputcsv($out, ['TAXATION']);
                foreach ($data['taxes'] as $row) {
                    fputcsv($out, [$row['code'], $row['name'], number_format($row['balance'], 2, '.', '')]);
                }
                fputcsv($out, ['', 'Total Tax', number_format($data['totalTax'], 2, '.', '')]);
            }
            fputcsv($out, []);
            fputcsv($out, ['', 'Net Profit / (Loss) After Tax', number_format($data['netProfit'], 2, '.', '')]);
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
            ->whereDate('journal_entries.entry_date', '>=', $from->toDateString())
            ->whereDate('journal_entries.entry_date', '<=', $to->toDateString())
            ->whereNull('journal_entries.deleted_at')->whereNull('accounts.deleted_at')
            ->selectRaw('accounts.id, accounts.code, accounts.name, accounts.type, accounts.subtype, SUM(journal_lines.debit) as total_debit, SUM(journal_lines.credit) as total_credit')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type', 'accounts.subtype')
            ->orderBy('accounts.code')->get();

        // Taxation accounts are reported below operating profit, not mixed into
        // operating expenses — otherwise the profit that tax is computed from
        // would itself include the tax charge.
        $income = $expenses = $taxes = [];
        foreach ($rows as $row) {
            $balance = $row->type === 'income'
                ? round($row->total_credit - $row->total_debit, 2)
                : round($row->total_debit - $row->total_credit, 2);
            $data = ['code' => $row->code, 'name' => $row->name, 'subtype' => $row->subtype, 'balance' => $balance];

            if ($row->type === 'income') {
                $income[] = $data;
            } elseif ($row->subtype === 'taxation') {
                $taxes[] = $data;
            } else {
                $expenses[] = $data;
            }
        }

        $totalIncome     = array_sum(array_column($income, 'balance'));
        $totalExpenses   = array_sum(array_column($expenses, 'balance'));
        $totalTax        = array_sum(array_column($taxes, 'balance'));
        $profitBeforeTax = round($totalIncome - $totalExpenses, 2);

        return [
            'income' => $income, 'expenses' => $expenses, 'taxes' => $taxes,
            'totalIncome' => round($totalIncome, 2), 'totalExpenses' => round($totalExpenses, 2),
            'profitBeforeTax' => $profitBeforeTax,
            'totalTax' => round($totalTax, 2),
            // netProfit remains the bottom line — now after tax.
            'netProfit' => round($profitBeforeTax - $totalTax, 2),
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
            ->whereDate('journal_entries.entry_date', '<=', $asOf->toDateString())
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
            ->whereDate('journal_entries.entry_date', '<=', $asOf->toDateString())
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
            ->whereDate('issue_date', '>=', $from->toDateString())
            ->whereDate('issue_date', '<=', $to->toDateString())
            ->whereNull('deleted_at')->orderBy('issue_date')
            ->get(['id', 'invoice_number', 'issue_date', 'subtotal', 'tax_amount', 'total']);

        $bills = DB::table('bills')->where('company_id', $companyId)
            ->whereNotIn('status', ['void', 'draft'])
            ->whereDate('issue_date', '>=', $from->toDateString())
            ->whereDate('issue_date', '<=', $to->toDateString())
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

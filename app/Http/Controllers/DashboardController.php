<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $company   = $request->user()->currentCompany;
        $companyId = $company->id;

        // Receivables: unpaid/partial invoices
        $receivables = DB::table('invoices')
            ->where('company_id', $companyId)
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->whereNull('deleted_at')
            ->selectRaw('SUM(amount_due) as total, COUNT(*) as count')
            ->first();

        // Overdue invoices
        $overdue = DB::table('invoices')
            ->where('company_id', $companyId)
            ->whereIn('status', ['sent', 'partial'])
            ->where('due_date', '<', now()->toDateString())
            ->whereNull('deleted_at')
            ->selectRaw('SUM(amount_due) as total, COUNT(*) as count')
            ->first();

        // Revenue this month
        $revenueThisMonth = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.company_id', $companyId)
            ->whereIn('invoices.status', ['sent', 'partial', 'paid'])
            ->whereMonth('invoices.issue_date', now()->month)
            ->whereYear('invoices.issue_date', now()->year)
            ->whereNull('invoices.deleted_at')
            ->sum('invoice_items.subtotal');

        // Revenue last 6 months
        $revenueMonthly = DB::table('invoices')
            ->where('company_id', $companyId)
            ->whereIn('status', ['sent', 'partial', 'paid'])
            ->where('issue_date', '>=', now()->subMonths(5)->startOfMonth()->toDateString())
            ->whereNull('deleted_at')
            ->selectRaw("DATE_FORMAT(issue_date, '%Y-%m') as month, SUM(total) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Recent invoices
        $recentInvoices = $company->invoices()
            ->with('contact:id,name')
            ->whereNotIn('status', ['void'])
            ->latest()
            ->limit(6)
            ->get(['id', 'invoice_number', 'contact_id', 'status', 'total', 'due_date', 'amount_due']);

        // Invoice status counts
        $invoiceCounts = DB::table('invoices')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['void'])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return Inertia::render('Dashboard', [
            'stats' => [
                'receivables_total'  => $receivables->total ?? 0,
                'receivables_count'  => $receivables->count ?? 0,
                'overdue_total'      => $overdue->total ?? 0,
                'overdue_count'      => $overdue->count ?? 0,
                'revenue_this_month' => $revenueThisMonth ?? 0,
            ],
            'invoiceCounts'  => $invoiceCounts,
            'revenueMonthly' => $revenueMonthly,
            'recentInvoices' => $recentInvoices,
            'company'        => $company->only('name', 'currency'),
        ]);
    }
}

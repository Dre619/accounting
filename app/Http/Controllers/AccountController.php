<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $company   = $request->user()->currentCompany;
        $companyId = $company->id;

        // Load all accounts with running balance calculated via a subquery
        $accounts = DB::table('accounts')
            ->leftJoin('journal_lines', 'journal_lines.account_id', '=', 'accounts.id')
            ->leftJoin('journal_entries', function ($join) {
                $join->on('journal_entries.id', '=', 'journal_lines.journal_entry_id')
                     ->where('journal_entries.status', '=', 'posted')
                     ->whereNull('journal_entries.deleted_at');
            })
            ->where('accounts.company_id', $companyId)
            ->whereNull('accounts.deleted_at')
            ->selectRaw('
                accounts.id,
                accounts.code,
                accounts.name,
                accounts.type,
                accounts.subtype,
                accounts.is_system,
                accounts.is_bank_account,
                accounts.is_active,
                accounts.opening_balance,
                SUM(COALESCE(journal_lines.debit, 0))  as total_debit,
                SUM(COALESCE(journal_lines.credit, 0)) as total_credit
            ')
            ->groupBy(
                'accounts.id', 'accounts.code', 'accounts.name',
                'accounts.type', 'accounts.subtype', 'accounts.is_system',
                'accounts.is_bank_account', 'accounts.is_active', 'accounts.opening_balance'
            )
            ->orderBy('accounts.code')
            ->get()
            ->map(function ($row) {
                $opening = (float) $row->opening_balance;
                $balance = in_array($row->type, ['asset', 'expense'])
                    ? $opening + $row->total_debit - $row->total_credit
                    : $opening + $row->total_credit - $row->total_debit;

                return [
                    'id'              => $row->id,
                    'code'            => $row->code,
                    'name'            => $row->name,
                    'type'            => $row->type,
                    'subtype'         => $row->subtype,
                    'is_system'       => (bool) $row->is_system,
                    'is_bank_account' => (bool) $row->is_bank_account,
                    'is_active'       => (bool) $row->is_active,
                    'balance'         => round($balance, 2),
                ];
            });

        // Group: type → accounts
        $typeOrder = ['asset', 'liability', 'equity', 'income', 'expense'];
        $grouped   = [];
        foreach ($typeOrder as $type) {
            $grouped[$type] = $accounts->where('type', $type)->values();
        }

        $totals = [];
        foreach ($typeOrder as $type) {
            $totals[$type] = round($grouped[$type]->sum('balance'), 2);
        }

        return Inertia::render('accounts/Index', [
            'grouped' => $grouped,
            'totals'  => $totals,
            'company' => $company->only('name', 'currency'),
        ]);
    }

    public function show(Request $request, Account $account): Response
    {
        $company = $request->user()->currentCompany;
        abort_unless($account->company_id === $company->id, 403);

        $from = $request->filled('from') ? $request->input('from') : null;
        $to   = $request->filled('to')   ? $request->input('to')   : null;

        $linesQuery = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->leftJoin('contacts', 'contacts.id', '=', 'journal_lines.contact_id')
            ->where('journal_lines.account_id', $account->id)
            ->where('journal_entries.status', 'posted')
            ->whereNull('journal_entries.deleted_at')
            ->when($from, fn ($q) => $q->where('journal_entries.entry_date', '>=', $from))
            ->when($to,   fn ($q) => $q->where('journal_entries.entry_date', '<=', $to))
            ->selectRaw('
                journal_entries.entry_date,
                journal_entries.entry_number,
                journal_entries.description,
                journal_entries.source,
                journal_lines.debit,
                journal_lines.credit,
                contacts.name as contact_name
            ')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.id')
            ->get();

        // Calculate running balance from inception (opening balance + all lines before filter)
        $openingBalance = (float) $account->opening_balance;

        // If filtering by date, compute balance before $from
        $priorBalance = 0.0;
        if ($from) {
            $prior = DB::table('journal_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->where('journal_lines.account_id', $account->id)
                ->where('journal_entries.status', 'posted')
                ->whereNull('journal_entries.deleted_at')
                ->where('journal_entries.entry_date', '<', $from)
                ->selectRaw('SUM(journal_lines.debit) as d, SUM(journal_lines.credit) as c')
                ->first();

            $d = (float) ($prior->d ?? 0);
            $c = (float) ($prior->c ?? 0);
            $priorBalance = in_array($account->type, ['asset', 'expense'])
                ? $openingBalance + $d - $c
                : $openingBalance + $c - $d;
        } else {
            $priorBalance = $openingBalance;
        }

        // Build lines with running balance
        $runningBalance = $priorBalance;
        $lines = $linesQuery->map(function ($line) use (&$runningBalance, $account) {
            $debit  = (float) $line->debit;
            $credit = (float) $line->credit;

            if (in_array($account->type, ['asset', 'expense'])) {
                $runningBalance += $debit - $credit;
            } else {
                $runningBalance += $credit - $debit;
            }

            return [
                'date'         => $line->entry_date,
                'entry_number' => $line->entry_number,
                'description'  => $line->description,
                'source'       => $line->source,
                'contact'      => $line->contact_name,
                'debit'        => $debit,
                'credit'       => $credit,
                'balance'      => round($runningBalance, 2),
            ];
        });

        return Inertia::render('accounts/Show', [
            'account'        => [
                'id'        => $account->id,
                'code'      => $account->code,
                'name'      => $account->name,
                'type'      => $account->type,
                'subtype'   => $account->subtype,
                'is_system' => $account->is_system,
            ],
            'lines'          => $lines,
            'openingBalance' => round($priorBalance, 2),
            'closingBalance' => round($runningBalance, 2),
            'from'           => $from,
            'to'             => $to,
            'company'        => $company->only('name', 'currency'),
        ]);
    }

    public function create(Request $request): Response
    {
        $company = $request->user()->currentCompany;

        $categories = AccountCategory::where(function ($q) use ($company) {
            $q->whereNull('company_id')->orWhere('company_id', $company->id);
        })->orderBy('sort_order')->get(['id', 'name', 'type']);

        return Inertia::render('accounts/Form', [
            'categories' => $categories,
            'account'    => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $request->user()->currentCompany;

        $validated = $request->validate([
            'account_category_id' => ['required', 'integer', 'exists:account_categories,id'],
            'code'                => ['required', 'string', 'max:20'],
            'name'                => ['required', 'string', 'max:150'],
            'description'         => ['nullable', 'string', 'max:500'],
            'type'                => ['required', 'in:asset,liability,equity,income,expense'],
            'subtype'             => ['nullable', 'string'],
            'is_bank_account'     => ['boolean'],
            'bank_name'           => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'opening_balance'     => ['nullable', 'numeric'],
            'opening_balance_date'=> ['nullable', 'date'],
        ]);

        // Ensure code is unique for this company
        $exists = Account::where('company_id', $company->id)
            ->where('code', $validated['code'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['code' => 'This account code is already in use.'])->withInput();
        }

        Account::create(array_merge($validated, ['company_id' => $company->id]));

        return redirect()->route('accounts.index')
            ->with('success', 'Account created.');
    }

    public function edit(Request $request, Account $account): Response
    {
        $company = $request->user()->currentCompany;
        abort_unless($account->company_id === $company->id, 403);

        $categories = AccountCategory::where(function ($q) use ($company) {
            $q->whereNull('company_id')->orWhere('company_id', $company->id);
        })->orderBy('sort_order')->get(['id', 'name', 'type']);

        return Inertia::render('accounts/Form', [
            'categories' => $categories,
            'account'    => $account,
        ]);
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $company = $request->user()->currentCompany;
        abort_unless($account->company_id === $company->id, 403);

        $validated = $request->validate([
            'account_category_id' => ['required', 'integer', 'exists:account_categories,id'],
            'name'                => ['required', 'string', 'max:150'],
            'description'         => ['nullable', 'string', 'max:500'],
            'is_bank_account'     => ['boolean'],
            'bank_name'           => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'opening_balance'     => ['nullable', 'numeric'],
            'opening_balance_date'=> ['nullable', 'date'],
            'is_active'           => ['boolean'],
        ]);

        // System accounts: only allow name, category, bank details, active flag
        if ($account->is_system) {
            $validated = array_intersect_key($validated, array_flip([
                'account_category_id', 'name', 'is_bank_account',
                'bank_name', 'bank_account_number', 'is_active',
            ]));
        }

        $account->update($validated);

        return redirect()->route('accounts.show', $account)
            ->with('success', 'Account updated.');
    }

    public function destroy(Request $request, Account $account): RedirectResponse
    {
        $company = $request->user()->currentCompany;
        abort_unless($account->company_id === $company->id, 403);
        abort_if($account->is_system, 403, 'System accounts cannot be deleted.');

        $hasLines = DB::table('journal_lines')->where('account_id', $account->id)->exists();
        if ($hasLines) {
            return back()->withErrors(['account' => 'Cannot delete an account that has transactions.']);
        }

        $account->delete();

        return redirect()->route('accounts.index')
            ->with('success', 'Account deleted.');
    }
}

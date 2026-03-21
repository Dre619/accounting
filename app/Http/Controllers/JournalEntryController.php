<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class JournalEntryController extends Controller
{
    public function index(Request $request): Response
    {
        $company = $request->user()->currentCompany;

        $entries = JournalEntry::where('company_id', $company->id)
            ->where('source', 'manual')
            ->withCount('lines')
            ->with('createdBy:id,name')
            ->latest('entry_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('journal/Index', [
            'entries' => $entries,
        ]);
    }

    public function create(Request $request): Response
    {
        $company  = $request->user()->currentCompany;
        $accounts = Account::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        $contacts = $company->contacts()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('journal/Form', [
            'accounts' => $accounts,
            'contacts' => $contacts,
            'today'    => now()->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $request->user()->currentCompany;

        $data = $request->validate([
            'entry_date'        => ['required', 'date'],
            'description'       => ['required', 'string', 'max:500'],
            'lines'             => ['required', 'array', 'min:2'],
            'lines.*.account_id'  => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit'       => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit'      => ['nullable', 'numeric', 'min:0'],
            'lines.*.contact_id'  => ['nullable', 'integer', 'exists:contacts,id'],
        ]);

        // Validate balance
        $totalDebit  = collect($data['lines'])->sum(fn ($l) => (float) ($l['debit']  ?? 0));
        $totalCredit = collect($data['lines'])->sum(fn ($l) => (float) ($l['credit'] ?? 0));

        if (abs($totalDebit - $totalCredit) > 0.01) {
            return back()->withErrors(['lines' => 'Journal entry must balance: total debits must equal total credits.'])->withInput();
        }

        if ($totalDebit <= 0) {
            return back()->withErrors(['lines' => 'Journal entry must have at least one debit and one credit line.'])->withInput();
        }

        DB::transaction(function () use ($company, $data, $request) {
            $count       = JournalEntry::where('company_id', $company->id)->where('source', 'manual')->count();
            $entryNumber = 'JNL-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

            $entry = JournalEntry::create([
                'company_id'   => $company->id,
                'entry_number' => $entryNumber,
                'entry_date'   => $data['entry_date'],
                'description'  => $data['description'],
                'status'       => 'draft',
                'source'       => 'manual',
                'created_by'   => $request->user()->id,
            ]);

            foreach ($data['lines'] as $i => $line) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $line['account_id'],
                    'description'      => $line['description'] ?? null,
                    'debit'            => (float) ($line['debit']  ?? 0),
                    'credit'           => (float) ($line['credit'] ?? 0),
                    'contact_id'       => $line['contact_id'] ?? null,
                    'sort_order'       => $i,
                ]);
            }
        });

        return redirect()->route('journal.index')
            ->with('success', 'Journal entry created as draft. Review and post it to record it.');
    }

    public function show(Request $request, JournalEntry $entry): Response
    {
        abort_unless($entry->company_id === $request->user()->currentCompany->id, 403);

        $entry->load([
            'lines.account:id,code,name,type',
            'lines.contact:id,name',
            'createdBy:id,name',
        ]);

        return Inertia::render('journal/Show', [
            'entry' => $entry,
        ]);
    }

    public function post(Request $request, JournalEntry $entry): RedirectResponse
    {
        abort_unless($entry->company_id === $request->user()->currentCompany->id, 403);
        abort_unless($entry->status === 'draft', 422, 'Only draft entries can be posted.');

        $entry->post();

        return back()->with('success', "Entry {$entry->entry_number} posted.");
    }

    public function destroy(Request $request, JournalEntry $entry): RedirectResponse
    {
        abort_unless($entry->company_id === $request->user()->currentCompany->id, 403);
        abort_unless($entry->status === 'draft', 422, 'Only draft entries can be deleted.');

        $entry->delete();

        return redirect()->route('journal.index')
            ->with('success', 'Journal entry deleted.');
    }
}

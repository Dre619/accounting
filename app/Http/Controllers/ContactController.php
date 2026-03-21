<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $company = $request->user()->currentCompany;
        $type    = $request->query('type', 'all');
        $search  = $request->query('search', '');

        $contacts = $company->contacts()
            ->when($type !== 'all', fn ($q) => $q->where('type', $type))
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"))
            ->withCount(['invoices', 'bills'])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('contacts/Index', [
            'contacts'    => $contacts,
            'currentType' => $type,
            'search'      => $search,
            'counts'      => [
                'all'      => $company->contacts()->count(),
                'customer' => $company->contacts()->where('type', 'customer')->count(),
                'supplier' => $company->contacts()->where('type', 'supplier')->count(),
                'both'     => $company->contacts()->where('type', 'both')->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('contacts/Form', ['contact' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $request->user()->currentCompany->contacts()->create($data);

        return redirect()->route('contacts.index')
            ->with('success', 'Contact created.');
    }

    public function show(Request $request, Contact $contact): Response
    {
        $this->authorise($request, $contact);

        $contact->load(['invoices' => fn ($q) => $q->latest()->limit(10),
                         'bills'   => fn ($q) => $q->latest()->limit(10)]);

        $stats = [
            'total_invoiced' => $contact->invoices()->whereNotIn('status', ['void', 'draft'])->sum('total'),
            'total_paid'     => $contact->invoices()->where('status', 'paid')->sum('total'),
            'outstanding'    => $contact->invoices()->whereIn('status', ['sent', 'partial', 'overdue'])->sum('amount_due'),
        ];

        return Inertia::render('contacts/Show', compact('contact', 'stats'));
    }

    public function edit(Request $request, Contact $contact): Response
    {
        $this->authorise($request, $contact);

        return Inertia::render('contacts/Form', compact('contact'));
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $this->authorise($request, $contact);
        $contact->update($this->validated($request));

        return redirect()->route('contacts.index')
            ->with('success', 'Contact updated.');
    }

    public function destroy(Request $request, Contact $contact): RedirectResponse
    {
        $this->authorise($request, $contact);
        $contact->delete();

        return redirect()->route('contacts.index')
            ->with('success', 'Contact deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'type'     => ['required', 'in:customer,supplier,both'],
            'name'     => ['required', 'string', 'max:150'],
            'tpin'     => ['nullable', 'string', 'max:10'],
            'email'    => ['nullable', 'email', 'max:150'],
            'phone'    => ['nullable', 'string', 'max:20'],
            'address'  => ['nullable', 'string', 'max:255'],
            'city'     => ['nullable', 'string', 'max:100'],
            'country'  => ['nullable', 'string', 'max:100'],
            'withholding_tax_applicable' => ['boolean'],
            'notes'    => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function authorise(Request $request, Contact $contact): void
    {
        abort_unless($contact->company_id === $request->user()->currentCompany->id, 403);
    }
}

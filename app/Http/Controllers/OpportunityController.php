<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Services\OpportunityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OpportunityController extends Controller
{
    public function __construct(private readonly OpportunityService $service) {}

    public function index(Request $request): Response
    {
        $company = $request->user()->currentCompany;

        $opportunities = $company->opportunities()
            ->with(['contact:id,name', 'owner:id,name'])
            ->latest('id')
            ->get()
            ->map(fn (Opportunity $o) => [
                'id'                  => $o->id,
                'title'               => $o->title,
                'stage'               => $o->stage,
                'estimated_value'     => (float) $o->estimated_value,
                'expected_close_date' => $o->expected_close_date?->toDateString(),
                'contact'             => $o->contact?->only('id', 'name'),
                'owner'               => $o->owner?->only('id', 'name'),
                'has_quote'           => $o->sales_order_id !== null,
            ]);

        $openValue = $company->opportunities()->open()->sum('estimated_value');
        $wonThisMonth = $company->opportunities()
            ->where('stage', 'won')->where('won_at', '>=', now()->startOfMonth())->sum('estimated_value');

        return Inertia::render('opportunities/Index', [
            'opportunities' => $opportunities,
            'stages'        => Opportunity::STAGES,
            'stats'         => [
                'open_value'      => round((float) $openValue, 2),
                'won_this_month'  => round((float) $wonThisMonth, 2),
                'open_count'      => $company->opportunities()->open()->count(),
                'overdue_tasks'   => $company->tasks()->overdue()->count(),
                'activities_week' => $company->activities()->where('occurred_at', '>=', now()->subWeek())->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('opportunities/Form', array_merge($this->formData($request), [
            'opportunity'      => null,
            'defaultContactId' => $request->integer('contact_id') ?: null,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $request->user()->currentCompany;
        $data = $this->validated($request);

        $opportunity = $company->opportunities()->create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('opportunities.show', $opportunity)
            ->with('success', 'Opportunity created.');
    }

    public function show(Request $request, Opportunity $opportunity): Response
    {
        $this->authorise($request, $opportunity);

        $opportunity->load([
            'contact:id,name,email', 'owner:id,name', 'salesOrder:id,order_number,status,total',
            'activities' => fn ($q) => $q->with('user:id,name')->latest('occurred_at')->limit(100),
            'tasks' => fn ($q) => $q->with('assignee:id,name')->latest('id'),
        ]);

        return Inertia::render('opportunities/Show', ['opportunity' => $opportunity]);
    }

    public function edit(Request $request, Opportunity $opportunity): Response
    {
        $this->authorise($request, $opportunity);

        return Inertia::render('opportunities/Form', array_merge(
            $this->formData($request),
            ['opportunity' => $opportunity]
        ));
    }

    public function update(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorise($request, $opportunity);
        $opportunity->update($this->validated($request));

        return redirect()->route('opportunities.show', $opportunity)->with('success', 'Opportunity updated.');
    }

    public function won(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorise($request, $opportunity);
        $this->service->markWon($opportunity);

        return back()->with('success', 'Marked as won. 🎉');
    }

    public function lost(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorise($request, $opportunity);
        $reason = $request->validate(['lost_reason' => ['nullable', 'string', 'max:255']])['lost_reason'] ?? null;
        $this->service->markLost($opportunity, $reason);

        return back()->with('success', 'Marked as lost.');
    }

    public function convert(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorise($request, $opportunity);
        $order = $this->service->convertToQuote($opportunity);

        return redirect()->route('sales-orders.show', $order)
            ->with('success', "Quote {$order->order_number} created from opportunity.");
    }

    public function destroy(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorise($request, $opportunity);
        $opportunity->delete();

        return redirect()->route('opportunities.index')->with('success', 'Opportunity deleted.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function formData(Request $request): array
    {
        $company = $request->user()->currentCompany;

        return [
            'contacts' => $company->contacts()->active()->orderBy('name')->get(['id', 'name']),
            'members'  => $company->allUsers()->map->only('id', 'name')->values(),
            'stages'   => Opportunity::OPEN_STAGES,
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'contact_id'          => ['required', 'integer'],
            'title'               => ['required', 'string', 'max:200'],
            'description'         => ['nullable', 'string', 'max:2000'],
            'stage'               => ['required', 'in:new,qualified,proposal,won,lost'],
            'estimated_value'     => ['nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
            'owner_id'            => ['nullable', 'integer'],
        ]);
    }

    private function authorise(Request $request, Opportunity $opportunity): void
    {
        abort_unless($opportunity->company_id === $request->user()->currentCompany->id, 403);
    }
}

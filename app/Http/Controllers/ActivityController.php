<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Services\ActivityService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function __construct(private readonly ActivityService $service) {}

    public function store(Request $request, Contact $contact): RedirectResponse
    {
        return $this->logFor($request, $contact);
    }

    public function storeForOpportunity(Request $request, Opportunity $opportunity): RedirectResponse
    {
        return $this->logFor($request, $opportunity);
    }

    private function logFor(Request $request, Model $subject): RedirectResponse
    {
        abort_unless($subject->company_id === $request->user()->currentCompany->id, 403);

        $data = $request->validate([
            'type'        => ['required', 'in:note,call,email,meeting'],
            'body'        => ['required', 'string', 'max:5000'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $this->service->log($subject, $data);

        return back()->with('success', 'Activity logged.');
    }

    public function destroy(Request $request, Activity $activity): RedirectResponse
    {
        abort_unless($activity->company_id === $request->user()->currentCompany->id, 403);

        $activity->delete();

        return back()->with('success', 'Activity removed.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(Request $request): Response
    {
        $company = $request->user()->currentCompany;
        $filter  = $request->query('filter', 'open');

        $tasks = $company->tasks()
            ->with(['assignee:id,name', 'related'])
            ->when($filter === 'open', fn ($q) => $q->whereNull('completed_at'))
            ->when($filter === 'completed', fn ($q) => $q->whereNotNull('completed_at'))
            ->orderByRaw('due_date is null, due_date asc')
            ->latest('id')
            ->get()
            ->map(fn (Task $t) => [
                'id'           => $t->id,
                'title'        => $t->title,
                'notes'        => $t->notes,
                'due_date'     => $t->due_date?->toDateString(),
                'completed_at' => $t->completed_at?->toIso8601String(),
                'assignee'     => $t->assignee?->only('id', 'name'),
                'related_name' => $t->related instanceof Contact ? $t->related->name : null,
                'related_id'   => $t->related instanceof Contact ? $t->related->id : null,
                'is_overdue'   => $t->completed_at === null && $t->due_date !== null && $t->due_date->isPast(),
            ]);

        return Inertia::render('tasks/Index', [
            'tasks'   => $tasks,
            'filter'  => $filter,
            'members' => $company->allUsers()->map->only('id', 'name')->values(),
            'counts'  => [
                'open'      => $company->tasks()->whereNull('completed_at')->count(),
                'overdue'   => $company->tasks()->overdue()->count(),
                'completed' => $company->tasks()->whereNotNull('completed_at')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $request->user()->currentCompany;

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'notes'       => ['nullable', 'string', 'max:2000'],
            'due_date'    => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer'],
            'contact_id'  => ['nullable', 'integer'],
            'opportunity_id' => ['nullable', 'integer'],
        ]);

        $task = new Task([
            'title'       => $data['title'],
            'notes'       => $data['notes'] ?? null,
            'due_date'    => $data['due_date'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'created_by'  => $request->user()->id,
        ]);
        $task->company_id = $company->id;

        if (! empty($data['contact_id'])) {
            $contact = Contact::where('company_id', $company->id)->findOrFail($data['contact_id']);
            $task->related()->associate($contact);
        } elseif (! empty($data['opportunity_id'])) {
            $opportunity = \App\Models\Opportunity::where('company_id', $company->id)->findOrFail($data['opportunity_id']);
            $task->related()->associate($opportunity);
        }

        $task->save();

        return back()->with('success', 'Task added.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorise($request, $task);

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'notes'       => ['nullable', 'string', 'max:2000'],
            'due_date'    => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer'],
        ]);

        $task->update($data);

        return back()->with('success', 'Task updated.');
    }

    public function complete(Request $request, Task $task): RedirectResponse
    {
        $this->authorise($request, $task);

        $task->update(['completed_at' => $task->completed_at ? null : now()]);

        return back()->with('success', $task->completed_at ? 'Task completed.' : 'Task reopened.');
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $this->authorise($request, $task);
        $task->delete();

        return back()->with('success', 'Task deleted.');
    }

    private function authorise(Request $request, Task $task): void
    {
        abort_unless($task->company_id === $request->user()->currentCompany->id, 403);
    }
}

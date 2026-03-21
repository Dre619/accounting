<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $company = $request->user()->currentCompany;
        $search  = $request->query('search', '');

        $employees = $company->employees()
            ->when($search, fn ($q) =>
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name',  'like', "%{$search}%")
                  ->orWhere('employee_number', 'like', "%{$search}%"))
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('employees/Index', [
            'employees' => $employees,
            'search'    => $search,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('employees/Form', ['employee' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data    = $this->validated($request);
        $company = $request->user()->currentCompany;

        $data['employee_number'] = $data['employee_number']
            ?? 'EMP-' . str_pad($company->employees()->count() + 1, 4, '0', STR_PAD_LEFT);
        $data['created_by'] = $request->user()->id;

        $company->employees()->create($data);

        return redirect()->route('employees.index')->with('success', 'Employee added.');
    }

    public function edit(Request $request, Employee $employee): Response
    {
        $this->authorise($request, $employee);

        return Inertia::render('employees/Form', ['employee' => $employee]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorise($request, $employee);
        $employee->update($this->validated($request));

        return redirect()->route('employees.index')->with('success', 'Employee updated.');
    }

    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorise($request, $employee);
        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'Employee deleted.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function validated(Request $request): array
    {
        return $request->validate([
            'employee_number'  => ['nullable', 'string', 'max:20'],
            'first_name'       => ['required', 'string', 'max:100'],
            'last_name'        => ['required', 'string', 'max:100'],
            'job_title'        => ['nullable', 'string', 'max:150'],
            'department'       => ['nullable', 'string', 'max:100'],
            'employment_type'  => ['required', 'in:full_time,part_time,contract'],
            'basic_salary'     => ['required', 'numeric', 'min:0'],
            'hire_date'        => ['required', 'date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'tpin'             => ['nullable', 'string', 'max:10'],
            'napsa_number'     => ['nullable', 'string', 'max:20'],
            'nhima_number'     => ['nullable', 'string', 'max:20'],
            'email'            => ['nullable', 'email', 'max:150'],
            'phone'            => ['nullable', 'string', 'max:20'],
            'bank_name'        => ['nullable', 'string', 'max:100'],
            'bank_account'     => ['nullable', 'string', 'max:30'],
            'bank_branch'      => ['nullable', 'string', 'max:100'],
            'is_active'        => ['boolean'],
        ]);
    }

    private function authorise(Request $request, Employee $employee): void
    {
        abort_unless($employee->company_id === $request->user()->currentCompany->id, 403);
    }
}

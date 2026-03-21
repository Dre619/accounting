<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $service) {}

    public function index(Request $request): Response
    {
        $company = $request->user()->currentCompany;

        $runs = $company->payrollRuns()
            ->latest('period')
            ->paginate(20);

        return Inertia::render('payroll/Index', [
            'runs'           => $runs,
            'employeeCount'  => $company->employees()->active()->count(),
        ]);
    }

    public function create(Request $request): Response
    {
        $company       = $request->user()->currentCompany;
        $defaultPeriod = now()->format('Y-m');

        return Inertia::render('payroll/Create', [
            'defaultPeriod'  => $defaultPeriod,
            'employeeCount'  => $company->employees()->active()->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'notes'  => ['nullable', 'string', 'max:1000'],
        ]);

        $company = $request->user()->currentCompany;

        if ($company->payrollRuns()->where('period', $data['period'])->exists()) {
            return back()->withErrors(['period' => "A payroll run for {$data['period']} already exists."]);
        }

        $periodStart = Carbon::parse($data['period'] . '-01');
        $periodEnd   = $periodStart->copy()->endOfMonth();

        $run = $company->payrollRuns()->create([
            'period'       => $data['period'],
            'period_start' => $periodStart->toDateString(),
            'period_end'   => $periodEnd->toDateString(),
            'notes'        => $data['notes'] ?? null,
            'processed_by' => $request->user()->id,
        ]);

        $this->service->process($run);

        return redirect()->route('payroll.show', $run)
            ->with('success', "Payroll for {$data['period']} processed.");
    }

    public function show(Request $request, PayrollRun $payroll): Response
    {
        $this->authorise($request, $payroll);

        $payroll->load([
            'payslips.employee',
            'processedBy:id,name',
            'approvedBy:id,name',
        ]);

        $company = $request->user()->currentCompany;

        return Inertia::render('payroll/Show', [
            'run'     => $payroll,
            'company' => $company->only('name', 'address', 'city', 'tpin'),
        ]);
    }

    public function approve(Request $request, PayrollRun $payroll): RedirectResponse
    {
        $this->authorise($request, $payroll);
        $this->service->approve($payroll, $request->user()->id);

        return back()->with('success', "Payroll {$payroll->period} approved and posted to journal.");
    }

    public function destroy(Request $request, PayrollRun $payroll): RedirectResponse
    {
        $this->authorise($request, $payroll);
        abort_unless($payroll->status === 'draft', 422, 'Only draft payroll runs can be deleted.');

        $payroll->delete();

        return redirect()->route('payroll.index')->with('success', 'Payroll run deleted.');
    }

    // ── Payslip print ─────────────────────────────────────────────────────────

    public function printPayslip(Request $request, PayrollRun $payroll, \App\Models\Payslip $payslip): \Illuminate\Contracts\View\View
    {
        $this->authorise($request, $payroll);
        abort_unless($payslip->payroll_run_id === $payroll->id, 404);

        $payslip->load('employee');
        $company = $request->user()->currentCompany;

        return view('payroll.payslip', compact('payslip', 'payroll', 'company'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function authorise(Request $request, PayrollRun $payroll): void
    {
        abort_unless($payroll->company_id === $request->user()->currentCompany->id, 403);
    }
}

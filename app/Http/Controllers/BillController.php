<?php

namespace App\Http\Controllers;

use App\Jobs\SubmitZraPurchaseJob;
use App\Models\Bill;
use App\Models\GoodsCode;
use App\Models\ServiceCode;
use App\Services\BillService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillController extends Controller
{
    public function __construct(private readonly BillService $service) {}

    public function index(Request $request): Response
    {
        $company = $request->user()->currentCompany;
        $status  = $request->query('status', 'all');
        $search  = $request->query('search', '');

        $bills = $company->bills()
            ->with('contact:id,name')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) =>
                $q->where('bill_number', 'like', "%{$search}%")
                  ->orWhereHas('contact', fn ($c) => $c->where('name', 'like', "%{$search}%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = $company->bills()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return Inertia::render('bills/Index', [
            'bills'         => $bills,
            'currentStatus' => $status,
            'search'        => $search,
            'counts'        => $counts,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('bills/Form', $this->formData($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $bill = $this->service->store($request->user()->currentCompany, $data);

        return redirect()->route('bills.show', $bill)
            ->with('success', "Bill created.");
    }

    public function show(Request $request, Bill $bill): Response
    {
        $this->authorise($request, $bill);

        $bill->load([
            'contact',
            'items.taxRate',
            'items.account:id,name,code',
            'items.goodsCode:id,name,hs_code',
            'items.serviceCode:id,name,hs_code',
            'createdBy:id,name',
        ]);

        return Inertia::render('bills/Show', [
            'bill'    => $bill,
            'company' => $request->user()->currentCompany->only('name', 'address', 'city', 'tpin'),
        ]);
    }

    public function edit(Request $request, Bill $bill): Response
    {
        $this->authorise($request, $bill);
        abort_unless($bill->status === 'draft', 422, 'Only draft bills can be edited.');

        $bill->load(['items.taxRate', 'items.account']);

        return Inertia::render('bills/Form', array_merge(
            $this->formData($request),
            ['bill' => $bill]
        ));
    }

    public function update(Request $request, Bill $bill): RedirectResponse
    {
        $this->authorise($request, $bill);
        $bill = $this->service->update($bill, $this->validated($request));

        return redirect()->route('bills.show', $bill)->with('success', 'Bill updated.');
    }

    public function approve(Request $request, Bill $bill): RedirectResponse
    {
        $this->authorise($request, $bill);
        $this->service->approve($bill);

        return back()->with('success', 'Bill approved and posted to accounts.');
    }

    public function void(Request $request, Bill $bill): RedirectResponse
    {
        $this->authorise($request, $bill);
        $this->service->void($bill);

        return back()->with('success', 'Bill voided.');
    }

    public function print(Request $request, Bill $bill): \Illuminate\Contracts\View\View
    {
        $this->authorise($request, $bill);
        $bill->load(['contact', 'items.taxRate', 'items.account:id,name,code']);
        $company = $request->user()->currentCompany;

        return view('bills.print', compact('bill', 'company'));
    }

    public function submitZra(Request $request, Bill $bill): RedirectResponse
    {
        $this->authorise($request, $bill);
        abort_unless(in_array($bill->status, ['approved', 'partial', 'paid']), 422, 'Only approved bills can be submitted to ZRA.');
        abort_if((bool) $bill->zra_submitted_at, 422, 'This bill has already been submitted to ZRA.');

        SubmitZraPurchaseJob::dispatch($bill);

        return back()->with('success', 'Bill queued for ZRA submission.');
    }

    public function destroy(Request $request, Bill $bill): RedirectResponse
    {
        $this->authorise($request, $bill);
        abort_unless($bill->status === 'draft', 422, 'Only draft bills can be deleted.');
        $bill->delete();

        return redirect()->route('bills.index')->with('success', 'Bill deleted.');
    }

    private function formData(Request $request): array
    {
        $company = $request->user()->currentCompany;

        return [
            'bill'         => null,
            'contacts'     => $company->contacts()->suppliers()->active()
                ->orderBy('name')->get(['id', 'name', 'email', 'tpin']),
            'accounts'     => $company->accounts()->active()->ofType('expense')
                ->orderBy('code')->get(['id', 'code', 'name']),
            'taxRates'     => $company->taxRates()->active()->vat()
                ->orderBy('name')->get(['id', 'name', 'code', 'rate']),
            'vsdcEnabled'  => (bool) $company->vsdc_initialized,
            'goodsCodes'   => GoodsCode::orderBy('name')->get(['id', 'name', 'hs_code']),
            'serviceCodes' => ServiceCode::orderBy('name')->get(['id', 'name', 'hs_code']),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'contact_id'      => ['required', 'integer'],
            'bill_number'     => ['nullable', 'string', 'max:50'],
            'reference'       => ['nullable', 'string', 'max:100'],
            'issue_date'      => ['required', 'date'],
            'due_date'        => ['required', 'date', 'after_or_equal:issue_date'],
            'notes'           => ['nullable', 'string', 'max:2000'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items'                        => ['required', 'array', 'min:1'],
            'items.*.id'               => ['nullable', 'integer'],
            'items.*.description'      => ['required', 'string', 'max:255'],
            'items.*.account_id'       => ['nullable', 'integer'],
            'items.*.tax_rate_id'      => ['nullable', 'integer'],
            'items.*.quantity'         => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price'       => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.item_type'        => ['nullable', 'in:goods,service'],
            'items.*.cls_code_id'      => ['nullable', 'integer'],
        ]);
    }

    private function authorise(Request $request, Bill $bill): void
    {
        abort_unless($bill->company_id === $request->user()->currentCompany->id, 403);
    }
}

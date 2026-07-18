<?php

namespace App\Http\Controllers;

use App\Models\GoodsCode;
use App\Models\Product;
use App\Models\ServiceCode;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(private readonly StockService $stock) {}

    public function index(Request $request): Response
    {
        $company = $request->user()->currentCompany;
        $type    = $request->query('type', 'all');
        $search  = $request->query('search', '');
        $lowOnly = $request->boolean('low_stock');

        $products = $company->products()
            ->when($type !== 'all', fn ($q) => $q->where('type', $type))
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%"))
            ->when($lowOnly, fn ($q) => $q->where('type', 'inventory')
                ->whereNotNull('reorder_point')
                ->whereColumn('quantity_on_hand', '<=', 'reorder_point'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('products/Index', [
            'products'    => $products,
            'currentType' => $type,
            'search'      => $search,
            'lowStock'    => $lowOnly,
            'counts'      => [
                'all'       => $company->products()->count(),
                'inventory' => $company->products()->where('type', 'inventory')->count(),
                'service'   => $company->products()->where('type', 'service')->count(),
                'low_stock' => $company->products()->where('type', 'inventory')
                    ->whereNotNull('reorder_point')
                    ->whereColumn('quantity_on_hand', '<=', 'reorder_point')
                    ->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('products/Form', array_merge($this->formData($request), ['product' => null]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data    = $this->validated($request);
        $company = $request->user()->currentCompany;

        $opening = [
            'quantity' => (float) ($data['opening_quantity'] ?? 0),
            'cost'     => (float) ($data['opening_cost'] ?? 0),
        ];
        unset($data['opening_quantity'], $data['opening_cost']);

        $product = $company->products()->create($data);

        if ($product->tracksStock() && $opening['quantity'] > 0) {
            $this->stock->openingStock($product, $opening['quantity'], $opening['cost']);
        }

        return redirect()->route('products.show', $product)
            ->with('success', 'Product created.');
    }

    public function show(Request $request, Product $product): Response
    {
        $this->authorise($request, $product);

        $product->load(['salesAccount:id,code,name', 'purchaseAccount:id,code,name',
            'inventoryAccount:id,code,name', 'cogsAccount:id,code,name', 'taxRate:id,name,rate']);

        $movements = $product->stockMovements()
            ->with('warehouse:id,name')
            ->latest('movement_date')->latest('id')
            ->limit(50)->get();

        return Inertia::render('products/Show', [
            'product'   => $product,
            'movements' => $movements,
            'stockValue' => round((float) $product->quantity_on_hand * (float) $product->average_cost, 2),
        ]);
    }

    public function edit(Request $request, Product $product): Response
    {
        $this->authorise($request, $product);

        return Inertia::render('products/Form', array_merge($this->formData($request), ['product' => $product]));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorise($request, $product);

        $data = $this->validated($request, $product);
        unset($data['opening_quantity'], $data['opening_cost']); // opening stock only on create

        $product->update($data);

        return redirect()->route('products.show', $product)
            ->with('success', 'Product updated.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorise($request, $product);

        // Preserve history — deactivate items that already have stock movements.
        if ($product->stockMovements()->exists()) {
            $product->update(['is_active' => false]);

            return redirect()->route('products.index')
                ->with('success', 'Product has stock history and was deactivated instead of deleted.');
        }

        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product deleted.');
    }

    public function adjust(Request $request, Product $product): RedirectResponse
    {
        $this->authorise($request, $product);
        abort_unless($product->tracksStock(), 422, 'Only inventory products can be adjusted.');

        $data = $request->validate([
            'new_quantity' => ['required', 'numeric', 'min:0'],
            'reason'       => ['nullable', 'string', 'max:255'],
            'date'         => ['nullable', 'date'],
        ]);

        $this->stock->adjustStock($product, (float) $data['new_quantity'], [
            'description' => $data['reason'] ?? 'Stock adjustment',
            'date'        => $data['date'] ?? null,
        ]);

        return redirect()->route('products.show', $product)
            ->with('success', 'Stock adjusted.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function formData(Request $request): array
    {
        $company = $request->user()->currentCompany;

        return [
            'accounts'     => $company->accounts()->active()
                ->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'taxRates'     => $company->taxRates()->active()->vat()
                ->orderBy('name')->get(['id', 'name', 'code', 'rate']),
            'goodsCodes'   => GoodsCode::orderBy('name')->get(['id', 'name', 'hs_code']),
            'serviceCodes' => ServiceCode::orderBy('name')->get(['id', 'name', 'hs_code']),
        ];
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $company = $request->user()->currentCompany;

        return $request->validate([
            'name'                 => ['required', 'string', 'max:150'],
            'sku'                  => ['nullable', 'string', 'max:60',
                Rule::unique('products', 'sku')->where('company_id', $company->id)->ignore($product?->id)],
            'description'          => ['nullable', 'string', 'max:1000'],
            'type'                 => ['required', 'in:inventory,service,non_inventory'],
            'unit_of_measure'      => ['nullable', 'string', 'max:20'],
            'sales_price'          => ['nullable', 'numeric', 'min:0'],
            'sales_account_id'     => ['nullable', 'integer'],
            'purchase_account_id'  => ['nullable', 'integer'],
            'inventory_account_id' => ['nullable', 'integer'],
            'cogs_account_id'      => ['nullable', 'integer'],
            'tax_rate_id'          => ['nullable', 'integer'],
            'item_type'            => ['nullable', 'in:goods,service'],
            'cls_code_id'          => ['nullable', 'integer'],
            'reorder_point'        => ['nullable', 'numeric', 'min:0'],
            'is_active'            => ['boolean'],
            'opening_quantity'     => ['nullable', 'numeric', 'min:0'],
            'opening_cost'         => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function authorise(Request $request, Product $product): void
    {
        abort_unless($product->company_id === $request->user()->currentCompany->id, 403);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\BillService;
use App\Services\CompanyProvisioningService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryValuationReportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\AccountingSeeder::class);

        $this->user = User::factory()->create();
        $this->company = Company::create([
            'user_id' => $this->user->id, 'name' => 'Val Co', 'currency' => 'ZMW',
            'invoice_prefix' => 'INV', 'invoice_sequence' => 1,
        ]);
        $this->user->forceFill(['current_company_id' => $this->company->id])->save();

        app(CompanyProvisioningService::class)->provision($this->company);
        Warehouse::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_default' => true, 'is_active' => true]);

        $this->actingAs($this->user);
    }

    private function accountId(string $code): int
    {
        return (int) Account::where('company_id', $this->company->id)->where('code', $code)->value('id');
    }

    private function product(string $name): Product
    {
        return $this->company->products()->create([
            'name' => $name, 'type' => 'inventory', 'is_active' => true,
            'inventory_account_id' => $this->accountId('1300'),
            'cogs_account_id' => $this->accountId('5000'),
        ]);
    }

    public function test_report_sums_stock_value_at_average_cost(): void
    {
        $a = $this->product('A');
        $b = $this->product('B');
        app(StockService::class)->receiveStock($a, 10, 5.00); // value 50
        app(StockService::class)->receiveStock($b, 4, 7.50);  // value 30

        $this->get('/reports/inventory-valuation')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reports/InventoryValuation')
                ->where('totalValue', 80)
                ->has('rows', 2));
    }

    public function test_valuation_reconciles_to_the_gl_after_a_posted_purchase(): void
    {
        // A bill that receives stock posts DR Inventory 1300, so the ledger and
        // the product cache should agree — zero variance.
        $product = $this->product('Widget');
        $supplier = $this->company->contacts()->create(['name' => 'Acme', 'type' => 'supplier']);

        $bills = app(BillService::class);
        $bill = $bills->store($this->company, [
            'contact_id' => $supplier->id,
            'issue_date' => '2026-07-01',
            'due_date'   => '2026-07-31',
            'items'      => [[
                'description' => 'Widgets', 'product_id' => $product->id,
                'quantity' => 20, 'unit_price' => 4,
            ]],
        ]);
        $bills->approve($bill);

        $this->get('/reports/inventory-valuation')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('totalValue', 80)
                ->where('glBalance', 80)
                ->where('variance', 0));
    }
}

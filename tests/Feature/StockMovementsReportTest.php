<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CompanyProvisioningService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementsReportTest extends TestCase
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
            'user_id' => $this->user->id, 'name' => 'Move Co', 'currency' => 'ZMW',
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

    public function test_report_lists_movements_and_totals_in_and_out(): void
    {
        $stock = app(StockService::class);
        $p = $this->product('Widget');
        $stock->receiveStock($p, 10, 5.00, ['date' => now()->toDateString()]); // +10, value 50
        $stock->issueStock($p, 4, ['date' => now()->toDateString()]);           // -4,  value -20

        $this->get('/reports/stock-movements')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reports/StockMovements')
                ->has('movements', 2)
                ->where('totals.qty_in', 10)
                ->where('totals.qty_out', -4)
                ->where('totals.value_in', 50)
                ->where('totals.value_out', -20));
    }

    public function test_type_filter_narrows_results(): void
    {
        $stock = app(StockService::class);
        $p = $this->product('Widget');
        $stock->receiveStock($p, 10, 5.00);
        $stock->issueStock($p, 2);

        $this->get('/reports/stock-movements?type=sale')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('movements', 1)
                ->where('movements.0.type', 'sale'));
    }
}

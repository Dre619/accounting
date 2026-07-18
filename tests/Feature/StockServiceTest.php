<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalLine;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CompanyProvisioningService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\AccountingSeeder::class);

        $user = User::factory()->create();
        $this->company = Company::create([
            'user_id'  => $user->id,
            'name'     => 'Stock Co',
            'currency' => 'ZMW',
        ]);

        app(CompanyProvisioningService::class)->provision($this->company);

        Warehouse::create([
            'company_id' => $this->company->id,
            'name'       => 'Main Warehouse',
            'code'       => 'MAIN',
            'is_default' => true,
            'is_active'  => true,
        ]);

        $this->stock = app(StockService::class);
    }

    private function product(array $attrs = []): Product
    {
        return $this->company->products()->create(array_merge([
            'name'        => 'Widget',
            'type'        => 'inventory',
            'sales_price' => 100,
            'is_active'   => true,
        ], $attrs));
    }

    private function accountId(string $code): int
    {
        return (int) Account::where('company_id', $this->company->id)->where('code', $code)->value('id');
    }

    public function test_receiving_stock_sets_quantity_and_cost(): void
    {
        $product = $this->product();

        $movement = $this->stock->receiveStock($product, 10, 5.00);

        $this->assertEquals(10.0, (float) $product->fresh()->quantity_on_hand);
        $this->assertEquals(5.0, (float) $product->fresh()->average_cost);
        $this->assertEquals('purchase', $movement->type);
        $this->assertEquals(10.0, (float) $movement->quantity);
        $this->assertEquals(50.0, (float) $movement->total_cost);
        $this->assertEquals(10.0, (float) $movement->running_qty);
    }

    public function test_second_receipt_recomputes_weighted_average(): void
    {
        $product = $this->product();

        $this->stock->receiveStock($product, 10, 5.00); // 10 @ 5
        $this->stock->receiveStock($product, 10, 7.00); // + 10 @ 7 → avg 6

        $product->refresh();
        $this->assertEquals(20.0, (float) $product->quantity_on_hand);
        $this->assertEquals(6.0, (float) $product->average_cost, 'Weighted avg = (10*5 + 10*7)/20');
    }

    public function test_issuing_stock_uses_average_cost_and_leaves_it_unchanged(): void
    {
        $product = $this->product();
        $this->stock->receiveStock($product, 10, 5.00);
        $this->stock->receiveStock($product, 10, 7.00); // avg 6, qty 20

        $movement = $this->stock->issueStock($product, 5); // COGS 5 * 6 = 30

        $product->refresh();
        $this->assertEquals(15.0, (float) $product->quantity_on_hand);
        $this->assertEquals(6.0, (float) $product->average_cost, 'Issue does not move average cost');
        $this->assertEquals('sale', $movement->type);
        $this->assertEquals(-5.0, (float) $movement->quantity);
        $this->assertEquals(-30.0, (float) $movement->total_cost, 'COGS = qty * avg');
    }

    public function test_weighted_average_survives_receive_issue_receive(): void
    {
        $product = $this->product();
        $this->stock->receiveStock($product, 10, 5.00); // qty 10, avg 5
        $this->stock->issueStock($product, 4);          // qty 6,  avg 5
        $this->stock->receiveStock($product, 4, 8.00);  // qty 10, avg (6*5 + 4*8)/10 = 6.2

        $product->refresh();
        $this->assertEquals(10.0, (float) $product->quantity_on_hand);
        $this->assertEquals(6.2, (float) $product->average_cost);
    }

    public function test_upward_adjustment_posts_balanced_entry_debiting_inventory(): void
    {
        $product = $this->product([
            'inventory_account_id' => $this->accountId('1300'),
            'cogs_account_id'      => $this->accountId('5000'),
        ]);
        $this->stock->receiveStock($product, 10, 5.00); // avg 5, qty 10

        $movement = $this->stock->adjustStock($product, 12); // +2 @ 5 = 10.00

        $this->assertEquals(12.0, (float) $product->fresh()->quantity_on_hand);
        $this->assertEquals('adjustment', $movement->type);
        $this->assertEquals(2.0, (float) $movement->quantity);
        $this->assertNotNull($movement->journal_entry_id);

        $lines = JournalLine::where('journal_entry_id', $movement->journal_entry_id)->get();
        $this->assertEqualsWithDelta(10.0, (float) $lines->sum('debit'), 0.001);
        $this->assertEqualsWithDelta(10.0, (float) $lines->sum('credit'), 0.001);

        // Surplus debits Inventory (1300).
        $invLine = $lines->firstWhere('account_id', $this->accountId('1300'));
        $this->assertEquals(10.0, (float) $invLine->debit);
        $this->assertEquals(0.0, (float) $invLine->credit);
    }

    public function test_downward_adjustment_credits_inventory(): void
    {
        $product = $this->product([
            'inventory_account_id' => $this->accountId('1300'),
            'cogs_account_id'      => $this->accountId('5000'),
        ]);
        $this->stock->receiveStock($product, 10, 5.00);

        $movement = $this->stock->adjustStock($product, 7); // -3 @ 5 = 15.00 shrinkage

        $this->assertEquals(7.0, (float) $product->fresh()->quantity_on_hand);
        $this->assertEquals(-3.0, (float) $movement->quantity);

        $lines = JournalLine::where('journal_entry_id', $movement->journal_entry_id)->get();
        $invLine = $lines->firstWhere('account_id', $this->accountId('1300'));
        $this->assertEquals(15.0, (float) $invLine->credit, 'Shrinkage credits Inventory');
        $cogsLine = $lines->firstWhere('account_id', $this->accountId('5000'));
        $this->assertEquals(15.0, (float) $cogsLine->debit, 'Shrinkage debits COGS');
    }

    public function test_movement_records_running_snapshot_and_default_warehouse(): void
    {
        $product = $this->product();
        $movement = $this->stock->receiveStock($product, 8, 4.00);

        $this->assertEquals(8.0, (float) $movement->running_qty);
        $this->assertEquals(4.0, (float) $movement->running_avg_cost);
        $this->assertNotNull($movement->warehouse_id, 'Falls back to the company default warehouse');
    }

    public function test_non_inventory_product_rejects_stock_movement(): void
    {
        $service = $this->product(['type' => 'service']);

        $this->expectException(HttpException::class);
        $this->stock->receiveStock($service, 5, 3.00);
    }

    public function test_zero_or_negative_receive_is_rejected(): void
    {
        $product = $this->product();

        $this->expectException(HttpException::class);
        $this->stock->receiveStock($product, 0, 3.00);
    }

    public function test_adjustment_to_same_quantity_is_rejected(): void
    {
        $product = $this->product();
        $this->stock->receiveStock($product, 5, 2.00);

        $this->expectException(HttpException::class);
        $this->stock->adjustStock($product, 5);
    }
}

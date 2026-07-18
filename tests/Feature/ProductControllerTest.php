<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalLine;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CompanyProvisioningService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
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
            'user_id' => $this->user->id, 'name' => 'Stock Co', 'currency' => 'ZMW',
            'invoice_prefix' => 'INV', 'invoice_sequence' => 1,
        ]);
        $this->user->forceFill(['current_company_id' => $this->company->id])->save();

        app(CompanyProvisioningService::class)->provision($this->company); // sets 14-day trial
        Warehouse::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_default' => true, 'is_active' => true]);

        $this->actingAs($this->user);
    }

    private function accountId(string $code): int
    {
        return (int) Account::where('company_id', $this->company->id)->where('code', $code)->value('id');
    }

    public function test_creating_an_inventory_product_with_opening_stock_posts_opening_entry(): void
    {
        $this->post('/products', [
            'name'             => 'Bolt',
            'type'             => 'inventory',
            'sales_price'      => 12,
            'item_type'        => 'goods',
            'is_active'        => true,
            'opening_quantity' => 10,
            'opening_cost'     => 5,
        ])->assertRedirect();

        $product = Product::where('company_id', $this->company->id)->firstOrFail();
        $this->assertEquals(10.0, (float) $product->quantity_on_hand);
        $this->assertEquals(5.0, (float) $product->average_cost);

        $entry = $this->company->journalEntries()->where('source', 'opening')->firstOrFail();
        $lines = JournalLine::where('journal_entry_id', $entry->id)->get();
        $this->assertEquals(50.0, (float) $lines->firstWhere('account_id', $this->accountId('1300'))->debit);
        $this->assertEquals(50.0, (float) $lines->firstWhere('account_id', $this->accountId('3100'))->credit);
    }

    public function test_adjusting_stock_updates_quantity_on_hand(): void
    {
        $product = $this->company->products()->create([
            'name' => 'Widget', 'type' => 'inventory', 'is_active' => true,
            'inventory_account_id' => $this->accountId('1300'),
            'cogs_account_id' => $this->accountId('5000'),
        ]);
        app(StockService::class)->receiveStock($product, 20, 3.00);

        $this->post("/products/{$product->id}/adjust", [
            'new_quantity' => 18,
            'reason'       => 'Stock take',
        ])->assertRedirect();

        $this->assertEquals(18.0, (float) $product->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'adjustment']);
    }

    public function test_low_stock_filter_returns_only_products_at_or_below_reorder_point(): void
    {
        $low = $this->company->products()->create([
            'name' => 'Low', 'type' => 'inventory', 'is_active' => true,
            'quantity_on_hand' => 2, 'reorder_point' => 5,
        ]);
        $ok = $this->company->products()->create([
            'name' => 'Plenty', 'type' => 'inventory', 'is_active' => true,
            'quantity_on_hand' => 40, 'reorder_point' => 5,
        ]);

        $this->get('/products?low_stock=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('products/Index')
                ->where('counts.low_stock', 1)
                ->where('products.data', fn ($rows) => collect($rows)->pluck('id')->contains($low->id)
                    && ! collect($rows)->pluck('id')->contains($ok->id))
            );
    }

    public function test_deleting_a_product_with_history_deactivates_instead(): void
    {
        $product = $this->company->products()->create([
            'name' => 'Historic', 'type' => 'inventory', 'is_active' => true,
            'inventory_account_id' => $this->accountId('1300'), 'cogs_account_id' => $this->accountId('5000'),
        ]);
        app(StockService::class)->receiveStock($product, 5, 2.00);

        $this->delete("/products/{$product->id}")->assertRedirect();

        $product->refresh();
        $this->assertFalse((bool) $product->is_active);
        $this->assertNull($product->deleted_at, 'Kept, not soft-deleted, because it has stock history');
    }
}

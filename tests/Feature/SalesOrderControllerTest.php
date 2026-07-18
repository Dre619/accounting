<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CompanyProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderControllerTest extends TestCase
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
            'user_id' => $this->user->id, 'name' => 'SO Co', 'currency' => 'ZMW',
            'invoice_prefix' => 'INV', 'invoice_sequence' => 1,
        ]);
        $this->user->forceFill(['current_company_id' => $this->company->id])->save();

        app(CompanyProvisioningService::class)->provision($this->company);
        Warehouse::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_default' => true, 'is_active' => true]);

        $this->actingAs($this->user);
    }

    private function customer(): Contact
    {
        return $this->company->contacts()->create(['name' => 'Buyer', 'type' => 'customer']);
    }

    private function payload(): array
    {
        return [
            'contact_id' => $this->customer()->id,
            'order_date' => '2026-07-01',
            'items'      => [[
                'description' => 'Consulting',
                'account_id'  => (int) $this->company->accounts()->where('type', 'income')->value('id'),
                'quantity'    => 2,
                'unit_price'  => 150,
            ]],
        ];
    }

    public function test_can_create_a_sales_order(): void
    {
        $this->post('/sales-orders', $this->payload())->assertRedirect();

        $order = SalesOrder::where('company_id', $this->company->id)->firstOrFail();
        $this->assertEquals('SO-0001', $order->order_number);
        $this->assertEquals(300.0, (float) $order->total);
    }

    public function test_convert_endpoint_creates_invoice_and_redirects(): void
    {
        $this->post('/sales-orders', $this->payload());
        $order = SalesOrder::where('company_id', $this->company->id)->firstOrFail();

        $this->post("/sales-orders/{$order->id}/convert")->assertRedirect();

        $order->refresh();
        $this->assertEquals('invoiced', $order->status);
        $this->assertDatabaseHas('invoices', ['sales_order_id' => $order->id, 'company_id' => $this->company->id]);
    }

    public function test_partial_convert_via_endpoint_leaves_order_partial(): void
    {
        $this->post('/sales-orders', $this->payload()); // 1 line, qty 2
        $order = SalesOrder::where('company_id', $this->company->id)->firstOrFail();
        $itemId = $order->items->first()->id;

        $this->post("/sales-orders/{$order->id}/convert", ['lines' => [$itemId => 1]])
            ->assertRedirect();

        $order->refresh();
        $this->assertEquals('partial', $order->status);
        $this->assertEquals(1.0, (float) $order->items->first()->quantity_invoiced);
    }

    public function test_index_loads(): void
    {
        $this->post('/sales-orders', $this->payload());

        $this->get('/sales-orders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('sales-orders/Index')->where('orders.total', 1));
    }

    public function test_billed_order_cannot_be_deleted(): void
    {
        $this->post('/sales-orders', $this->payload());
        $order = SalesOrder::where('company_id', $this->company->id)->firstOrFail();

        $this->post("/sales-orders/{$order->id}/convert");
        $this->delete("/sales-orders/{$order->id}")->assertStatus(422);
    }
}

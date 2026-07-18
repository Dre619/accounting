<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CompanyProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderControllerTest extends TestCase
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
            'user_id' => $this->user->id, 'name' => 'PO Co', 'currency' => 'ZMW',
            'invoice_prefix' => 'INV', 'invoice_sequence' => 1,
        ]);
        $this->user->forceFill(['current_company_id' => $this->company->id])->save();

        app(CompanyProvisioningService::class)->provision($this->company);
        Warehouse::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_default' => true, 'is_active' => true]);

        $this->actingAs($this->user);
    }

    private function supplier(): Contact
    {
        return $this->company->contacts()->create(['name' => 'Acme', 'type' => 'supplier']);
    }

    private function storePayload(): array
    {
        return [
            'contact_id' => $this->supplier()->id,
            'order_date' => '2026-07-01',
            'items'      => [[
                'description' => 'Widgets',
                'quantity'    => 4,
                'unit_price'  => 25,
            ]],
        ];
    }

    public function test_can_create_a_purchase_order(): void
    {
        $this->post('/purchase-orders', $this->storePayload())->assertRedirect();

        $po = PurchaseOrder::where('company_id', $this->company->id)->firstOrFail();
        $this->assertEquals('PO-0001', $po->po_number);
        $this->assertEquals(100.0, (float) $po->total);
    }

    public function test_convert_endpoint_creates_bill_and_redirects_to_it(): void
    {
        $this->post('/purchase-orders', $this->storePayload());
        $po = PurchaseOrder::where('company_id', $this->company->id)->firstOrFail();

        $this->post("/purchase-orders/{$po->id}/convert")
            ->assertRedirect();

        $po->refresh();
        $this->assertEquals('billed', $po->status);
        $this->assertDatabaseHas('bills', ['purchase_order_id' => $po->id, 'company_id' => $this->company->id]);
    }

    public function test_partial_convert_via_endpoint_leaves_order_partial(): void
    {
        $this->post('/purchase-orders', $this->storePayload()); // 1 line, qty 4
        $po = PurchaseOrder::where('company_id', $this->company->id)->firstOrFail();
        $itemId = $po->items->first()->id;

        $this->post("/purchase-orders/{$po->id}/convert", ['lines' => [$itemId => 1]])
            ->assertRedirect();

        $po->refresh();
        $this->assertEquals('partial', $po->status);
        $this->assertEquals(1.0, (float) $po->items->first()->quantity_received);
    }

    public function test_index_loads(): void
    {
        $this->post('/purchase-orders', $this->storePayload());

        $this->get('/purchase-orders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('purchase-orders/Index')
                ->where('orders.total', 1));
    }

    public function test_draft_order_can_be_deleted_but_billed_cannot(): void
    {
        $this->post('/purchase-orders', $this->storePayload());
        $po = PurchaseOrder::where('company_id', $this->company->id)->firstOrFail();

        // Convert (→ billed), then deletion must be refused.
        $this->post("/purchase-orders/{$po->id}/convert");
        $this->delete("/purchase-orders/{$po->id}")->assertStatus(422);

        $this->assertDatabaseHas('purchase_orders', ['id' => $po->id, 'deleted_at' => null]);
    }
}

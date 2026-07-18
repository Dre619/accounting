<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CompanyProvisioningService;
use App\Services\PurchaseOrderService;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPdfTest extends TestCase
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
            'user_id' => $this->user->id, 'name' => 'PDF Co', 'currency' => 'ZMW',
            'invoice_prefix' => 'INV', 'invoice_sequence' => 1,
        ]);
        $this->user->forceFill(['current_company_id' => $this->company->id])->save();

        app(CompanyProvisioningService::class)->provision($this->company);
        Warehouse::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_default' => true, 'is_active' => true]);

        $this->actingAs($this->user);
    }

    private function incomeAccountId(): int
    {
        return (int) $this->company->accounts()->where('type', 'income')->value('id');
    }

    public function test_purchase_order_pdf_renders(): void
    {
        $supplier = $this->company->contacts()->create(['name' => 'Acme', 'type' => 'supplier']);
        $po = app(PurchaseOrderService::class)->store($this->company, [
            'contact_id' => $supplier->id,
            'order_date' => '2026-07-01',
            'items'      => [['description' => 'Widgets', 'quantity' => 3, 'unit_price' => 10]],
        ]);

        $response = $this->get("/purchase-orders/{$po->id}/print");

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_sales_order_pdf_renders(): void
    {
        $customer = $this->company->contacts()->create(['name' => 'Buyer', 'type' => 'customer']);
        $so = app(SalesOrderService::class)->store($this->company, [
            'contact_id' => $customer->id,
            'order_date' => '2026-07-01',
            'items'      => [['description' => 'Consulting', 'account_id' => $this->incomeAccountId(), 'quantity' => 1, 'unit_price' => 500]],
        ]);

        $response = $this->get("/sales-orders/{$so->id}/print");

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}

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
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PurchaseOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private PurchaseOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\AccountingSeeder::class);

        $user = User::factory()->create();
        $this->company = Company::create([
            'user_id' => $user->id, 'name' => 'PO Co', 'currency' => 'ZMW',
            'invoice_prefix' => 'INV', 'invoice_sequence' => 1,
        ]);
        $user->forceFill(['current_company_id' => $this->company->id])->save();

        app(CompanyProvisioningService::class)->provision($this->company);
        Warehouse::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_default' => true, 'is_active' => true]);

        $this->actingAs($user);
        $this->service = app(PurchaseOrderService::class);
    }

    private function accountId(string $code): int
    {
        return (int) Account::where('company_id', $this->company->id)->where('code', $code)->value('id');
    }

    private function supplier(): Contact
    {
        return $this->company->contacts()->create(['name' => 'Acme', 'type' => 'supplier']);
    }

    private function product(): Product
    {
        return $this->company->products()->create([
            'name' => 'Widget', 'type' => 'inventory', 'is_active' => true,
            'inventory_account_id' => $this->accountId('1300'),
            'cogs_account_id' => $this->accountId('5000'),
        ]);
    }

    private function draftPo(Product $product): \App\Models\PurchaseOrder
    {
        return $this->service->store($this->company, [
            'contact_id' => $this->supplier()->id,
            'order_date' => '2026-07-01',
            'items'      => [[
                'description' => 'Widget order',
                'product_id'  => $product->id,
                'quantity'    => 10,
                'unit_price'  => 5,
            ]],
        ]);
    }

    public function test_store_computes_totals_and_assigns_sequential_number(): void
    {
        $po = $this->draftPo($this->product());

        $this->assertEquals('PO-0001', $po->po_number);
        $this->assertEquals('draft', $po->status);
        $this->assertEquals(50.0, (float) $po->subtotal);
        $this->assertEquals(50.0, (float) $po->total);
        $this->assertCount(1, $po->items);
    }

    public function test_send_transitions_draft_to_sent(): void
    {
        $po = $this->draftPo($this->product());
        $this->service->send($po);

        $this->assertEquals('sent', $po->fresh()->status);
        $this->assertNotNull($po->fresh()->sent_at);
    }

    public function test_cannot_cancel_a_billed_order(): void
    {
        $po = $this->draftPo($this->product());
        $this->service->convertToBill($po);

        $this->expectException(HttpException::class);
        $this->service->cancel($po->fresh());
    }

    public function test_convert_to_bill_links_and_marks_billed(): void
    {
        $product = $this->product();
        $po = $this->draftPo($product);

        $bill = $this->service->convertToBill($po);

        $po->refresh();
        $this->assertEquals('billed', $po->status);
        $this->assertEquals(10.0, (float) $po->items->first()->quantity_received);
        $this->assertEquals($po->id, $bill->purchase_order_id);
        $this->assertEquals($po->po_number, $bill->reference);
        $this->assertEquals(50.0, (float) $bill->total);
    }

    public function test_approving_the_converted_bill_receives_stock(): void
    {
        $product = $this->product();
        $po = $this->draftPo($product);
        $bill = $this->service->convertToBill($po);

        app(BillService::class)->approve($bill);

        $product->refresh();
        $this->assertEquals(10.0, (float) $product->quantity_on_hand);
        $this->assertEquals(5.0, (float) $product->average_cost);
    }

    public function test_po_numbers_increment_across_orders(): void
    {
        $this->draftPo($this->product());
        $second = $this->draftPo($this->product());

        $this->assertEquals('PO-0002', $second->po_number);
    }

    public function test_partial_conversion_marks_partial_then_completes(): void
    {
        $po = $this->draftPo($this->product()); // qty 10 @ 5
        $itemId = $po->items->first()->id;

        // Bill 4 of 10 → partial.
        $first = $this->service->convertToBill($po, [$itemId => 4]);
        $po->refresh();
        $this->assertEquals('partial', $po->status);
        $this->assertEquals(4.0, (float) $po->items->first()->quantity_received);
        $this->assertEquals(20.0, (float) $first->total);

        // Bill the remaining 6 → billed.
        $second = $this->service->convertToBill($po->fresh(), [$itemId => 6]);
        $po->refresh();
        $this->assertEquals('billed', $po->status);
        $this->assertEquals(10.0, (float) $po->items->first()->quantity_received);
        $this->assertEquals(30.0, (float) $second->total);
    }

    public function test_partial_quantity_is_capped_at_outstanding(): void
    {
        $po = $this->draftPo($this->product()); // qty 10
        $itemId = $po->items->first()->id;

        $bill = $this->service->convertToBill($po, [$itemId => 25]); // ask for more than ordered
        $po->refresh();

        $this->assertEquals(10.0, (float) $po->items->first()->quantity_received);
        $this->assertEquals('billed', $po->status);
        $this->assertEquals(50.0, (float) $bill->total);
    }

    public function test_converting_nothing_is_rejected(): void
    {
        $po = $this->draftPo($this->product());
        $itemId = $po->items->first()->id;

        $this->expectException(HttpException::class);
        $this->service->convertToBill($po, [$itemId => 0]);
    }
}

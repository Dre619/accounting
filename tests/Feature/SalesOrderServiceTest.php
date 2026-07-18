<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CompanyProvisioningService;
use App\Services\InvoiceService;
use App\Services\SalesOrderService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SalesOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private SalesOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\AccountingSeeder::class);

        $user = User::factory()->create();
        $this->company = Company::create([
            'user_id' => $user->id, 'name' => 'SO Co', 'currency' => 'ZMW',
            'invoice_prefix' => 'INV', 'invoice_sequence' => 1,
        ]);
        $user->forceFill(['current_company_id' => $this->company->id])->save();

        app(CompanyProvisioningService::class)->provision($this->company);
        Warehouse::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_default' => true, 'is_active' => true]);

        $this->actingAs($user);
        $this->service = app(SalesOrderService::class);
    }

    private function accountId(string $code): int
    {
        return (int) Account::where('company_id', $this->company->id)->where('code', $code)->value('id');
    }

    private function customer(): Contact
    {
        return $this->company->contacts()->create(['name' => 'Buyer', 'type' => 'customer']);
    }

    private function incomeAccountId(): int
    {
        return (int) $this->company->accounts()->where('type', 'income')->value('id');
    }

    private function product(): Product
    {
        return $this->company->products()->create([
            'name' => 'Widget', 'type' => 'inventory', 'is_active' => true,
            'inventory_account_id' => $this->accountId('1300'),
            'cogs_account_id' => $this->accountId('5000'),
        ]);
    }

    private function draftOrder(?Product $product = null): SalesOrder
    {
        return $this->service->store($this->company, [
            'contact_id' => $this->customer()->id,
            'order_date' => '2026-07-01',
            'items'      => [[
                'description' => 'Widget order',
                'product_id'  => $product?->id,
                'account_id'  => $this->incomeAccountId(),
                'quantity'    => 4,
                'unit_price'  => 30,
            ]],
        ]);
    }

    public function test_store_numbers_and_totals_the_order(): void
    {
        $order = $this->draftOrder();

        $this->assertEquals('SO-0001', $order->order_number);
        $this->assertEquals('draft', $order->status);
        $this->assertEquals(120.0, (float) $order->total);
    }

    public function test_send_then_accept_transitions(): void
    {
        $order = $this->draftOrder();
        $this->service->send($order);
        $this->assertEquals('sent', $order->fresh()->status);

        $this->service->accept($order->fresh());
        $this->assertEquals('accepted', $order->fresh()->status);
    }

    public function test_cannot_cancel_an_invoiced_order(): void
    {
        $order = $this->draftOrder();
        $this->service->convertToInvoice($order);

        $this->expectException(HttpException::class);
        $this->service->cancel($order->fresh());
    }

    public function test_convert_to_invoice_links_and_marks_invoiced(): void
    {
        $order = $this->draftOrder();
        $invoice = $this->service->convertToInvoice($order);

        $order->refresh();
        $this->assertEquals('invoiced', $order->status);
        $this->assertEquals(4.0, (float) $order->items->first()->quantity_invoiced);
        $this->assertEquals($order->id, $invoice->sales_order_id);
        $this->assertEquals(120.0, (float) $invoice->total);
    }

    public function test_sending_the_converted_invoice_issues_stock_and_posts_cogs(): void
    {
        $product = $this->product();
        app(StockService::class)->receiveStock($product, 10, 5.00); // qty 10 @ 5

        $order = $this->draftOrder($product);
        $invoice = $this->service->convertToInvoice($order);
        app(InvoiceService::class)->send($invoice);

        $product->refresh();
        $this->assertEquals(6.0, (float) $product->quantity_on_hand, 'Sold 4 of 10');
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'type' => 'sale',
        ]);
    }

    public function test_order_numbers_increment(): void
    {
        $this->draftOrder();
        $second = $this->draftOrder();

        $this->assertEquals('SO-0002', $second->order_number);
    }

    public function test_partial_invoicing_marks_partial_then_completes(): void
    {
        $order = $this->draftOrder(); // qty 4 @ 30
        $itemId = $order->items->first()->id;

        $first = $this->service->convertToInvoice($order, [$itemId => 1]);
        $order->refresh();
        $this->assertEquals('partial', $order->status);
        $this->assertEquals(1.0, (float) $order->items->first()->quantity_invoiced);
        $this->assertEquals(30.0, (float) $first->total);

        $second = $this->service->convertToInvoice($order->fresh(), [$itemId => 3]);
        $order->refresh();
        $this->assertEquals('invoiced', $order->status);
        $this->assertEquals(4.0, (float) $order->items->first()->quantity_invoiced);
        $this->assertEquals(90.0, (float) $second->total);
    }

    public function test_partial_invoice_quantity_capped_at_outstanding(): void
    {
        $order = $this->draftOrder(); // qty 4
        $itemId = $order->items->first()->id;

        $invoice = $this->service->convertToInvoice($order, [$itemId => 10]);
        $order->refresh();

        $this->assertEquals(4.0, (float) $order->items->first()->quantity_invoiced);
        $this->assertEquals('invoiced', $order->status);
        $this->assertEquals(120.0, (float) $invoice->total);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\Contact;
use App\Models\JournalLine;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\BillService;
use App\Services\CompanyProvisioningService;
use App\Services\InvoiceService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentStockPostingTest extends TestCase
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
            'user_id'          => $this->user->id,
            'name'             => 'Stock Co',
            'currency'         => 'ZMW',
            'invoice_prefix'   => 'INV',
            'invoice_sequence' => 1,
        ]);
        $this->user->forceFill(['current_company_id' => $this->company->id])->save();

        app(CompanyProvisioningService::class)->provision($this->company);

        Warehouse::create([
            'company_id' => $this->company->id,
            'name'       => 'Main', 'code' => 'MAIN',
            'is_default' => true, 'is_active' => true,
        ]);

        $this->actingAs($this->user);
    }

    private function accountId(string $code): int
    {
        return (int) Account::where('company_id', $this->company->id)->where('code', $code)->value('id');
    }

    private function incomeAccountId(): int
    {
        return (int) $this->company->accounts()->where('type', 'income')->value('id');
    }

    private function contact(string $type): Contact
    {
        return $this->company->contacts()->create(['name' => ucfirst($type), 'type' => $type]);
    }

    private function inventoryProduct(): Product
    {
        return $this->company->products()->create([
            'name'                 => 'Widget',
            'type'                 => 'inventory',
            'sales_price'          => 20,
            'inventory_account_id' => $this->accountId('1300'),
            'cogs_account_id'      => $this->accountId('5000'),
            'is_active'            => true,
        ]);
    }

    public function test_approving_a_bill_receives_stock_and_debits_inventory(): void
    {
        $product = $this->inventoryProduct();
        $bills   = app(BillService::class);

        $bill = $bills->store($this->company, [
            'contact_id' => $this->contact('supplier')->id,
            'issue_date' => '2026-07-01',
            'due_date'   => '2026-07-31',
            'items'      => [[
                'description' => 'Widget purchase',
                'product_id'  => $product->id,
                'quantity'    => 10,
                'unit_price'  => 5,
            ]],
        ]);

        $bills->approve($bill);

        $product->refresh();
        $this->assertEquals(10.0, (float) $product->quantity_on_hand);
        $this->assertEquals(5.0, (float) $product->average_cost);

        $entry = $bill->journalEntries()->where('source', 'bill')->first();
        $lines = JournalLine::where('journal_entry_id', $entry->id)->get();

        // Inventory (1300) debited with the purchase cost, not an expense account.
        $inv = $lines->firstWhere('account_id', $this->accountId('1300'));
        $this->assertEquals(50.0, (float) $inv->debit);
        $this->assertEqualsWithDelta($lines->sum('debit'), $lines->sum('credit'), 0.001);

        $this->assertDatabaseHas('stock_movements', [
            'product_id'      => $product->id,
            'sourceable_type' => \App\Models\Bill::class,
            'sourceable_id'   => $bill->id,
            'type'            => 'purchase',
        ]);
    }

    public function test_sending_an_invoice_issues_stock_and_posts_cogs(): void
    {
        $product = $this->inventoryProduct();
        app(StockService::class)->receiveStock($product, 10, 5.00); // qty 10 @ 5

        $invoices = app(InvoiceService::class);
        $invoice  = $invoices->store($this->company, [
            'contact_id' => $this->contact('customer')->id,
            'issue_date' => '2026-07-02',
            'due_date'   => '2026-07-20',
            'items'      => [[
                'description' => 'Widget sale',
                'product_id'  => $product->id,
                'account_id'  => $this->incomeAccountId(),
                'quantity'    => 3,
                'unit_price'  => 20,
            ]],
        ]);

        $invoices->send($invoice);

        $product->refresh();
        $this->assertEquals(7.0, (float) $product->quantity_on_hand, 'Sold 3 of 10');
        $this->assertEquals(5.0, (float) $product->average_cost);

        $entry = $invoice->journalEntries()->where('source', 'invoice')->first();
        $lines = JournalLine::where('journal_entry_id', $entry->id)->get();

        // COGS 5000 debited 15 (3 @ 5); Inventory 1300 credited 15.
        $this->assertEquals(15.0, (float) $lines->firstWhere('account_id', $this->accountId('5000'))->debit);
        $this->assertEquals(15.0, (float) $lines->firstWhere('account_id', $this->accountId('1300'))->credit);
        $this->assertEqualsWithDelta($lines->sum('debit'), $lines->sum('credit'), 0.001, 'Entry stays balanced with COGS');
    }

    public function test_voiding_an_invoice_returns_stock(): void
    {
        $product = $this->inventoryProduct();
        app(StockService::class)->receiveStock($product, 10, 5.00);

        $invoices = app(InvoiceService::class);
        $invoice  = $invoices->store($this->company, [
            'contact_id' => $this->contact('customer')->id,
            'issue_date' => '2026-07-02',
            'due_date'   => '2026-07-20',
            'items'      => [[
                'description' => 'Widget sale',
                'product_id'  => $product->id,
                'account_id'  => $this->incomeAccountId(),
                'quantity'    => 3,
                'unit_price'  => 20,
            ]],
        ]);
        $invoices->send($invoice);
        $this->assertEquals(7.0, (float) $product->fresh()->quantity_on_hand);

        $invoices->void($invoice);

        $this->assertEquals(10.0, (float) $product->fresh()->quantity_on_hand, 'Void returns the 3 units');
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type'       => 'return',
        ]);
    }

    public function test_service_only_invoice_moves_no_stock_and_posts_no_cogs(): void
    {
        $invoices = app(InvoiceService::class);
        $invoice  = $invoices->store($this->company, [
            'contact_id' => $this->contact('customer')->id,
            'issue_date' => '2026-07-02',
            'due_date'   => '2026-07-20',
            'items'      => [[
                'description' => 'Consulting',
                'account_id'  => $this->incomeAccountId(),
                'quantity'    => 1,
                'unit_price'  => 500,
            ]],
        ]);

        $invoices->send($invoice);

        $entry = $invoice->journalEntries()->where('source', 'invoice')->first();
        $lines = JournalLine::where('journal_entry_id', $entry->id)->get();

        $this->assertNull($lines->firstWhere('account_id', $this->accountId('5000')), 'No COGS line');
        $this->assertEquals(0, StockMovement::where('company_id', $this->company->id)->count());
        $this->assertEqualsWithDelta($lines->sum('debit'), $lines->sum('credit'), 0.001);
    }
}

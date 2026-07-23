<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\CompanyProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalEntryNumberingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\AccountingSeeder::class);

        $user = User::factory()->create();
        $this->company = Company::create([
            'user_id' => $user->id, 'name' => 'JNL Co', 'currency' => 'ZMW',
            'invoice_prefix' => 'INV', 'invoice_sequence' => 1,
        ]);
        $user->forceFill(['current_company_id' => $this->company->id])->save();

        app(CompanyProvisioningService::class)->provision($this->company);
        $this->actingAs($user);
    }

    private function makeEntry(string $source = 'manual'): JournalEntry
    {
        return JournalEntry::create([
            'company_id'   => $this->company->id,
            'entry_number' => $this->company->nextJournalEntryNumber(),
            'entry_date'   => now()->toDateString(),
            'description'  => 'Test',
            'status'       => 'posted',
            'source'       => $source,
            'posted_at'    => now(),
        ]);
    }

    public function test_numbers_increment_sequentially(): void
    {
        $this->assertEquals('JNL-0001', $this->makeEntry()->entry_number);
        $this->assertEquals('JNL-0002', $this->makeEntry()->entry_number);
        $this->assertEquals('JNL-0003', $this->makeEntry()->entry_number);
    }

    public function test_soft_deleted_entries_do_not_release_their_number(): void
    {
        $first = $this->makeEntry();
        $this->makeEntry();
        $this->assertEquals('JNL-0001', $first->entry_number);

        // This is the production failure: the unique index still holds the row,
        // but a live count() would drop back to 1 and reissue JNL-0001.
        $first->delete();
        $this->assertSoftDeleted('journal_entries', ['id' => $first->id]);

        $next = $this->makeEntry();
        $this->assertEquals('JNL-0003', $next->entry_number, 'Must not reuse a trashed number');
    }

    public function test_numbering_is_shared_across_all_sources(): void
    {
        $this->makeEntry('invoice');
        $this->makeEntry('bill');

        // A manual entry must continue the shared sequence, not restart at 0001.
        $manual = $this->makeEntry('manual');
        $this->assertEquals('JNL-0003', $manual->entry_number);
    }

    public function test_numbering_survives_past_four_digits(): void
    {
        JournalEntry::create([
            'company_id' => $this->company->id, 'entry_number' => 'JNL-9999',
            'entry_date' => now()->toDateString(), 'description' => 'High water mark',
            'status' => 'posted', 'source' => 'manual', 'posted_at' => now(),
        ]);

        $this->assertEquals('JNL-10000', $this->company->nextJournalEntryNumber());
    }

    public function test_numbers_are_scoped_per_company(): void
    {
        $this->makeEntry();
        $this->makeEntry();

        $other = Company::create(['user_id' => $this->company->user_id, 'name' => 'Other', 'currency' => 'ZMW']);

        $this->assertEquals('JNL-0001', $other->nextJournalEntryNumber(), 'Each company numbers independently');
    }

    // ── Purchase / sales orders use the same scheme ──────────────────────────

    private function contact(string $type): \App\Models\Contact
    {
        return $this->company->contacts()->create(['name' => ucfirst($type), 'type' => $type]);
    }

    private function makePo(): \App\Models\PurchaseOrder
    {
        return app(\App\Services\PurchaseOrderService::class)->store($this->company, [
            'contact_id' => $this->contact('supplier')->id,
            'order_date' => now()->toDateString(),
            'items'      => [['description' => 'Thing', 'quantity' => 1, 'unit_price' => 10]],
        ]);
    }

    public function test_purchase_order_numbers_never_collide_after_a_middle_deletion(): void
    {
        $this->makePo();                 // PO-0001
        $second = $this->makePo();       // PO-0002
        $third  = $this->makePo();       // PO-0003
        $this->assertEquals('PO-0003', $third->po_number);

        // Deleting a record in the middle is where counting breaks: the count
        // falls to 2 and reissues PO-0003, which still exists.
        $second->forceDelete();

        $fourth = $this->makePo();
        $this->assertEquals('PO-0004', $fourth->po_number);
        $this->assertNotEquals($third->po_number, $fourth->po_number);
    }

    public function test_sales_order_numbers_never_collide_after_a_middle_deletion(): void
    {
        $orders  = app(\App\Services\SalesOrderService::class);
        $payload = fn () => [
            'contact_id' => $this->contact('customer')->id,
            'order_date' => now()->toDateString(),
            'items'      => [['description' => 'Thing', 'quantity' => 1, 'unit_price' => 10]],
        ];

        $orders->store($this->company, $payload());              // SO-0001
        $second = $orders->store($this->company, $payload());     // SO-0002
        $third  = $orders->store($this->company, $payload());     // SO-0003
        $this->assertEquals('SO-0003', $third->order_number);

        $second->forceDelete();

        $this->assertEquals('SO-0004', $orders->store($this->company, $payload())->order_number);
    }

    public function test_soft_deleted_orders_keep_their_number_reserved(): void
    {
        $first = $this->makePo();
        $first->delete(); // soft delete — row still occupies PO-0001

        $this->assertEquals('PO-0002', $this->makePo()->po_number);
    }

    public function test_invoice_number_reads_a_fresh_sequence_not_a_stale_instance(): void
    {
        // Two model instances of the same company simulate two requests holding
        // separately-loaded copies. The first advances the sequence; the second,
        // with a stale in-memory value, must still mint the next number — not
        // reuse the first's — because nextInvoiceNumber re-reads from the row.
        $a = \App\Models\Company::find($this->company->id);
        $b = \App\Models\Company::find($this->company->id);

        $first  = $a->nextInvoiceNumber();
        $second = $b->nextInvoiceNumber();

        $this->assertEquals('INV-0001', $first);
        $this->assertEquals('INV-0002', $second, 'A stale instance must not reissue the same invoice number');
    }
}

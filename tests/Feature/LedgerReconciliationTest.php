<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\Contact;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use App\Services\CompanyProvisioningService;
use App\Services\InvoiceService;
use App\Services\LedgerReconciliationService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerReconciliationTest extends TestCase
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
            'user_id' => $this->user->id, 'name' => 'Recon Co', 'currency' => 'ZMW',
            'invoice_prefix' => 'INV', 'invoice_sequence' => 1,
        ]);
        $this->user->forceFill(['current_company_id' => $this->company->id])->save();

        app(CompanyProvisioningService::class)->provision($this->company);
        $this->actingAs($this->user);
    }

    private function service(): LedgerReconciliationService
    {
        return app(LedgerReconciliationService::class);
    }

    private function accountId(string $code): int
    {
        return (int) Account::where('company_id', $this->company->id)->where('code', $code)->value('id');
    }

    private function customer(): Contact
    {
        return $this->company->contacts()->create(['name' => 'Buyer', 'type' => 'customer']);
    }

    private function sendInvoice(float $amount, Contact $customer): \App\Models\Invoice
    {
        $invoices = app(InvoiceService::class);
        $invoice  = $invoices->store($this->company, [
            'contact_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date'   => now()->addDays(14)->toDateString(),
            'items'      => [[
                'description' => 'Work', 'account_id' => $this->accountId('4000'),
                'quantity' => 1, 'unit_price' => $amount,
            ]],
        ]);
        $invoices->send($invoice);

        return $invoice;
    }

    private function failed(\Illuminate\Support\Collection $findings): \Illuminate\Support\Collection
    {
        return $findings->where('ok', false);
    }

    public function test_a_normal_ledger_reconciles_cleanly(): void
    {
        $customer = $this->customer();
        $invoice  = $this->sendInvoice(3000, $customer);

        app(PaymentService::class)->record($this->company, [
            'contact_id'         => $customer->id,
            'type'               => 'receipt',
            'payment_date'       => now()->toDateString(),
            'amount'             => 1000,
            'method'             => 'bank_transfer',
            'deposit_account_id' => $this->accountId('1100'),
            'allocations'        => [['type' => 'invoice', 'id' => $invoice->id, 'amount' => 1000]],
        ]);

        $findings = $this->service()->check($this->company);
        $this->assertTrue($this->failed($findings)->isEmpty(), 'Clean books should raise no findings');

        // And the AR tie is genuinely exercised: 3,000 invoiced less 1,000 paid.
        $ar = $findings->firstWhere('check', 'ar-subledger');
        $this->assertEquals(2000.0, $ar['actual']);
        $this->assertTrue($ar['ok']);
    }

    public function test_detects_an_unbalanced_journal_entry(): void
    {
        $entry = JournalEntry::create([
            'company_id' => $this->company->id, 'entry_number' => $this->company->nextJournalEntryNumber(),
            'entry_date' => now()->toDateString(), 'description' => 'Broken', 'status' => 'posted',
            'source' => 'manual', 'posted_at' => now(),
        ]);
        JournalLine::insert([
            ['journal_entry_id' => $entry->id, 'account_id' => $this->accountId('1000'), 'description' => 'x', 'debit' => 900, 'credit' => 0, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $entry->id, 'account_id' => $this->accountId('4000'), 'description' => 'x', 'debit' => 0, 'credit' => 800, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $failed = $this->failed($this->service()->check($this->company));
        $this->assertTrue($failed->where('check', 'entry-balanced')->isNotEmpty());
        $this->assertTrue($failed->where('check', 'trial-balance')->isNotEmpty(), 'A lopsided entry also breaks the trial balance');
    }

    public function test_detects_a_negative_control_account(): void
    {
        // Credit AR below zero via a lopsided-but-balanced pair of entries.
        $entry = JournalEntry::create([
            'company_id' => $this->company->id, 'entry_number' => $this->company->nextJournalEntryNumber(),
            'entry_date' => now()->toDateString(), 'description' => 'Push AR negative', 'status' => 'posted',
            'source' => 'manual', 'posted_at' => now(),
        ]);
        JournalLine::insert([
            ['journal_entry_id' => $entry->id, 'account_id' => $this->accountId('1200'), 'description' => 'x', 'debit' => 0, 'credit' => 500, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $entry->id, 'account_id' => $this->accountId('4000'), 'description' => 'x', 'debit' => 500, 'credit' => 0, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $failed = $this->failed($this->service()->check($this->company));
        $sign = $failed->firstWhere('check', 'control-sign');
        $this->assertNotNull($sign, 'Negative AR must be flagged as an error');
        $this->assertEquals('error', $sign['severity']);
        $this->assertEquals(-500.0, $sign['actual']);
    }

    public function test_command_exits_non_zero_when_errors_exist(): void
    {
        $entry = JournalEntry::create([
            'company_id' => $this->company->id, 'entry_number' => $this->company->nextJournalEntryNumber(),
            'entry_date' => now()->toDateString(), 'description' => 'Broken', 'status' => 'posted',
            'source' => 'manual', 'posted_at' => now(),
        ]);
        JournalLine::insert([
            ['journal_entry_id' => $entry->id, 'account_id' => $this->accountId('1000'), 'description' => 'x', 'debit' => 100, 'credit' => 0, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('ledger:reconcile', ['--company' => $this->company->id])->assertFailed();
    }

    public function test_command_succeeds_on_clean_books(): void
    {
        $this->sendInvoice(1000, $this->customer());

        $this->artisan('ledger:reconcile', ['--company' => $this->company->id])->assertSuccessful();
    }
}

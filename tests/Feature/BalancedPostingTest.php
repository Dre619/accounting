<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\Contact;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\CompanyProvisioningService;
use App\Services\InvoiceService;
use App\Services\LedgerReconciliationService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug A (invoice line with no income account dropped its revenue credit) and
 * Bug B (WHT on a receipt posted a one-sided credit to the wrong account) both
 * left the ledger unbalanced. These guard the fixes: every entry balances and
 * the whole ledger reconciles.
 */
class BalancedPostingTest extends TestCase
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
            'user_id' => $this->user->id, 'name' => 'Post Co', 'currency' => 'ZMW',
            'invoice_prefix' => 'INV', 'invoice_sequence' => 1,
        ]);
        $this->user->forceFill(['current_company_id' => $this->company->id])->save();

        app(CompanyProvisioningService::class)->provision($this->company);
        $this->actingAs($this->user);
    }

    private function account(string $code): Account
    {
        return Account::where('company_id', $this->company->id)->where('code', $code)->firstOrFail();
    }

    private function customer(): Contact
    {
        return $this->company->contacts()->create(['name' => 'Buyer', 'type' => 'customer']);
    }

    /** Assert every posted journal entry for the company balances, and the ledger reconciles. */
    private function assertLedgerClean(): void
    {
        $failed = app(LedgerReconciliationService::class)->check($this->company)->where('ok', false);
        $this->assertTrue(
            $failed->isEmpty(),
            'Ledger not clean: ' . $failed->pluck('message')->implode('; ')
        );
    }

    public function test_invoice_with_no_income_account_still_balances(): void
    {
        // Bug A: a line with account_id = null used to drop the revenue credit.
        $invoices = app(InvoiceService::class);
        $invoice = $invoices->store($this->company, [
            'contact_id' => $this->customer()->id,
            'issue_date' => now()->toDateString(),
            'due_date'   => now()->addDays(14)->toDateString(),
            'items'      => [[
                'description' => 'No account line', 'account_id' => null,
                'quantity' => 1, 'unit_price' => 12000,
            ]],
        ]);
        $invoices->send($invoice);

        $entry = $invoice->journalEntries()->where('source', 'invoice')->first();
        $lines = \App\Models\JournalLine::where('journal_entry_id', $entry->id)->get();
        $this->assertEqualsWithDelta($lines->sum('debit'), $lines->sum('credit'), 0.001, 'Invoice entry must balance');

        // The revenue landed in the default income account (4000).
        $this->assertEquals(12000.0, (float) $lines->firstWhere('account_id', $this->account('4000')->id)->credit);
        $this->assertLedgerClean();
    }

    public function test_discounted_invoice_balances(): void
    {
        $invoices = app(InvoiceService::class);
        $invoice = $invoices->store($this->company, [
            'contact_id' => $this->customer()->id,
            'issue_date' => now()->toDateString(),
            'due_date'   => now()->addDays(14)->toDateString(),
            'discount_amount' => 500,
            'items'      => [[
                'description' => 'Work', 'account_id' => $this->account('4000')->id,
                'quantity' => 1, 'unit_price' => 5000,
            ]],
        ]);
        $invoices->send($invoice);

        $entry = $invoice->journalEntries()->where('source', 'invoice')->first();
        $lines = \App\Models\JournalLine::where('journal_entry_id', $entry->id)->get();
        $this->assertEqualsWithDelta($lines->sum('debit'), $lines->sum('credit'), 0.001, 'Discounted invoice must balance');
        $this->assertLedgerClean();
    }

    public function test_receipt_with_withholding_tax_balances_and_uses_wht_receivable(): void
    {
        // Bug B: customer withholds 500 on a 5,000 invoice; cash 4,500 received.
        $customer = $this->customer();
        $invoices = app(InvoiceService::class);
        $invoice = $invoices->store($this->company, [
            'contact_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date'   => now()->addDays(14)->toDateString(),
            'items'      => [[
                'description' => 'Work', 'account_id' => $this->account('4000')->id,
                'quantity' => 1, 'unit_price' => 5000,
            ]],
        ]);
        $invoices->send($invoice);

        app(PaymentService::class)->record($this->company, [
            'contact_id'             => $customer->id,
            'type'                   => 'receipt',
            'payment_date'           => now()->toDateString(),
            'amount'                 => 4500,   // cash actually received
            'withholding_tax_amount' => 500,    // withheld by the customer
            'method'                 => 'bank_transfer',
            'deposit_account_id'     => $this->account('1100')->id,
            'allocations'            => [['type' => 'invoice', 'id' => $invoice->id, 'amount' => 4500]],
        ]);

        $entry = $invoice->company->journalEntries()->where('source', 'payment')->latest('id')->first();
        $lines = \App\Models\JournalLine::where('journal_entry_id', $entry->id)->get();

        $this->assertEqualsWithDelta($lines->sum('debit'), $lines->sum('credit'), 0.001, 'Receipt-with-WHT entry must balance');
        // WHT is a receivable (1600, debit), NOT WHT payable (2200).
        $this->assertEquals(500.0, (float) $lines->firstWhere('account_id', $this->account('1600')->id)->debit);
        $this->assertNull($lines->firstWhere('account_id', $this->account('2200')->id), 'Must not touch WHT Payable on a receipt');
        // AR is credited the gross 5,000 (cash 4,500 + WHT 500), fully settling the invoice.
        $this->assertEquals(5000.0, (float) $lines->firstWhere('account_id', $this->account('1200')->id)->credit);
        $this->assertLedgerClean();
    }
}

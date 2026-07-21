<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\User;
use App\Services\CompanyProvisioningService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class VoidedInvoiceReceivableTest extends TestCase
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
            'user_id' => $this->user->id, 'name' => 'AR Co', 'currency' => 'ZMW',
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

    private function arBalance(): float
    {
        return round((float) $this->account('1200')->balance, 2);
    }

    private function customer(): Contact
    {
        return $this->company->contacts()->create(['name' => 'Buyer', 'type' => 'customer']);
    }

    private function sentInvoice(float $amount, Contact $customer): Invoice
    {
        $invoices = app(InvoiceService::class);
        $invoice = $invoices->store($this->company, [
            'contact_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date'   => now()->addDays(14)->toDateString(),
            'items'      => [[
                'description' => 'Work', 'account_id' => $this->account('4000')->id,
                'quantity' => 1, 'unit_price' => $amount,
            ]],
        ]);
        $invoices->send($invoice);

        return $invoice;
    }

    public function test_voiding_an_unpaid_invoice_leaves_receivables_untouched(): void
    {
        $invoice = $this->sentInvoice(1000, $this->customer());
        $this->assertEquals(1000.0, $this->arBalance());

        app(InvoiceService::class)->void($invoice);

        $this->assertEquals(0.0, $this->arBalance(), 'Void must net AR back to zero');
    }

    public function test_voiding_a_partially_paid_invoice_leaves_receivables_correct(): void
    {
        $customer = $this->customer();
        $invoice  = $this->sentInvoice(1000, $customer);

        // Customer pays 400 of the 1,000.
        app(PaymentService::class)->record($this->company, [
            'contact_id'         => $customer->id,
            'type'               => 'receipt',
            'payment_date'       => now()->toDateString(),
            'amount'             => 400,
            'method'             => 'bank_transfer',
            'deposit_account_id' => $this->account('1100')->id,
            'allocations'        => [['type' => 'invoice', 'id' => $invoice->id, 'amount' => 400]],
        ]);

        $this->assertEquals(600.0, $this->arBalance(), 'AR is 1000 invoiced less 400 received');
        $this->assertEquals('partial', $invoice->fresh()->status);

        // Voiding is now refused while a payment is allocated, which is what keeps
        // AR from going negative.
        try {
            app(InvoiceService::class)->void($invoice->fresh());
            $this->fail('Voiding a part-paid invoice should have been refused.');
        } catch (HttpException $e) {
            $this->assertEquals(422, $e->getStatusCode());
        }

        $this->assertEquals('partial', $invoice->fresh()->status, 'Invoice is left untouched');
        $this->assertEquals(600.0, $this->arBalance(), 'AR is unchanged by the refused void');
    }

    public function test_audit_command_reports_and_repairs_legacy_voided_invoices(): void
    {
        $customer = $this->customer();
        $invoice  = $this->sentInvoice(1000, $customer);

        app(PaymentService::class)->record($this->company, [
            'contact_id'         => $customer->id,
            'type'               => 'receipt',
            'payment_date'       => now()->toDateString(),
            'amount'             => 400,
            'method'             => 'bank_transfer',
            'deposit_account_id' => $this->account('1100')->id,
            'allocations'        => [['type' => 'invoice', 'id' => $invoice->id, 'amount' => 400]],
        ]);

        // Simulate damage done BEFORE the guard existed: void bypassing the service.
        $service = app(InvoiceService::class);
        $invoice->forceFill(['status' => 'void', 'voided_at' => now()])->save();
        $reverse = (new \ReflectionClass($service))->getMethod('reverseJournalEntry');
        $reverse->setAccessible(true);
        $reverse->invoke($service, $invoice->journalEntries()->where('source', 'invoice')->first(), $invoice);

        $this->assertEquals(-400.0, $this->arBalance(), 'Legacy damage: AR is negative');

        $this->artisan('invoices:audit-voided-receivables', ['--fix' => true])
            ->expectsConfirmation('Post correcting journal entries for the invoices listed above?', 'yes')
            ->assertSuccessful();

        $this->assertEquals(0.0, $this->arBalance(), 'Residual cleared out of receivables');
        $this->assertEquals(400.0, round((float) $this->account('2050')->balance, 2), 'Held as a customer credit');
    }

    public function test_audit_command_is_a_dry_run_by_default(): void
    {
        $this->artisan('invoices:audit-voided-receivables')->assertSuccessful();
    }
}

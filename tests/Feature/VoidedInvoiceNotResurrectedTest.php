<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\CompanyProvisioningService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression for the production defect that left Accounts Receivable at -2,350:
 * an invoice was voided while part-paid, the payment was then deleted, which
 * reset the invoice status back to 'sent', allowing a SECOND void reversal to
 * post and double-credit receivables.
 */
class VoidedInvoiceNotResurrectedTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\AccountingSeeder::class);

        $user = User::factory()->create();
        $this->company = Company::create([
            'user_id' => $user->id, 'name' => 'AR Co', 'currency' => 'ZMW',
            'invoice_prefix' => 'INV', 'invoice_sequence' => 1,
        ]);
        $user->forceFill(['current_company_id' => $this->company->id])->save();

        app(CompanyProvisioningService::class)->provision($this->company);
        $this->actingAs($user);
    }

    private function account(string $code): Account
    {
        return Account::where('company_id', $this->company->id)->where('code', $code)->firstOrFail();
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
                'description' => 'Work', 'account_id' => $this->account('4100')->id,
                'quantity' => 1, 'unit_price' => $amount,
            ]],
        ]);
        $invoices->send($invoice);

        return $invoice;
    }

    public function test_deleting_a_payment_does_not_un_void_the_invoice(): void
    {
        $customer = $this->customer();
        $invoice  = $this->sentInvoice(5000, $customer);

        $payment = app(PaymentService::class)->record($this->company, [
            'contact_id'         => $customer->id,
            'type'               => 'receipt',
            'payment_date'       => now()->toDateString(),
            'amount'             => 2500,
            'method'             => 'bank_transfer',
            'deposit_account_id' => $this->account('1100')->id,
            'allocations'        => [['type' => 'invoice', 'id' => $invoice->id, 'amount' => 2500]],
        ]);

        // Void it the way the old code allowed (bypassing the new part-paid guard),
        // reproducing the state the production data is in.
        $service = app(InvoiceService::class);
        $invoice->forceFill(['status' => 'void', 'voided_at' => now()])->save();
        $reverse = (new \ReflectionClass($service))->getMethod('reverseJournalEntry');
        $reverse->setAccessible(true);
        $reverse->invoke($service, $invoice->journalEntries()->where('source', 'invoice')->first(), $invoice);

        // Now delete the payment — this is what used to resurrect the invoice.
        app(PaymentService::class)->destroy($payment->fresh());

        $this->assertEquals('void', $invoice->fresh()->status, 'A voided invoice must stay voided');
    }

    public function test_a_voided_invoice_cannot_be_reversed_twice(): void
    {
        $customer = $this->customer();
        $invoice  = $this->sentInvoice(5000, $customer);

        app(InvoiceService::class)->void($invoice);
        $this->assertEquals(0.0, round((float) $this->account('1200')->balance, 2));

        // Second void must be refused, so only one reversal ever exists.
        try {
            app(InvoiceService::class)->void($invoice->fresh());
            $this->fail('Voiding an already-voided invoice should be refused.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertEquals(422, $e->getStatusCode());
        }

        $this->assertEquals(
            1,
            $invoice->journalEntries()->where('description', 'like', 'Void reversal%')->count(),
            'Exactly one void reversal'
        );
        $this->assertEquals(0.0, round((float) $this->account('1200')->balance, 2), 'AR stays at zero');
    }
}

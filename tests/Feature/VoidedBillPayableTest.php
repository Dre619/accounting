<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use App\Services\BillService;
use App\Services\CompanyProvisioningService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * The accounts-payable mirror of VoidedInvoiceReceivableTest: voiding a bill
 * that already has a payment must not corrupt AP.
 */
class VoidedBillPayableTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\AccountingSeeder::class);

        $user = User::factory()->create();
        $this->company = Company::create([
            'user_id' => $user->id, 'name' => 'AP Co', 'currency' => 'ZMW',
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

    private function apBalance(): float
    {
        return round((float) $this->account('2000')->balance, 2);
    }

    private function supplier(): Contact
    {
        return $this->company->contacts()->create(['name' => 'Vendor', 'type' => 'supplier']);
    }

    private function approvedBill(float $amount, Contact $supplier): Bill
    {
        $bills = app(BillService::class);
        $bill = $bills->store($this->company, [
            'contact_id' => $supplier->id,
            'bill_number' => 'SUP-1',
            'issue_date' => now()->toDateString(),
            'due_date'   => now()->addDays(14)->toDateString(),
            'items'      => [[
                'description' => 'Rent', 'account_id' => $this->account('6100')->id,
                'quantity' => 1, 'unit_price' => $amount,
            ]],
        ]);
        $bills->approve($bill);

        return $bill;
    }

    private function pay(Bill $bill, Contact $supplier, float $amount)
    {
        return app(PaymentService::class)->record($this->company, [
            'contact_id'         => $supplier->id,
            'type'               => 'payment',
            'payment_date'       => now()->toDateString(),
            'amount'             => $amount,
            'method'             => 'bank_transfer',
            'deposit_account_id' => $this->account('1100')->id,
            'allocations'        => [['type' => 'bill', 'id' => $bill->id, 'amount' => $amount]],
        ]);
    }

    public function test_voiding_a_part_paid_bill_is_refused(): void
    {
        $supplier = $this->supplier();
        $bill     = $this->approvedBill(1000, $supplier);
        $this->assertEquals(1000.0, $this->apBalance());

        $this->pay($bill, $supplier, 400);
        $this->assertEquals(600.0, $this->apBalance());

        try {
            app(BillService::class)->void($bill->fresh());
            $this->fail('Voiding a part-paid bill should have been refused.');
        } catch (HttpException $e) {
            $this->assertEquals(422, $e->getStatusCode());
        }

        $this->assertEquals(600.0, $this->apBalance(), 'AP unchanged by the refused void');
    }

    public function test_voiding_an_unpaid_bill_nets_payables_to_zero(): void
    {
        $bill = $this->approvedBill(1000, $this->supplier());
        $this->assertEquals(1000.0, $this->apBalance());

        app(BillService::class)->void($bill);

        $this->assertEquals(0.0, $this->apBalance());
    }

    public function test_deleting_a_payment_does_not_un_void_a_bill(): void
    {
        $supplier = $this->supplier();
        $bill     = $this->approvedBill(1000, $supplier);
        $payment  = $this->pay($bill, $supplier, 400);

        // Reproduce a legacy voided-while-paid bill by bypassing the new guard.
        $service = app(BillService::class);
        $bill->forceFill(['status' => 'void', 'voided_at' => now()])->save();
        $reverse = (new \ReflectionClass($service))->getMethod('reverseJournalEntry');
        $reverse->setAccessible(true);
        $reverse->invoke($service, $bill->journalEntries()->where('source', 'bill')->first(), $bill);

        app(PaymentService::class)->destroy($payment->fresh());

        $this->assertEquals('void', $bill->fresh()->status, 'A voided bill must stay voided');
    }
}

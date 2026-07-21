<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalLine;
use App\Models\User;
use App\Services\CompanyProvisioningService;
use App\Services\InvoiceService;
use App\Services\TurnoverTaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TurnoverTaxTest extends TestCase
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
            'user_id' => $this->user->id, 'name' => 'TOT Co', 'currency' => 'ZMW',
            'invoice_prefix' => 'INV', 'invoice_sequence' => 1,
            'tax_regime' => 'turnover',
        ]);
        $this->user->forceFill(['current_company_id' => $this->company->id])->save();

        app(CompanyProvisioningService::class)->provision($this->company);
        $this->rate(4.00); // always-effective default for most tests
        $this->actingAs($this->user);
    }

    private function rate(float $rate, ?string $from = null, ?string $to = null, string $code = 'TOT'): void
    {
        $this->company->taxRates()->create([
            'name' => 'Turnover Tax', 'code' => $code, 'type' => 'turnover',
            'rate' => $rate, 'effective_from' => $from, 'effective_to' => $to, 'is_active' => true,
        ]);
    }

    private function accountId(string $code): int
    {
        return (int) Account::where('company_id', $this->company->id)->where('code', $code)->value('id');
    }

    /** Raise and send an invoice so operating income is posted to the ledger. */
    private function sell(float $amount): void
    {
        $customer = $this->company->contacts()->create(['name' => 'Buyer', 'type' => 'customer']);
        $invoices = app(InvoiceService::class);
        $invoice = $invoices->store($this->company, [
            'contact_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date'   => now()->addDays(14)->toDateString(),
            'items'      => [[
                'description' => 'Sale', 'account_id' => $this->accountId('4000'),
                'quantity' => 1, 'unit_price' => $amount,
            ]],
        ]);
        $invoices->send($invoice);
    }

    public function test_provisioning_creates_the_tax_accounts(): void
    {
        foreach (['1450', '2150', '2250', '8000', '8100'] as $code) {
            $this->assertNotNull(
                Account::where('company_id', $this->company->id)->where('code', $code)->first(),
                "Account {$code} should exist"
            );
        }
        $this->assertEquals('taxation', Account::where('company_id', $this->company->id)->where('code', '8000')->value('subtype'));
    }

    public function test_computes_turnover_and_tax_for_the_period(): void
    {
        $this->sell(10_000);

        $result = app(TurnoverTaxService::class)->compute(
            $this->company, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()
        );

        $this->assertEquals(10_000.0, $result['turnover']);
        $this->assertEquals(4.0, $result['rate']);
        $this->assertEquals(400.0, $result['tax'], '10,000 @ 4%');
        $this->assertFalse($result['posted']);
    }

    public function test_posting_creates_a_balanced_tax_entry(): void
    {
        $this->sell(10_000);
        $from = now()->startOfMonth()->toDateString();
        $to   = now()->endOfMonth()->toDateString();

        $entry = app(TurnoverTaxService::class)->post($this->company, $from, $to);

        $lines = JournalLine::where('journal_entry_id', $entry->id)->get();
        $this->assertEquals('tax', $entry->source);
        $this->assertEquals(400.0, (float) $lines->firstWhere('account_id', $this->accountId('8000'))->debit);
        $this->assertEquals(400.0, (float) $lines->firstWhere('account_id', $this->accountId('2150'))->credit);
        $this->assertEqualsWithDelta($lines->sum('debit'), $lines->sum('credit'), 0.001);
    }

    public function test_cannot_post_the_same_period_twice(): void
    {
        $this->sell(5_000);
        $from = now()->startOfMonth()->toDateString();
        $to   = now()->endOfMonth()->toDateString();
        $svc  = app(TurnoverTaxService::class);
        $svc->post($this->company, $from, $to);

        $this->expectException(HttpException::class);
        $svc->post($this->company, $from, $to);
    }

    public function test_cannot_post_without_a_rate_configured(): void
    {
        $this->company->taxRates()->turnover()->delete();
        $this->sell(5_000);

        $this->expectException(HttpException::class);
        app(TurnoverTaxService::class)->post(
            $this->company->fresh(), now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()
        );
    }

    public function test_rate_is_resolved_by_the_period_being_filed(): void
    {
        // Replace the always-on rate with two dated ones: 4% up to 2026-06-30, 5% after.
        $this->company->taxRates()->turnover()->delete();
        $this->rate(4.00, null, '2026-06-30', 'TOT-OLD');
        $this->rate(5.00, '2026-07-01', null, 'TOT-NEW');

        $svc = app(TurnoverTaxService::class);

        $old = $svc->compute($this->company, '2026-05-01', '2026-05-31');
        $this->assertEquals(4.0, $old['rate'], 'May 2026 uses the pre-change rate');

        $new = $svc->compute($this->company, '2026-07-01', '2026-07-31');
        $this->assertEquals(5.0, $new['rate'], 'July 2026 uses the post-change rate');
    }

    public function test_period_spanning_a_rate_change_is_refused_rather_than_guessed(): void
    {
        $this->company->taxRates()->turnover()->delete();
        $this->rate(4.00, null, '2026-06-30', 'TOT-OLD');
        $this->rate(5.00, '2026-07-01', null, 'TOT-NEW');

        $svc = app(TurnoverTaxService::class);

        $straddling = $svc->compute($this->company, '2026-06-01', '2026-07-31');
        $this->assertEquals('ambiguous', $straddling['rate_error']);
        $this->assertNull($straddling['tax']);

        $this->expectException(HttpException::class);
        $svc->post($this->company, '2026-06-01', '2026-07-31');
    }

    public function test_historical_rate_survives_a_later_rate_change(): void
    {
        // A return filed for May must keep computing at May's rate even after
        // a new rate is introduced later — the point of effective dating.
        $this->company->taxRates()->turnover()->delete();
        $this->rate(4.00, null, '2026-06-30', 'TOT-OLD');
        $svc = app(TurnoverTaxService::class);
        $before = $svc->compute($this->company, '2026-05-01', '2026-05-31')['rate'];

        $this->rate(5.00, '2026-07-01', null, 'TOT-NEW');
        $after = $svc->compute($this->company, '2026-05-01', '2026-05-31')['rate'];

        $this->assertEquals(4.0, $before);
        $this->assertEquals(4.0, $after, 'May still computes at 4% after the change');
    }

    public function test_standard_regime_company_cannot_post_turnover_tax(): void
    {
        $this->company->update(['tax_regime' => 'standard']);
        $this->sell(5_000);

        $this->expectException(HttpException::class);
        app(TurnoverTaxService::class)->post(
            $this->company->fresh(), now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()
        );
    }

    public function test_turnover_tax_company_is_offered_no_vat_on_invoices(): void
    {
        $this->company->contacts()->create(['name' => 'Buyer', 'type' => 'customer']);

        $this->get('/invoices/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('invoices/Form')->has('taxRates', 0));
    }

    public function test_profit_and_loss_separates_tax_below_operating_profit(): void
    {
        $this->sell(10_000);
        app(TurnoverTaxService::class)->post(
            $this->company, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()
        );

        // The tax entry is dated period-end, so query the whole month.
        $from = now()->startOfMonth()->toDateString();
        $to   = now()->endOfMonth()->toDateString();

        $this->get("/reports/profit-loss?from={$from}&to={$to}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reports/ProfitLoss')
                ->where('profitBeforeTax', 10000)
                ->where('totalTax', 400)
                ->where('netProfit', 9600)
                ->has('taxes', 1));
    }

    public function test_turnover_tax_page_renders_with_the_return(): void
    {
        $this->sell(2_500);

        $this->get('/tax/turnover')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('tax/TurnoverTax')
                ->where('taxRegime', 'turnover')
                ->where('result.turnover', 2500)
                ->where('result.tax', 100));
    }
}

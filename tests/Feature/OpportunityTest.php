<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\CompanyProvisioningService;
use App\Services\OpportunityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OpportunityTest extends TestCase
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
            'user_id' => $this->user->id, 'name' => 'CRM Co', 'currency' => 'ZMW',
            'invoice_prefix' => 'INV', 'invoice_sequence' => 1,
        ]);
        $this->user->forceFill(['current_company_id' => $this->company->id])->save();

        app(CompanyProvisioningService::class)->provision($this->company);
        $this->actingAs($this->user);
    }

    private function contact(): Contact
    {
        return $this->company->contacts()->create(['name' => 'Acme', 'type' => 'customer']);
    }

    private function opp(array $attrs = []): Opportunity
    {
        return $this->company->opportunities()->create(array_merge([
            'contact_id' => $this->contact()->id,
            'title' => 'Big deal', 'stage' => 'qualified', 'estimated_value' => 5000,
            'created_by' => $this->user->id,
        ], $attrs));
    }

    public function test_pipeline_index_totals_open_value(): void
    {
        $this->opp(['stage' => 'new', 'estimated_value' => 1000]);
        $this->opp(['stage' => 'proposal', 'estimated_value' => 4000]);
        $this->opp(['stage' => 'won', 'estimated_value' => 9000, 'won_at' => now()]);

        $this->get('/opportunities')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('opportunities/Index')
                ->where('stats.open_value', 5000)
                ->where('stats.open_count', 2)
                ->where('stats.won_this_month', 9000));
    }

    public function test_mark_won_and_lost(): void
    {
        $svc = app(OpportunityService::class);
        $o = $this->opp();

        $svc->markWon($o);
        $this->assertEquals('won', $o->fresh()->stage);
        $this->assertNotNull($o->fresh()->won_at);

        $svc->markLost($o->fresh(), 'Chose competitor');
        $o->refresh();
        $this->assertEquals('lost', $o->stage);
        $this->assertEquals('Chose competitor', $o->lost_reason);
        $this->assertNull($o->won_at);
    }

    public function test_convert_to_quote_creates_linked_sales_order(): void
    {
        $o = $this->opp(['stage' => 'qualified', 'estimated_value' => 5000]);

        $order = app(OpportunityService::class)->convertToQuote($o);

        $o->refresh();
        $this->assertInstanceOf(SalesOrder::class, $order);
        $this->assertEquals($order->id, $o->sales_order_id);
        $this->assertEquals('proposal', $o->stage, 'Early stage advances to proposal');
        $this->assertEquals(5000.0, (float) $order->total);
        $this->assertEquals($o->contact_id, $order->contact_id);
    }

    public function test_cannot_convert_twice(): void
    {
        $o = $this->opp();
        app(OpportunityService::class)->convertToQuote($o);

        $this->expectException(HttpException::class);
        app(OpportunityService::class)->convertToQuote($o->fresh());
    }

    public function test_convert_endpoint_redirects_to_the_quote(): void
    {
        $o = $this->opp();

        $this->post("/opportunities/{$o->id}/convert")->assertRedirect();
        $this->assertDatabaseHas('sales_orders', ['id' => $o->fresh()->sales_order_id]);
    }

    public function test_logging_activity_against_an_opportunity(): void
    {
        $o = $this->opp();

        $this->post("/opportunities/{$o->id}/activities", ['type' => 'call', 'body' => 'Negotiated price'])
            ->assertRedirect();

        $this->assertDatabaseHas('activities', [
            'subject_type' => Opportunity::class, 'subject_id' => $o->id, 'type' => 'call',
        ]);
    }
}

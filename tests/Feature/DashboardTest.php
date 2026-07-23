<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_a_user_without_a_company_is_sent_to_setup()
    {
        // A freshly-registered user has no company yet, so the dashboard is not
        // reachable — the subscription middleware redirects them to create one.
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertRedirect(route('company.create'));
    }

    public function test_authenticated_users_with_a_company_can_visit_the_dashboard()
    {
        $this->seed(\Database\Seeders\AccountingSeeder::class); // global account categories

        $user = User::factory()->create();
        $company = Company::create([
            'user_id' => $user->id, 'name' => 'Dash Co', 'currency' => 'ZMW',
            'invoice_prefix' => 'INV', 'invoice_sequence' => 1,
        ]);
        $user->forceFill(['current_company_id' => $company->id])->save();
        app(CompanyProvisioningService::class)->provision($company); // grants the trial

        $this->actingAs($user);

        $this->get(route('dashboard'))->assertOk();
    }
}

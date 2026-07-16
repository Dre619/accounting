<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingRenewalTest extends TestCase
{
    use RefreshDatabase;

    private function companyOnPlan(string $slug, string $status, string $endsAt): array
    {
        $user = User::factory()->create();

        $company = Company::create([
            'user_id'       => $user->id,
            'name'          => 'Test Co',
            'currency'      => 'ZMW',
            'trial_ends_at' => now()->subMonths(6),
        ]);

        $user->forceFill(['current_company_id' => $company->id])->save();

        $plan = SubscriptionPlan::create([
            'name'          => ucfirst($slug),
            'slug'          => $slug,
            'description'   => 'Test plan',
            'price_monthly' => 399.00,
            'price_annual'  => 3_990.00,
            'max_users'     => 3,
            'sort_order'    => 2,
            'features'      => ['Everything'],
            'is_active'     => true,
        ]);

        Subscription::create([
            'company_id'    => $company->id,
            'plan_id'       => $plan->id,
            'status'        => $status,
            'billing_cycle' => 'monthly',
            'starts_at'     => now()->subMonths(2)->toDateString(),
            'ends_at'       => $endsAt,
        ]);

        return [$user, $plan];
    }

    public function test_expired_subscription_is_not_reported_as_the_current_plan(): void
    {
        [$user] = $this->companyOnPlan('growth', 'expired', now()->subDays(3)->toDateString());

        $this->actingAs($user)
            ->get(route('billing.plans'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('billing/Plans')
                ->where('subscriptionActive', false)
            );
    }

    public function test_lapsed_subscription_still_marked_active_in_db_is_treated_as_inactive(): void
    {
        // The subscriptions:expire job runs nightly, so a lapsed row can still read
        // "active" until it fires. The end date is what decides.
        [$user] = $this->companyOnPlan('growth', 'active', now()->subDay()->toDateString());

        $this->actingAs($user)
            ->get(route('billing.plans'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('subscriptionActive', false));
    }

    public function test_active_subscription_is_reported_as_active(): void
    {
        [$user] = $this->companyOnPlan('growth', 'active', now()->addMonth()->toDateString());

        $this->actingAs($user)
            ->get(route('billing.plans'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('subscriptionActive', true));
    }

    public function test_expired_subscriber_can_reach_checkout_to_renew_the_same_plan(): void
    {
        [$user, $plan] = $this->companyOnPlan('growth', 'expired', now()->subDays(3)->toDateString());

        $this->actingAs($user)
            ->get(route('billing.checkout', ['plan' => $plan->id, 'cycle' => 'monthly']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('billing/Checkout')
                ->where('plan.id', $plan->id)
                ->where('amount', '399.00')
            );
    }

    public function test_expired_subscriber_is_pushed_to_billing_with_a_renewal_prompt(): void
    {
        [$user] = $this->companyOnPlan('growth', 'expired', now()->subDays(3)->toDateString());

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('billing.plans'))
            ->assertSessionHas('warning', 'Your subscription has ended. Renew it to continue.');
    }

    public function test_status_page_reports_expired_subscription_as_inactive(): void
    {
        [$user] = $this->companyOnPlan('growth', 'expired', now()->subDays(3)->toDateString());

        $this->actingAs($user)
            ->get(route('billing.status'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('billing/Status')
                ->where('subscriptionActive', false)
            );
    }
}

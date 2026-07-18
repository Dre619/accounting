<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\CompanyProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityTest extends TestCase
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
        ]);
        $this->user->forceFill(['current_company_id' => $this->company->id])->save();

        app(CompanyProvisioningService::class)->provision($this->company); // trial → business features (crm)
        $this->actingAs($this->user);
    }

    private function contact(): Contact
    {
        return $this->company->contacts()->create(['name' => 'Acme', 'type' => 'customer']);
    }

    public function test_service_logs_an_activity_against_a_contact(): void
    {
        $contact = $this->contact();

        $activity = app(ActivityService::class)->log($contact, ['type' => 'call', 'body' => 'Discussed renewal']);

        $this->assertEquals($this->company->id, $activity->company_id);
        $this->assertEquals($this->user->id, $activity->user_id);
        $this->assertEquals('call', $activity->type);
        $this->assertTrue($contact->activities()->whereKey($activity->id)->exists());
    }

    public function test_store_endpoint_logs_activity_and_appears_on_show(): void
    {
        $contact = $this->contact();

        $this->post("/contacts/{$contact->id}/activities", [
            'type' => 'meeting', 'body' => 'Site visit at HQ',
        ])->assertRedirect();

        $this->assertDatabaseHas('activities', [
            'subject_type' => Contact::class,
            'subject_id'   => $contact->id,
            'type'         => 'meeting',
            'body'         => 'Site visit at HQ',
        ]);

        $this->get("/contacts/{$contact->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('contacts/Show')
                ->has('contact.activities', 1)
                ->where('contact.activities.0.type', 'meeting'));
    }

    public function test_activity_of_another_company_cannot_be_deleted(): void
    {
        $other = Company::create(['user_id' => $this->user->id, 'name' => 'Other', 'currency' => 'ZMW']);
        $otherContact = $other->contacts()->create(['name' => 'Foreign', 'type' => 'customer']);
        $foreign = $other->activities()->create([
            'user_id' => $this->user->id, 'subject_type' => Contact::class, 'subject_id' => $otherContact->id,
            'type' => 'note', 'body' => 'secret', 'occurred_at' => now(),
        ]);

        $this->delete("/activities/{$foreign->id}")->assertStatus(403);
        $this->assertDatabaseHas('activities', ['id' => $foreign->id]);
    }

    public function test_validation_rejects_bad_type(): void
    {
        $contact = $this->contact();

        $this->post("/contacts/{$contact->id}/activities", ['type' => 'carrier-pigeon', 'body' => 'x'])
            ->assertSessionHasErrors('type');
    }
}

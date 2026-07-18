<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use App\Services\CompanyProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\AccountingSeeder::class);

        $this->user = User::factory()->create();
        $this->company = Company::create(['user_id' => $this->user->id, 'name' => 'CRM Co', 'currency' => 'ZMW']);
        $this->user->forceFill(['current_company_id' => $this->company->id])->save();

        app(CompanyProvisioningService::class)->provision($this->company);
        $this->actingAs($this->user);
    }

    public function test_creating_a_task_linked_to_a_contact(): void
    {
        $contact = $this->company->contacts()->create(['name' => 'Acme', 'type' => 'customer']);

        $this->post('/tasks', [
            'title' => 'Call about renewal', 'due_date' => '2026-08-01', 'contact_id' => $contact->id,
        ])->assertRedirect();

        $task = Task::where('company_id', $this->company->id)->firstOrFail();
        $this->assertEquals('Call about renewal', $task->title);
        $this->assertEquals(Contact::class, $task->related_type);
        $this->assertEquals($contact->id, $task->related_id);
        $this->assertEquals($this->user->id, $task->created_by);
    }

    public function test_complete_toggles_completion(): void
    {
        $task = $this->company->tasks()->create(['title' => 'X', 'created_by' => $this->user->id]);

        $this->post("/tasks/{$task->id}/complete")->assertRedirect();
        $this->assertNotNull($task->fresh()->completed_at);

        $this->post("/tasks/{$task->id}/complete")->assertRedirect();
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_index_counts_overdue_open_and_completed(): void
    {
        $this->company->tasks()->create(['title' => 'Overdue', 'created_by' => $this->user->id, 'due_date' => now()->subDays(2)->toDateString()]);
        $this->company->tasks()->create(['title' => 'Future', 'created_by' => $this->user->id, 'due_date' => now()->addWeek()->toDateString()]);
        $done = $this->company->tasks()->create(['title' => 'Done', 'created_by' => $this->user->id, 'completed_at' => now()]);

        $this->get('/tasks')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('tasks/Index')
                ->where('counts.open', 2)
                ->where('counts.overdue', 1)
                ->where('counts.completed', 1));
    }

    public function test_cannot_touch_another_companys_task(): void
    {
        $other = Company::create(['user_id' => $this->user->id, 'name' => 'Other', 'currency' => 'ZMW']);
        $foreign = $other->tasks()->create(['title' => 'secret', 'created_by' => $this->user->id]);

        $this->post("/tasks/{$foreign->id}/complete")->assertStatus(403);
        $this->delete("/tasks/{$foreign->id}")->assertStatus(403);
    }
}

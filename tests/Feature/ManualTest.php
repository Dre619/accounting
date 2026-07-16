<?php

namespace Tests\Feature;

use App\Models\ManualSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualTest extends TestCase
{
    use RefreshDatabase;

    private function section(array $attributes = []): ManualSection
    {
        return ManualSection::create([
            'slug'         => 'getting-started',
            'title'        => 'Getting started',
            'summary'      => 'Find your way around.',
            'body'         => "## Create your account\n\nGo to **Invoices**.",
            'sort_order'   => 1,
            'is_published' => true,
            ...$attributes,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    // ── Public access ────────────────────────────────────────────────────────

    public function test_guests_can_read_the_manual(): void
    {
        $this->section();

        $this->get(route('manual'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('manual/Index')
                ->has('sections', 1)
                ->where('sections.0.title', 'Getting started')
            );
    }

    public function test_signed_in_users_can_read_the_manual(): void
    {
        $this->section();

        $this->actingAs(User::factory()->create())
            ->get(route('manual'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('sections', 1));
    }

    public function test_markdown_is_rendered_to_html(): void
    {
        $this->section();

        $this->get(route('manual'))->assertInertia(fn ($page) => $page
            ->where('sections.0.body_html', "<h2>Create your account</h2>\n<p>Go to <strong>Invoices</strong>.</p>\n")
        );
    }

    public function test_raw_html_in_a_section_body_is_stripped_not_rendered(): void
    {
        $this->section(['body' => 'Hello <script>alert(1)</script> there']);

        $this->get(route('manual'))->assertInertia(fn ($page) => $page
            ->where('sections.0.body_html', fn (string $html) => ! str_contains($html, '<script>'))
        );
    }

    public function test_javascript_links_are_not_rendered(): void
    {
        $this->section(['body' => '[click me](javascript:alert(1))']);

        $this->get(route('manual'))->assertInertia(fn ($page) => $page
            ->where('sections.0.body_html', fn (string $html) => ! str_contains($html, 'javascript:'))
        );
    }

    public function test_draft_sections_are_hidden_from_the_public_manual(): void
    {
        $this->section(['is_published' => false]);

        $this->get(route('manual'))->assertInertia(fn ($page) => $page->has('sections', 0));
    }

    public function test_sections_are_returned_in_sort_order(): void
    {
        $this->section(['slug' => 'third', 'title' => 'Third', 'sort_order' => 3]);
        $this->section(['slug' => 'first', 'title' => 'First', 'sort_order' => 1]);
        $this->section(['slug' => 'second', 'title' => 'Second', 'sort_order' => 2]);

        $this->get(route('manual'))->assertInertia(fn ($page) => $page
            ->where('sections.0.title', 'First')
            ->where('sections.1.title', 'Second')
            ->where('sections.2.title', 'Third')
        );
    }

    // ── Admin authorisation ──────────────────────────────────────────────────

    public function test_guests_cannot_reach_the_manual_editor(): void
    {
        $this->get(route('admin.settings.manual'))->assertRedirect(route('login'));
    }

    public function test_non_admins_cannot_reach_the_manual_editor(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.settings.manual'))
            ->assertForbidden();
    }

    public function test_non_admins_cannot_edit_a_section(): void
    {
        $section = $this->section();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->put(route('admin.settings.manual.update', $section), ['title' => 'Hacked'])
            ->assertForbidden();

        $this->assertSame('Getting started', $section->fresh()->title);
    }

    public function test_non_admins_cannot_delete_a_section(): void
    {
        $section = $this->section();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->delete(route('admin.settings.manual.destroy', $section))
            ->assertForbidden();

        $this->assertDatabaseHas('manual_sections', ['id' => $section->id]);
    }

    // ── Admin editing ────────────────────────────────────────────────────────

    public function test_admins_see_drafts_in_the_editor(): void
    {
        $this->section(['is_published' => false]);

        $this->actingAs($this->admin())
            ->get(route('admin.settings.manual'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/manual/Index')->has('sections', 1));
    }

    public function test_admins_can_create_a_section(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.manual.store'), [
                'title'        => 'Creating an invoice',
                'summary'      => 'Bill your customers.',
                'body'         => 'Go to **Invoices**.',
                'is_published' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('manual_sections', [
            'title' => 'Creating an invoice',
            'slug'  => 'creating-an-invoice',
        ]);
    }

    public function test_a_new_section_goes_to_the_end_of_the_order(): void
    {
        $this->section(['sort_order' => 7]);

        $this->actingAs($this->admin())->post(route('admin.settings.manual.store'), [
            'title' => 'Last one',
        ]);

        $this->assertSame(8, ManualSection::where('slug', 'last-one')->value('sort_order'));
    }

    public function test_duplicate_titles_get_distinct_slugs(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.settings.manual.store'), ['title' => 'Reports']);
        $this->actingAs($admin)->post(route('admin.settings.manual.store'), ['title' => 'Reports']);

        $this->assertDatabaseHas('manual_sections', ['slug' => 'reports']);
        $this->assertDatabaseHas('manual_sections', ['slug' => 'reports-2']);
    }

    public function test_a_section_title_is_required(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.manual.store'), ['title' => ''])
            ->assertSessionHasErrors('title');
    }

    public function test_admins_can_edit_a_section(): void
    {
        $section = $this->section();

        $this->actingAs($this->admin())
            ->put(route('admin.settings.manual.update', $section), [
                'title'        => 'Getting started',
                'body'         => 'Updated body.',
                'is_published' => true,
            ])
            ->assertRedirect();

        $this->assertSame('Updated body.', $section->fresh()->body);
    }

    public function test_renaming_a_published_section_keeps_its_slug_stable(): void
    {
        $section = $this->section(['is_published' => true]);

        $this->actingAs($this->admin())->put(route('admin.settings.manual.update', $section), [
            'title'        => 'A completely different title',
            'is_published' => true,
        ]);

        // Existing links to /manual#getting-started must keep working.
        $this->assertSame('getting-started', $section->fresh()->slug);
    }

    public function test_renaming_a_draft_section_updates_its_slug(): void
    {
        $section = $this->section(['is_published' => false]);

        $this->actingAs($this->admin())->put(route('admin.settings.manual.update', $section), [
            'title'        => 'Brand new title',
            'is_published' => false,
        ]);

        $this->assertSame('brand-new-title', $section->fresh()->slug);
    }

    public function test_admins_can_reorder_sections(): void
    {
        $a = $this->section(['slug' => 'a', 'title' => 'A', 'sort_order' => 1]);
        $b = $this->section(['slug' => 'b', 'title' => 'B', 'sort_order' => 2]);

        $this->actingAs($this->admin())
            ->post(route('admin.settings.manual.reorder'), ['ids' => [$b->id, $a->id]])
            ->assertRedirect();

        $this->assertSame(1, $b->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);
    }

    // ── Images ───────────────────────────────────────────────────────────────

    public function test_admins_can_upload_an_image_to_a_section(): void
    {
        Storage::fake('public');
        $section = $this->section();

        $this->actingAs($this->admin())
            ->post(route('admin.settings.manual.images.store', $section), [
                'image'   => UploadedFile::fake()->image('invoice-screen.png'),
                'caption' => 'The invoice screen',
            ])
            ->assertRedirect();

        $image = $section->images()->first();

        $this->assertNotNull($image);
        $this->assertSame('The invoice screen', $image->caption);
        Storage::disk('public')->assertExists($image->path);
    }

    public function test_uploaded_images_appear_on_the_public_manual(): void
    {
        Storage::fake('public');
        $section = $this->section();

        $this->actingAs($this->admin())->post(route('admin.settings.manual.images.store', $section), [
            'image'   => UploadedFile::fake()->image('screen.png'),
            'caption' => 'The invoice screen',
        ]);

        $this->get(route('manual'))->assertInertia(fn ($page) => $page
            ->has('sections.0.images', 1)
            ->where('sections.0.images.0.caption', 'The invoice screen')
        );
    }

    public function test_non_image_uploads_are_rejected(): void
    {
        Storage::fake('public');
        $section = $this->section();

        $this->actingAs($this->admin())
            ->post(route('admin.settings.manual.images.store', $section), [
                'image' => UploadedFile::fake()->create('payload.php', 16, 'application/x-php'),
            ])
            ->assertSessionHasErrors('image');

        $this->assertSame(0, $section->images()->count());
    }

    public function test_non_admins_cannot_upload_images(): void
    {
        Storage::fake('public');
        $section = $this->section();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post(route('admin.settings.manual.images.store', $section), [
                'image' => UploadedFile::fake()->image('screen.png'),
            ])
            ->assertForbidden();
    }

    public function test_deleting_an_image_removes_the_file(): void
    {
        Storage::fake('public');
        $section = $this->section();

        $this->actingAs($this->admin())->post(route('admin.settings.manual.images.store', $section), [
            'image' => UploadedFile::fake()->image('screen.png'),
        ]);

        $image = $section->images()->first();

        $this->actingAs($this->admin())
            ->delete(route('admin.settings.manual.images.destroy', $image))
            ->assertRedirect();

        Storage::disk('public')->assertMissing($image->path);
        $this->assertDatabaseMissing('manual_images', ['id' => $image->id]);
    }

    public function test_deleting_a_section_cleans_up_its_images(): void
    {
        Storage::fake('public');
        $section = $this->section();

        $this->actingAs($this->admin())->post(route('admin.settings.manual.images.store', $section), [
            'image' => UploadedFile::fake()->image('screen.png'),
        ]);

        $image = $section->images()->first();

        $this->actingAs($this->admin())
            ->delete(route('admin.settings.manual.destroy', $section))
            ->assertRedirect();

        Storage::disk('public')->assertMissing($image->path);
        $this->assertDatabaseMissing('manual_images', ['id' => $image->id]);
        $this->assertDatabaseMissing('manual_sections', ['id' => $section->id]);
    }

    // ── Preview ──────────────────────────────────────────────────────────────

    public function test_preview_renders_markdown_the_same_way_as_the_public_page(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('admin.settings.manual.preview'), ['body' => '## Hello'])
            ->assertOk()
            ->assertJson(['html' => "<h2>Hello</h2>\n"]);
    }

    public function test_non_admins_cannot_use_the_preview_endpoint(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->postJson(route('admin.settings.manual.preview'), ['body' => '## Hello'])
            ->assertForbidden();
    }
}

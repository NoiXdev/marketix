<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\QrCode;
use App\Models\QrTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class QrTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_list_templates_in_their_project(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $template = QrTemplate::create([
            'project_id' => $project->id,
            'name' => 'Brand A',
            'style' => ['foreground' => '#000000', 'background' => '#ffffff'],
        ]);

        $response = $this->actingAs($user)->getJson(
            route('app.project.qr-templates.list', ['project' => $project->id]),
        )->assertOk();

        $response->assertJsonPath('templates.0.id', $template->id);
        $response->assertJsonPath('templates.0.name', 'Brand A');
        $response->assertJsonPath('templates.0.style.foreground', '#000000');
    }

    public function test_a_member_can_create_a_template_in_their_project(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $response = $this->actingAs($user)->postJson(
            route('app.project.qr-templates.store', ['project' => $project->id]),
            [
                'name' => 'Brand A',
                'style' => ['foreground' => '#000000', 'background' => '#ffffff'],
            ],
        )->assertCreated();

        $response->assertJsonPath('template.name', 'Brand A');

        $this->assertDatabaseHas('qr_templates', [
            'project_id' => $project->id,
            'name' => 'Brand A',
        ]);
    }

    public function test_a_member_can_delete_a_template_in_their_project(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $template = QrTemplate::create([
            'project_id' => $project->id,
            'name' => 'Brand A',
            'style' => ['foreground' => '#000000'],
        ]);

        $this->actingAs($user)->deleteJson(
            route('app.project.qr-templates.destroy', ['project' => $project->id, 'qrTemplate' => $template->id]),
        )->assertNoContent();

        $this->assertDatabaseMissing('qr_templates', ['id' => $template->id]);
    }

    public function test_validation_rejects_a_missing_name(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $this->actingAs($user)->postJson(
            route('app.project.qr-templates.store', ['project' => $project->id]),
            ['style' => ['foreground' => '#000000']],
        )->assertJsonValidationErrors('name');
    }

    public function test_a_template_from_another_project_is_not_listed(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $other = Project::create(['name' => 'Other']);
        $otherUser = User::factory()->create();
        $other->users()->attach($otherUser->id, ['role' => 'member']);
        QrTemplate::create([
            'project_id' => $other->id,
            'name' => 'Theirs',
            'style' => ['foreground' => '#000000'],
        ]);

        $response = $this->actingAs($user)->getJson(
            route('app.project.qr-templates.list', ['project' => $project->id]),
        )->assertOk();

        $response->assertJsonCount(0, 'templates');
    }

    public function test_a_member_cannot_delete_another_projects_template(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $other = Project::create(['name' => 'Other']);
        $otherUser = User::factory()->create();
        $other->users()->attach($otherUser->id, ['role' => 'member']);
        $otherTemplate = QrTemplate::create([
            'project_id' => $other->id,
            'name' => 'Theirs',
            'style' => ['foreground' => '#000000'],
        ]);

        // $user (Acme) trying to destroy Other's template via Acme's own project route → 404 (scoped via project).
        $this->actingAs($user)->deleteJson(
            route('app.project.qr-templates.destroy', ['project' => $project->id, 'qrTemplate' => $otherTemplate->id]),
        )->assertNotFound();

        $this->assertDatabaseHas('qr_templates', ['id' => $otherTemplate->id]);
    }

    public function test_a_member_can_rename_a_template_without_touching_its_style(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $template = QrTemplate::create([
            'project_id' => $project->id,
            'name' => 'Brand A',
            'style' => ['foreground' => '#000000'],
        ]);

        $this->actingAs($user)->putJson(
            route('app.project.qr-templates.update', ['project' => $project->id, 'qrTemplate' => $template->id]),
            ['name' => 'Brand B'],
        )->assertOk()->assertJsonPath('template.name', 'Brand B');

        $this->assertSame('Brand B', $template->fresh()->name);
        $this->assertSame(['foreground' => '#000000'], $template->fresh()->style);
    }

    public function test_a_member_can_update_a_templates_style(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $template = QrTemplate::create([
            'project_id' => $project->id,
            'name' => 'Brand A',
            'style' => ['foreground' => '#000000'],
        ]);

        $this->actingAs($user)->putJson(
            route('app.project.qr-templates.update', ['project' => $project->id, 'qrTemplate' => $template->id]),
            ['name' => 'Brand A', 'style' => ['foreground' => '#ff0000']],
        )->assertOk()->assertJsonPath('template.style.foreground', '#ff0000');

        $this->assertSame(['foreground' => '#ff0000'], $template->fresh()->style);
    }

    public function test_updating_a_template_leaves_existing_qr_codes_untouched(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $template = QrTemplate::create([
            'project_id' => $project->id,
            'name' => 'Brand A',
            'style' => ['foreground' => '#000000'],
        ]);

        $qrCode = QrCode::create([
            'project_id' => $project->id,
            'name' => 'Flyer',
            'type' => 'text',
            'is_dynamic' => false,
            'content' => ['text' => 'hello'],
            'style' => ['foreground' => '#000000'],
        ]);

        $this->actingAs($user)->putJson(
            route('app.project.qr-templates.update', ['project' => $project->id, 'qrTemplate' => $template->id]),
            ['name' => 'Brand A', 'style' => ['foreground' => '#ff0000']],
        )->assertOk();

        $this->assertSame(['foreground' => '#000000'], $qrCode->fresh()->style);
    }

    public function test_a_member_cannot_update_another_projects_template(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $other = Project::create(['name' => 'Other']);
        $otherTemplate = QrTemplate::create([
            'project_id' => $other->id,
            'name' => 'Theirs',
            'style' => ['foreground' => '#000000'],
        ]);

        $this->actingAs($user)->putJson(
            route('app.project.qr-templates.update', ['project' => $project->id, 'qrTemplate' => $otherTemplate->id]),
            ['name' => 'Stolen'],
        )->assertNotFound();

        $this->assertSame('Theirs', $otherTemplate->fresh()->name);
    }

    public function test_the_template_management_page_renders_for_a_member(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $this->actingAs($user)
            ->get(route('app.project.qr-templates.index', ['project' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('QrCodes/Templates/Index'));
    }
}

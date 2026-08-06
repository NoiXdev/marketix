<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\QrTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            route('app.project.qr-templates.index', ['project' => $project->id]),
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
            route('app.project.qr-templates.index', ['project' => $project->id]),
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
}

<?php

namespace Tests\Feature\Privacy;

use App\Enums\ControllerRole;
use App\Enums\LegalBasis;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Url;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyNoticeEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_request_the_notice(): void
    {
        [$user, $project] = $this->memberWithProjectAndLink();

        $response = $this->actingAs($user)->getJson(route('app.project.docs.privacy.notice', [
            'project' => $project->id,
            'role' => ControllerRole::OwnCompany->value,
            'legal_basis' => LegalBasis::LegitimateInterest->value,
            'locale' => 'de',
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'sections' => [
                    '*' => ['heading', 'paragraphs'],
                ],
                'text',
                'html',
                'placeholders',
                'warnings' => [
                    '*' => ['key', 'message'],
                ],
            ]);

        $this->assertNotEmpty($response->json('sections'));
        $this->assertStringContainsString('Verantwortlicher', $response->json('text'));
    }

    public function test_the_notice_is_returned_in_the_requested_language_regardless_of_ui_locale(): void
    {
        [$user, $project] = $this->memberWithProjectAndLink();

        session(['locale' => 'de']);

        $response = $this->actingAs($user)->getJson(route('app.project.docs.privacy.notice', [
            'project' => $project->id,
            'role' => ControllerRole::OwnCompany->value,
            'legal_basis' => LegalBasis::LegitimateInterest->value,
            'locale' => 'fr',
        ]));

        $response->assertOk();

        // Real French strings from lang/fr/privacy_notice.php — not merely
        // "different from German".
        $this->assertStringContainsString('Responsable du traitement', $response->json('text'));
        $this->assertStringNotContainsString('Verantwortlicher', $response->json('text'));
    }

    public function test_a_non_member_cannot_request_another_projects_notice(): void
    {
        [$user] = $this->memberWithProjectAndLink();
        $otherProject = Project::create(['name' => 'Other']);

        $this->actingAs($user)->getJson(route('app.project.docs.privacy.notice', [
            'project' => $otherProject->id,
            'role' => ControllerRole::OwnCompany->value,
            'legal_basis' => LegalBasis::LegitimateInterest->value,
            'locale' => 'de',
        ]))->assertForbidden();
    }

    public function test_an_unknown_locale_is_rejected(): void
    {
        [$user, $project] = $this->memberWithProjectAndLink();

        $this->actingAs($user)->getJson(route('app.project.docs.privacy.notice', [
            'project' => $project->id,
            'role' => ControllerRole::OwnCompany->value,
            'legal_basis' => LegalBasis::LegitimateInterest->value,
            'locale' => 'es',
        ]))->assertStatus(422)
            ->assertJsonValidationErrors('locale');
    }

    /** @return array{0: User, 1: Project} */
    private function memberWithProjectAndLink(): array
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);

        $domain = Domain::factory()->create(['project_id' => $project->id]);
        Url::factory()->create(['project_id' => $project->id, 'domain_id' => $domain->id]);

        return [$user, $project];
    }
}

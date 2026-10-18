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

        $user->update(['locale' => 'de']);

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

    /**
     * The generator's buildNoticeQuery() emits the array parameter with the
     * [] suffix PHP needs, so the exact query string it produces is pinned
     * here rather than an array Laravel would re-encode for us.
     */
    public function test_the_query_string_the_generator_builds_switches_activities_off(): void
    {
        [$user, $project] = $this->memberWithProjectAndLink();

        $url = route('app.project.docs.privacy.notice', ['project' => $project->id])
            .'?role=own_company&legal_basis=legitimate_interest&locale=de'
            .'&controller_name=Acme+GmbH&controller_address=Hauptstr.+1'
            .'&disabled_activities%5B%5D=link_clicks&disabled_activities%5B%5D=pixels';

        $control = $this->actingAs($user)->getJson(route('app.project.docs.privacy.notice', [
            'project' => $project->id,
            'role' => ControllerRole::OwnCompany->value,
            'legal_basis' => LegalBasis::LegitimateInterest->value,
            'locale' => 'de',
        ]));

        $control->assertOk();
        $this->assertStringContainsString('Erfassung von Klicks auf Kurzlinks', $control->json('text'));

        $response = $this->actingAs($user)->getJson($url);

        $response->assertOk();
        $this->assertStringNotContainsString('Erfassung von Klicks auf Kurzlinks', $response->json('text'));
    }

    /**
     * Warnings are Marketix UI text shown beside the generator form and are
     * never published, so they follow the interface language even when the
     * notice itself is composed in another one.
     */
    public function test_warnings_stay_in_the_ui_language_when_the_notice_is_generated_in_another(): void
    {
        [$user, $project] = $this->memberWithProjectAndLink();

        // SetLocaleMiddleware reads the user's own locale, so this is what
        // actually puts the interface into German.
        $user->update(['locale' => 'de']);

        $response = $this->actingAs($user)->getJson(route('app.project.docs.privacy.notice', [
            'project' => $project->id,
            'role' => ControllerRole::OwnCompany->value,
            'legal_basis' => LegalBasis::LegitimateInterest->value,
            'locale' => 'fr',
        ]));

        $response->assertOk();

        // The notice is French: no party details were supplied, so the
        // placeholders are French too.
        $this->assertStringContainsString('Responsable du traitement', $response->json('text'));

        $warnings = collect($response->json('warnings'))->keyBy('key');
        $this->assertTrue($warnings->has('missing_party_details'));

        $message = $warnings['missing_party_details']['message'];
        $this->assertStringContainsString('Folgende Pflichtangaben fehlen', $message);
        $this->assertStringNotContainsString('Les champs obligatoires suivants', $message);
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

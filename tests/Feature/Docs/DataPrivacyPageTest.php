<?php

namespace Tests\Feature\Docs;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DataPrivacyPageTest extends TestCase
{
    use RefreshDatabase;

    private function projectForMember(): array
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);

        return [$user, $project];
    }

    public function test_guests_are_redirected_to_login(): void
    {
        [, $project] = $this->projectForMember();

        $this->get(route('app.project.docs.privacy', ['project' => $project->id]))
            ->assertRedirect(route('app.auth.show-login'));
    }

    public function test_a_member_can_view_the_data_privacy_page(): void
    {
        [$user, $project] = $this->projectForMember();

        $this->actingAs($user)
            ->get(route('app.project.docs.privacy', ['project' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Docs/DataPrivacy')
                ->where('statsMonths', (int) config('statistics.retention_months'))
                ->where('analyticsMonths', (int) config('analytics.retention_months'))
                ->has('appName')
            );
    }

    public function test_a_non_member_cannot_view_another_projects_page(): void
    {
        [$user] = $this->projectForMember();
        $otherProject = Project::create(['name' => 'Other']);

        $this->actingAs($user)
            ->get(route('app.project.docs.privacy', ['project' => $otherProject->id]))
            ->assertForbidden();
    }

    public function test_all_locales_define_the_same_privacy_doc_keys(): void
    {
        $flatten = function (array $a, string $prefix = '') use (&$flatten): array {
            $out = [];
            foreach ($a as $k => $v) {
                $key = $prefix === '' ? (string) $k : $prefix.'.'.$k;
                $out = array_merge($out, is_array($v) ? $flatten($v, $key) : [$key]);
            }

            return $out;
        };

        $reference = $flatten(require base_path('lang/de/docs.php'));
        sort($reference);

        foreach (['en', 'fr', 'nl'] as $locale) {
            $keys = $flatten(require base_path("lang/{$locale}/docs.php"));
            sort($keys);
            $this->assertSame($reference, $keys, "lang/{$locale}/docs.php key set differs from German");
        }
    }

    public function test_the_page_no_longer_claims_there_are_no_third_party_cookies(): void
    {
        $de = require base_path('lang/de/docs.php');

        $this->assertStringNotContainsString('Keine Third-Party-Cookies', $de['privacy']['nocollect']['cookies']);
        $this->assertArrayHasKey('hybrid', $de['privacy']['modes']);
    }
}

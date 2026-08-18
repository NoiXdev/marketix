<?php

namespace Tests\Feature\Demo;

use App\Models\Domain;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoQrTargetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Domain $domain;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'demo.enabled' => true,
            'demo.allowed_target_hosts' => ['example.com'],
        ]);

        $this->user = User::factory()->create();
        $this->project = Project::create(['name' => 'Demo', 'locked' => false]);
        $this->project->users()->attach($this->user, ['role' => 'admin', 'active' => true]);
        $this->domain = Domain::create(['project_id' => $this->project->id, 'name' => 'links.example.com']);
    }

    /**
     * A dynamic QR is backed by a real, publicly-resolvable short link, which
     * is the actual abuse vector: domain_id/slug make it create one, and the
     * targeting_* fields exercise the QrCodeRequest::rules()
     * UrlRequest::withDemoTargetRules(array_merge(...)) wrap directly.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function dynamicPayload(array $overrides = []): array
    {
        return array_merge(
            $this->payload('link', ['url' => 'https://example.com/menu']),
            [
                'is_dynamic' => true,
                'domain_id' => $this->domain->id,
                'slug' => 'promo',
            ],
            $overrides
        );
    }

    /** @param array<string, mixed> $content */
    private function payload(string $type, array $content): array
    {
        return [
            'name' => 'Demo QR',
            'type' => $type,
            'is_dynamic' => false,
            'content' => $content,
            'style' => $this->defaultStyle(),
        ];
    }

    /** @return array<string, mixed> */
    private function defaultStyle(): array
    {
        return [
            'foreground' => '#000000',
            'background' => '#ffffff',
            'logo_type' => 'none',
            'logo_size' => 20,
            'module_mode' => 'square',
            'module_rounding' => 0,
            'eye_frame_mode' => 'square',
            'eye_frame_rounding' => 0,
            'eye_ball_mode' => 'square',
            'eye_ball_rounding' => 0,
            'error_correction' => 'M',
            'quiet_zone' => 4,
            'logo_margin' => 2,
            'logo_clear_modules' => true,
            'frame_style' => 'none',
            'frame_text_color' => '#000000',
            'frame_color' => '#000000',
            'frame_background' => '#ffffff',
        ];
    }

    public function test_allowed_link_target_is_accepted(): void
    {
        $this->actingAs($this->user)
            ->post(
                route('app.project.qrcodes.store', ['project' => $this->project->id]),
                $this->payload('link', ['url' => 'https://example.com/menu'])
            )
            ->assertSessionHasNoErrors();
    }

    public function test_foreign_link_target_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(
                route('app.project.qrcodes.store', ['project' => $this->project->id]),
                $this->payload('link', ['url' => 'https://evil-phishing.test/login'])
            )
            ->assertSessionHasErrors('content');
    }

    public function test_non_web_qr_types_are_unaffected(): void
    {
        $this->actingAs($this->user)
            ->post(
                route('app.project.qrcodes.store', ['project' => $this->project->id]),
                $this->payload('phone', ['phone' => '+4915112345678'])
            )
            ->assertSessionHasNoErrors();
    }

    public function test_foreign_dynamic_targeting_url_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(
                route('app.project.qrcodes.store', ['project' => $this->project->id]),
                $this->dynamicPayload([
                    'targeting_geo' => [
                        ['country' => 'US', 'state' => '', 'url' => 'https://evil-phishing.test/us'],
                    ],
                ])
            )
            ->assertSessionHasErrors('targeting_geo.0.url');
    }

    public function test_allowed_dynamic_targeting_url_is_accepted(): void
    {
        $this->actingAs($this->user)
            ->post(
                route('app.project.qrcodes.store', ['project' => $this->project->id]),
                $this->dynamicPayload([
                    'targeting_geo' => [
                        ['country' => 'US', 'state' => '', 'url' => 'https://example.com/us'],
                    ],
                ])
            )
            ->assertSessionHasNoErrors();
    }

    public function test_foreign_dynamic_link_target_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(
                route('app.project.qrcodes.store', ['project' => $this->project->id]),
                $this->dynamicPayload([
                    'content' => ['url' => 'https://evil-phishing.test/login'],
                ])
            )
            ->assertSessionHasErrors('content');
    }

    public function test_no_restriction_when_demo_mode_is_off(): void
    {
        config(['demo.enabled' => false]);

        $this->actingAs($this->user)
            ->post(
                route('app.project.qrcodes.store', ['project' => $this->project->id]),
                $this->payload('link', ['url' => 'https://anywhere.test/page'])
            )
            ->assertSessionHasNoErrors();
    }
}

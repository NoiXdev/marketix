<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Models\Domain;
use App\Models\Project;
use App\Models\QrCode;
use App\Models\Url;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlugReuseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Domain $domain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::create(['name' => 'Acme']);
        $this->user->projects()->attach($this->project);
        $this->domain = Domain::create(['project_id' => $this->project->id, 'name' => 'links.test']);
    }

    private function link(string $slug = 'promo'): Url
    {
        return Url::factory()->create(['project_id' => $this->project->id, 'domain_id' => $this->domain->id, 'slug' => $slug]);
    }

    private function storeLink(string $slug = 'promo')
    {
        return $this->actingAs($this->user)->post(route('app.project.links.store', ['project' => $this->project->id]), [
            'domain_id' => $this->domain->id,
            'slug' => $slug,
            'url' => 'https://example.com/landing',
            'type' => RedirectType::REDIRECT->value,
            'status' => UrlStatus::ACTIVATED->value,
        ]);
    }

    public function test_a_link_can_reuse_the_slug_of_a_deleted_link(): void
    {
        $this->link()->delete();

        $this->storeLink()->assertSessionHasNoErrors();

        $this->assertSame(1, Url::where('slug', 'promo')->count());
        $this->assertSame(2, Url::withTrashed()->where('slug', 'promo')->count());
    }

    public function test_the_slug_of_an_active_link_is_still_taken(): void
    {
        $this->link();

        $this->storeLink()->assertSessionHasErrors('slug');
    }

    public function test_a_dynamic_qr_code_gets_a_fresh_link_instead_of_a_deleted_one(): void
    {
        $deleted = $this->link();
        $deleted->delete();

        $this->actingAs($this->user)->post(route('app.project.qrcodes.store', ['project' => $this->project->id]), [
            'name' => 'Promo',
            'type' => 'link',
            'is_dynamic' => true,
            'domain_id' => $this->domain->id,
            'slug' => 'promo',
            'content' => ['url' => 'https://example.com/landing'],
            'style' => [
                'foreground' => '#000000', 'background' => '#ffffff',
                'logo_type' => 'none', 'logo_name' => '', 'logo_data' => '', 'logo_size' => 30,
                'module_mode' => 'square', 'module_rounding' => 0,
                'eye_frame_mode' => 'square', 'eye_frame_rounding' => 0,
                'eye_ball_mode' => 'square', 'eye_ball_rounding' => 0,
                'error_correction' => 'Q', 'quiet_zone' => 4,
                'logo_margin' => 1, 'logo_clear_modules' => true,
                'frame_style' => 'none', 'frame_text_color' => '#000000',
                'frame_color' => '#000000', 'frame_background' => '#ffffff',
            ],
        ])->assertSessionHasNoErrors();

        $this->assertNotSame($deleted->id, QrCode::firstOrFail()->url_id);
        $this->assertSoftDeleted('urls', ['id' => $deleted->id]);
    }

    public function test_the_database_rejects_two_active_links_with_the_same_slug(): void
    {
        $this->link();

        $this->expectException(UniqueConstraintViolationException::class);

        $this->link();
    }
}

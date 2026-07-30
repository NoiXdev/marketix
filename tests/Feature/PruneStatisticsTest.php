<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Statistic;
use App\Models\Url;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PruneStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUrl(int $clicks = 0): Url
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $domain = Domain::create(['project_id' => $project->id, 'name' => 'links.test']);

        return Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => 'promo',
            'url' => 'https://example.com',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
            'clicks' => $clicks,
        ]);
    }

    public function test_prunes_rows_older_than_retention_and_keeps_recent(): void
    {
        $url = $this->makeUrl();

        $old = Statistic::factory()->forUrl($url)->create(['created_at' => now()->subMonths(13)]);
        $recent = Statistic::factory()->forUrl($url)->create(['created_at' => now()->subDays(5)]);

        Artisan::call('statistics:prune');

        $this->assertDatabaseMissing('statistics', ['id' => $old->id]);
        $this->assertDatabaseHas('statistics', ['id' => $recent->id]);
    }

    public function test_respects_configured_retention_and_is_idempotent(): void
    {
        config(['statistics.retention_months' => 1]);
        $url = $this->makeUrl();

        $twoMonths = Statistic::factory()->forUrl($url)->create(['created_at' => now()->subMonths(2)]);

        Artisan::call('statistics:prune');
        Artisan::call('statistics:prune'); // idempotent — no error on re-run

        $this->assertDatabaseMissing('statistics', ['id' => $twoMonths->id]);
    }

    public function test_does_not_touch_url_click_counters(): void
    {
        $url = $this->makeUrl(clicks: 5);
        Statistic::factory()->forUrl($url)->create(['created_at' => now()->subMonths(13)]);

        Artisan::call('statistics:prune');

        $this->assertSame(5, $url->fresh()->clicks);
    }
}

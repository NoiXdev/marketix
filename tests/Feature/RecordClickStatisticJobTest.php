<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Jobs\RecordClickStatisticJob;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Statistic;
use App\Models\Url;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordClickStatisticJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeUrl(): Url
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $domain = Domain::create(['project_id' => $project->id, 'name' => 'links.test']);

        return Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => 'promo',
            'url' => 'https://example.com/default',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
        ]);
    }

    private function dispatch(Url $url, string $hash): void
    {
        (new RecordClickStatisticJob(
            $url->id,
            $url->project_id,
            $hash,
            'UA/1.0',
            null,
            'en',
            ['country' => 'Germany', 'city' => 'Berlin'],
        ))->handle();
    }

    public function test_stores_visitor_hash_and_geo(): void
    {
        $url = $this->makeUrl();

        $this->dispatch($url, 'hash-aaa');

        $this->assertDatabaseHas('statistics', [
            'url_id' => $url->id,
            'visitor_hash' => 'hash-aaa',
            'country' => 'Germany',
            'city' => 'Berlin',
        ]);
    }

    public function test_same_hash_same_day_counts_unique_once(): void
    {
        $url = $this->makeUrl();

        $this->dispatch($url, 'hash-same');
        $this->dispatch($url, 'hash-same');

        $this->assertSame(2, Statistic::where('url_id', $url->id)->count());
        $this->assertSame(2, $url->fresh()->clicks);
        $this->assertSame(1, $url->fresh()->unique_clicks);
    }

    public function test_distinct_hashes_count_as_distinct_uniques(): void
    {
        $url = $this->makeUrl();

        $this->dispatch($url, 'hash-aaa');
        $this->dispatch($url, 'hash-bbb');

        $this->assertSame(2, $url->fresh()->unique_clicks);
    }

    public function test_country_code_column_is_persistable(): void
    {
        $url = $this->makeUrl();

        \App\Models\Statistic::create([
            'project_id' => $url->project_id,
            'url_id' => $url->id,
            'visitor_hash' => 'hash-cc',
            'country' => 'Germany',
            'country_code' => 'DE',
        ]);

        $this->assertDatabaseHas('statistics', [
            'url_id' => $url->id,
            'country_code' => 'DE',
        ]);
    }
}

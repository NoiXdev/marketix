<?php

namespace Tests\Feature;

use App\Models\Statistic;
use App\Models\Url;
use App\Models\User;
use App\Models\Project;
use App\Models\Domain;
use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackfillStatisticCountryCodesTest extends TestCase
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

    public function test_backfills_known_names_and_leaves_unknown_null(): void
    {
        $url = $this->makeUrl();

        $known = Statistic::create([
            'project_id' => $url->project_id, 'url_id' => $url->id,
            'visitor_hash' => 'h1', 'country' => 'Germany', 'country_code' => null,
        ]);
        $unknown = Statistic::create([
            'project_id' => $url->project_id, 'url_id' => $url->id,
            'visitor_hash' => 'h2', 'country' => 'Atlantis', 'country_code' => null,
        ]);

        Artisan::call('statistics:backfill-country-codes');

        $this->assertSame('DE', $known->fresh()->country_code);
        $this->assertNull($unknown->fresh()->country_code);
    }

    public function test_does_not_overwrite_existing_codes(): void
    {
        $url = $this->makeUrl();

        $row = Statistic::create([
            'project_id' => $url->project_id, 'url_id' => $url->id,
            'visitor_hash' => 'h3', 'country' => 'Germany', 'country_code' => 'XX',
        ]);

        Artisan::call('statistics:backfill-country-codes');

        $this->assertSame('XX', $row->fresh()->country_code);
    }
}

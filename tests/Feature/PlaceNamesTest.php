<?php

namespace Tests\Feature;

use App\Jobs\RecordPageViewJob;
use App\Models\GeoName;
use App\Models\PageView;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Models\Visit;
use App\Support\Geo\PlaceNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PlaceNamesTest extends TestCase
{
    use RefreshDatabase;

    private PlaceNames $places;

    protected function setUp(): void
    {
        parent::setUp();
        $this->places = app(PlaceNames::class);
    }

    public function test_remember_stores_every_translation_once_per_place(): void
    {
        $this->places->remember(PlaceNames::CITY, 'CH', 'Zurich', ['en' => 'Zurich', 'de' => 'Zürich', 'fr' => 'Zurich']);
        $this->places->remember(PlaceNames::CITY, 'CH', 'Zurich', ['en' => 'Zurich', 'de' => 'Zürich', 'fr' => 'Zurich']);

        $this->assertSame(1, GeoName::count());
        $this->assertSame('Zürich', GeoName::firstOrFail()->names['de']);
    }

    public function test_remember_skips_incomplete_places(): void
    {
        $this->places->remember(PlaceNames::CITY, null, 'Zurich', ['de' => 'Zürich']);
        $this->places->remember(PlaceNames::CITY, 'CH', null, ['de' => 'Zürich']);
        $this->places->remember(PlaceNames::CITY, 'CH', 'Zurich', []);

        $this->assertSame(0, GeoName::count());
    }

    public function test_localize_uses_the_ui_language_and_falls_back_to_english(): void
    {
        $this->places->remember(PlaceNames::REGION, 'AT', 'Tyrol', ['en' => 'Tyrol', 'de' => 'Tirol', 'fr' => 'Tyrol']);
        $rows = [['region' => 'Tyrol', 'country_code' => 'AT'], ['region' => 'Unknown Land', 'country_code' => 'XX']];

        App::setLocale('de');
        $this->assertSame(['Tirol', 'Unknown Land'], $this->places->localize($rows, PlaceNames::REGION, 'region')->pluck('region_name')->all());

        App::setLocale('nl'); // GeoLite2 has no Dutch names
        $this->assertSame(['Tyrol', 'Unknown Land'], $this->places->localize($rows, PlaceNames::REGION, 'region')->pluck('region_name')->all());
    }

    public function test_localize_tells_places_with_the_same_name_apart_by_country(): void
    {
        $this->places->remember(PlaceNames::CITY, 'CH', 'Bern', ['en' => 'Bern', 'fr' => 'Berne']);
        $this->places->remember(PlaceNames::CITY, 'US', 'Bern', ['en' => 'Bern', 'fr' => 'Bern (Indiana)']);
        App::setLocale('fr');

        $rows = $this->places->localize([['city' => 'Bern', 'country_code' => 'US'], ['city' => 'Bern', 'country_code' => 'CH']], PlaceNames::CITY, 'city');

        $this->assertSame(['Bern (Indiana)', 'Berne'], $rows->pluck('city_name')->all());
    }

    public function test_page_views_record_region_and_city_translations(): void
    {
        $site = Site::factory()->create();

        (new RecordPageViewJob($site->id, $site->project_id, 'v1', 'Mozilla/5.0', '/', null, 'de', [
            'country' => 'Austria', 'country_code' => 'AT', 'region' => 'Tyrol', 'city' => 'Innsbruck',
            'region_names' => ['en' => 'Tyrol', 'de' => 'Tirol'], 'city_names' => ['en' => 'Innsbruck', 'de' => 'Innsbruck'],
        ]))->handle();

        $this->assertDatabaseHas('geo_names', ['type' => 'region', 'country_code' => 'AT', 'name' => 'Tyrol']);
        $this->assertDatabaseHas('geo_names', ['type' => 'city', 'country_code' => 'AT', 'name' => 'Innsbruck']);
    }

    public function test_audience_tab_and_filter_chip_show_localized_names(): void
    {
        $user = User::factory()->create(['locale' => 'de']);
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);
        $site = Site::factory()->forProject($project)->create();
        $visit = Visit::factory()->forSite($site)->create();
        PageView::factory()->forVisit($visit)->create(['country_code' => 'AT', 'region' => 'Tyrol', 'city' => 'Vienna']);
        $this->places->remember(PlaceNames::REGION, 'AT', 'Tyrol', ['en' => 'Tyrol', 'de' => 'Tirol']);
        $this->places->remember(PlaceNames::CITY, 'AT', 'Vienna', ['en' => 'Vienna', 'de' => 'Wien']);

        $this->actingAs($user)
            ->get(route('app.project.analytics.show', ['project' => $project->id, 'site' => $site->id]).'?tab=audience&filters[city]=Vienna')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('regions.0.region', 'Tyrol')
                ->where('regions.0.region_name', 'Tirol')
                ->where('cities.0.city_name', 'Wien')
                ->where('filterLabels.city', 'Wien')
            );
    }
}

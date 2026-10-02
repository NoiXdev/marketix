<?php

namespace Tests\Feature\Dashboards;

use App\Enums\WidgetType;
use App\Models\Project;
use App\Support\Widgets\WidgetRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidgetRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_kpi_data_returns_value_and_delta(): void
    {
        $project = Project::create(['name' => 'Acme']);
        $registry = app(WidgetRegistry::class);

        $data = $registry->data(WidgetType::Kpi, $project->id, ['metric' => 'clicks', 'days' => 30, 'title' => null]);

        $this->assertArrayHasKey('value', $data);
        $this->assertArrayHasKey('deltaPct', $data);
        $this->assertIsInt($data['value']);
    }

    public function test_top_list_links_returns_rows(): void
    {
        $project = Project::create(['name' => 'Acme']);
        $registry = app(WidgetRegistry::class);

        $data = $registry->data(WidgetType::TopList, $project->id, ['dimension' => 'links', 'limit' => 5, 'days' => 30, 'title' => null]);

        $this->assertArrayHasKey('rows', $data);
        $this->assertIsArray($data['rows']);
    }

    public function test_config_rules_reject_bad_metric(): void
    {
        $registry = app(WidgetRegistry::class);
        $rules = $registry->configRules(WidgetType::Kpi);
        $this->assertStringContainsString('in:clicks', implode(',', $rules['metric']));
    }
}

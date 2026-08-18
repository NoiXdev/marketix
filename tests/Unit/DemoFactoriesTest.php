<?php

namespace Tests\Unit;

use App\Models\Pixel;
use App\Models\Project;
use App\Models\QrCode;
use App\Models\QrTemplate;
use App\Models\ScheduledReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoFactoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_four_factories_produce_persisted_records(): void
    {
        $project = Project::create(['name' => 'Demo', 'locked' => false]);
        $user = User::factory()->create();

        $this->assertNotNull(QrCode::factory()->forProject($project)->create()->id);
        $this->assertNotNull(QrTemplate::factory()->forProject($project)->create()->id);
        $this->assertNotNull(Pixel::factory()->forProject($project)->create()->id);
        $this->assertNotNull(ScheduledReport::factory()->forProject($project, $user)->create()->id);
    }
}

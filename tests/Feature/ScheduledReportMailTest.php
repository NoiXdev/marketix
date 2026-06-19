<?php

namespace Tests\Feature;

use App\Enums\ReportFrequency;
use App\Mail\ScheduledReportMail;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ScheduledReportMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Route::get('/project/{project}/settings/notifications', fn () => '')
            ->name('app.project.settings.notifications');
        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    private function payload(): array
    {
        return [
            'totalClicks' => 42,
            'uniqueClicks' => 30,
            'clicksChange' => ['value' => 42, 'previous' => 21, 'percent' => 100, 'isNew' => false],
            'uniqueChange' => ['value' => 30, 'previous' => 30, 'percent' => 0, 'isNew' => false],
            'topLinks' => [['slug' => 'go', 'domain' => 'acme.test', 'clicks' => 40]],
            'topCountries' => [['label' => 'Germany', 'count' => 25]],
            'topReferrers' => [['label' => 'google.com', 'count' => 12]],
            'timeSeries' => [['date' => '2026-06-18', 'clicks' => 42, 'unique' => 30]],
            'periodLabel' => '18 Jun 2026',
            'frequencyLabel' => 'Daily',
        ];
    }

    public function test_subject_includes_frequency_project_and_period(): void
    {
        $project = Project::factory()->create(['name' => 'Acme']);
        $mail = new ScheduledReportMail($project, ReportFrequency::Daily, '18 Jun 2026', $this->payload());

        $this->assertSame('Your Daily report for Acme — 18 Jun 2026', $mail->envelope()->subject);
    }

    public function test_rendered_body_shows_totals_and_top_links(): void
    {
        $project = Project::factory()->create(['name' => 'Acme']);
        $mail = new ScheduledReportMail($project, ReportFrequency::Daily, '18 Jun 2026', $this->payload());

        $rendered = $mail->render();

        $this->assertStringContainsString('42', $rendered);   // total clicks
        $this->assertStringContainsString('Germany', $rendered);
        $this->assertStringContainsString('acme.test/go', $rendered);
    }
}

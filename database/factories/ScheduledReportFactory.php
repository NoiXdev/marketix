<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ScheduledReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ScheduledReport> */
class ScheduledReportFactory extends Factory
{
    protected $model = ScheduledReport::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            // Registry keys verified against App\Reports\ReportTypeRegistry:
            // project_summary, link, site_analytics.
            'type' => 'project_summary',
            'subject_id' => null,
            'frequency' => 'weekly',
            'weekday' => 1,
            'day_of_month' => null,
            'period' => 'last_7_days',
            'formats' => ['csv' => false, 'pdf' => true],
            'recipients' => ['demo@example.com'],
            'active' => true,
            'last_sent_at' => null,
            'next_run_at' => now()->addWeek(),
        ];
    }

    public function forProject(Project $project, User $creator): static
    {
        return $this->state(fn () => [
            'project_id' => $project->id,
            'created_by' => $creator->id,
        ]);
    }
}

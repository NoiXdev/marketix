<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Enums\RedirectType;
use App\Enums\ReportFrequency;
use App\Enums\UrlStatus;
use App\Mail\ScheduledReportMail;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Statistic;
use App\Models\Url;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendScheduledReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-06-19 08:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function projectWithUrl(): array
    {
        $project = Project::factory()->create();
        $domain = Domain::create(['project_id' => $project->id, 'name' => 'acme.test']);
        $url = Url::create([
            'project_id' => $project->id, 'domain_id' => $domain->id,
            'user_id' => User::factory()->create()->id,
            'slug' => 'go', 'url' => 'https://example.test',
            'type' => RedirectType::cases()[0], 'status' => UrlStatus::ACTIVATED, 'archived' => false,
            'targeting_geo' => [], 'targeting_device' => [], 'targeting_language' => [], 'targeting_ab' => [],
        ]);

        return [$project, $url];
    }

    public function test_sends_only_to_active_users_opted_into_the_cadence(): void
    {
        Mail::fake();
        [$project, $url] = $this->projectWithUrl();
        Statistic::factory()->count(2)->forUrl($url)->create(['created_at' => '2026-06-18 10:00:00']);

        $daily = User::factory()->create();
        $weekly = User::factory()->create();
        $inactive = User::factory()->create();
        $off = User::factory()->create();

        $project->users()->attach($daily, ['role' => ProjectRole::Member->value, 'active' => true, 'report_frequency' => 'daily']);
        $project->users()->attach($weekly, ['role' => ProjectRole::Member->value, 'active' => true, 'report_frequency' => 'weekly']);
        $project->users()->attach($inactive, ['role' => ProjectRole::Member->value, 'active' => false, 'report_frequency' => 'daily']);
        $project->users()->attach($off, ['role' => ProjectRole::Member->value, 'active' => true, 'report_frequency' => 'off']);

        $this->artisan('marketix:reports:send', ['--cadence' => 'daily'])->assertSuccessful();

        Mail::assertQueued(ScheduledReportMail::class, 1);
        Mail::assertQueued(ScheduledReportMail::class, fn ($mail) => $mail->hasTo($daily->email));
    }

    public function test_sends_even_when_there_were_zero_clicks(): void
    {
        Mail::fake();
        [$project] = $this->projectWithUrl();
        $user = User::factory()->create();
        $project->users()->attach($user, ['role' => ProjectRole::Member->value, 'active' => true, 'report_frequency' => 'daily']);

        $this->artisan('marketix:reports:send', ['--cadence' => 'daily'])->assertSuccessful();

        Mail::assertQueued(ScheduledReportMail::class, 1);
    }

    public function test_invalid_or_off_cadence_is_rejected_and_sends_nothing(): void
    {
        Mail::fake();

        $this->artisan('marketix:reports:send', ['--cadence' => 'off'])->assertFailed();
        $this->artisan('marketix:reports:send', ['--cadence' => 'yearly'])->assertFailed();

        Mail::assertNothingQueued();
    }
}

<?php

namespace Tests\Feature\ActivityLog;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class RetentionTest extends TestCase
{
    public function test_activitylog_clean_is_scheduled(): void
    {
        $schedule = app(Schedule::class);
        $commands = collect($schedule->events())->map(fn ($e) => $e->command ?? '')->implode(' ');

        $this->assertStringContainsString('activitylog:clean', $commands);
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\ReportFrequency;
use App\Mail\ScheduledReportMail;
use App\Models\Project;
use App\Models\User;
use App\Reports\ReportPeriod;
use App\Reports\ScheduledReportData;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendScheduledReports extends Command
{
    protected $signature = 'marketix:reports:send {--cadence=}';

    protected $description = 'Queue statistics report emails for users opted into the given cadence.';

    public function handle(ScheduledReportData $assembler): int
    {
        $cadence = ReportFrequency::tryFrom((string) $this->option('cadence'));

        if ($cadence === null || ! $cadence->isSendable()) {
            $this->error('Invalid --cadence. Use one of: daily, weekly, monthly.');

            return self::FAILURE;
        }

        $period = ReportPeriod::for($cadence, CarbonImmutable::now());

        $rows = DB::table('project_user')
            ->where('report_frequency', $cadence->value)
            ->where('active', true)
            ->get(['project_id', 'user_id']);

        $queued = 0;

        foreach ($rows->groupBy('project_id') as $projectId => $group) {
            $project = Project::find($projectId);
            if ($project === null) {
                continue;
            }

            try {
                $payload = $assembler->build($project, $period);

                $users = User::whereIn('id', $group->pluck('user_id'))->get();
                foreach ($users as $user) {
                    Mail::to($user->email)->queue(
                        new ScheduledReportMail($project, $cadence, $period->label(), $payload)
                    );
                    $queued++;
                }
            } catch (\Throwable $e) {
                report($e);
                $this->error("Failed to send reports for project {$projectId}: {$e->getMessage()}");
                continue;
            }
        }

        $this->info("Queued {$queued} {$cadence->value} report(s).");

        return self::SUCCESS;
    }
}

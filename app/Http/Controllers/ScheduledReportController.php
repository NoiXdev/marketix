<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduledReportRequest;
use App\Jobs\SendScheduledReport;
use App\Models\ScheduledReport;
use App\Reports\NextRunCalculator;
use App\Reports\ReportTypeRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ScheduledReportController extends Controller
{
    public function index(Request $request)
    {
        $project = $request->get('project');

        return inertia('Reports/Index', [
            'reports' => $project->scheduledReports()->latest()->get()->map(fn (ScheduledReport $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'type' => $r->type,
                'frequency' => $r->frequency,
                'next_run_at' => $r->next_run_at?->toISOString(),
                'active' => $r->active,
            ]),
        ]);
    }

    public function create(Request $request, ReportTypeRegistry $registry)
    {
        $project = $request->get('project');

        return inertia('Reports/Create', [
            'links' => $project->urls()->get(['id', 'slug']),
            'sites' => $project->sites()->get(['id', 'name']),
            'types' => array_keys($registry->all()),
        ]);
    }

    public function store(ScheduledReportRequest $request)
    {
        $project = $request->get('project');
        $data = $request->validated();

        $data['next_run_at'] = NextRunCalculator::next(
            $data['frequency'],
            $data['weekday'] ?? null,
            $data['day_of_month'] ?? null,
            config('reports.send_hour'),
            CarbonImmutable::now(),
        );

        $project->scheduledReports()->create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('app.project.reports.index')
            ->with('success', 'Report created.');
    }

    public function edit(Request $request, ReportTypeRegistry $registry, string $report)
    {
        $project = $request->get('project');
        $model = $project->scheduledReports()->findOrFail($report);

        return inertia('Reports/Edit', [
            'report' => [
                'id' => $model->id,
                'name' => $model->name,
                'type' => $model->type,
                'subject_id' => $model->subject_id,
                'frequency' => $model->frequency,
                'weekday' => $model->weekday,
                'day_of_month' => $model->day_of_month,
                'period' => $model->period,
                'formats' => $model->formats,
                'recipients' => $model->recipients,
                'active' => $model->active,
            ],
            'links' => $project->urls()->get(['id', 'slug']),
            'sites' => $project->sites()->get(['id', 'name']),
            'types' => array_keys($registry->all()),
        ]);
    }

    public function update(ScheduledReportRequest $request, string $report)
    {
        $project = $request->get('project');
        $model = $project->scheduledReports()->findOrFail($report);
        $data = $request->validated();

        $data['next_run_at'] = NextRunCalculator::next(
            $data['frequency'],
            $data['weekday'] ?? null,
            $data['day_of_month'] ?? null,
            config('reports.send_hour'),
            CarbonImmutable::now(),
        );

        $model->update($data);

        return redirect()->route('app.project.reports.index')
            ->with('success', 'Report updated.');
    }

    public function destroy(Request $request, string $report)
    {
        $project = $request->get('project');
        $project->scheduledReports()->findOrFail($report)->delete();

        return redirect()->route('app.project.reports.index')
            ->with('success', 'Report deleted.');
    }

    public function toggle(Request $request, string $report)
    {
        $project = $request->get('project');
        $model = $project->scheduledReports()->findOrFail($report);

        $active = ! $model->active;
        $attributes = ['active' => $active];

        if ($active) {
            $attributes['next_run_at'] = NextRunCalculator::next(
                $model->frequency,
                $model->weekday,
                $model->day_of_month,
                config('reports.send_hour'),
                CarbonImmutable::now(),
            );
        }

        $model->update($attributes);

        return redirect()->back()->with('success', 'Report updated.');
    }

    public function sendNow(Request $request, string $report)
    {
        $project = $request->get('project');
        $model = $project->scheduledReports()->findOrFail($report);

        SendScheduledReport::dispatch($model, [$request->user()->email]);

        return redirect()->back()->with('success', trans('reports.index.sent_now_flash'));
    }
}

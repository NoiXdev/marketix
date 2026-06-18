<?php

namespace App\Http\Controllers;

use App\Models\Url;
use App\Reports\ReportDataService;
use App\Reports\ReportDateRange;
use Illuminate\Http\Request;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReportController extends Controller
{
    public function __construct(private readonly ReportDataService $reports) {}

    public function downloadProject(Request $request)
    {
        $project = $request->get('project');
        $range = $this->range($request);
        $data = $this->reports->forProject($project, $range);

        return Pdf::view('reports.project', $data->toArray())
            ->format('a4')
            ->name($this->filename('report', $project->name, $range))
            ->download();
    }

    public function downloadLink(Request $request, string $url)
    {
        $project = $request->get('project');
        $model = Url::where('project_id', $project->id)->findOrFail($url);
        $range = $this->range($request);
        $data = $this->reports->forUrl($model, $range);

        return Pdf::view('reports.link', $data->toArray())
            ->format('a4')
            ->name($this->filename('link-'.$model->slug, $project->name, $range))
            ->download();
    }

    private function range(Request $request): ReportDateRange
    {
        try {
            return ReportDateRange::fromRequest($request->only(['range', 'from', 'to']));
        } catch (\InvalidArgumentException $e) {
            throw new HttpException(422, $e->getMessage());
        }
    }

    private function filename(string $prefix, string $project, ReportDateRange $range): string
    {
        $slug = str($project)->slug();

        return "{$prefix}-{$slug}-{$range->start()->format('Ymd')}-{$range->end()->format('Ymd')}.pdf";
    }
}

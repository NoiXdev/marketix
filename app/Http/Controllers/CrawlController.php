<?php

namespace App\Http\Controllers;

use App\Enums\CrawlMode;
use App\Http\Requests\StoreCrawlRequest;
use App\Jobs\RunCrawlJob;
use App\Models\Crawl;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrawlController extends Controller
{
    public function index(Request $request)
    {
        $project = $request->get('project');

        return inertia('Crawls/Index', [
            'crawls' => $project->crawls()->latest()->get()->map(fn (Crawl $c) => [
                'id' => $c->id,
                'start_url' => $c->start_url,
                'mode' => $c->mode->value,
                'status' => $c->status->value,
                'pages_crawled' => $c->pages_crawled,
                'created_at' => $c->created_at->toISOString(),
            ]),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Crawls/Create', [
            'modes' => CrawlMode::options(),
        ]);
    }

    public function store(StoreCrawlRequest $request)
    {
        $project = $request->get('project');

        $crawl = $project->crawls()->create($request->validated() + ['status' => 'queued']);
        RunCrawlJob::dispatch($crawl);

        return redirect()
            ->route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id])
            ->with('success', __('crawler.started'));
    }

    public function show(Request $request, string $crawl)
    {
        $project = $request->get('project');
        $model = $project->crawls()->findOrFail($crawl);

        $pages = $model->pages()
            ->orderBy('depth')
            ->paginate(50)
            ->through(fn ($p) => [
                'id' => $p->id,
                'url' => $p->url,
                'status_code' => $p->status_code,
                'title' => $p->title,
                'is_indexable' => $p->is_indexable,
                'inlinks_count' => $p->inlinks_count,
                'depth' => $p->depth,
                'issues' => $p->issues ?? [],
            ]);

        return inertia('Crawls/Show', [
            'crawl' => [
                'id' => $model->id,
                'start_url' => $model->start_url,
                'mode' => $model->mode->value,
                'status' => $model->status->value,
                'pages_crawled' => $model->pages_crawled,
                'summary' => $model->summary ?? [],
                'error' => $model->error,
            ],
            'pages' => $pages,
        ]);
    }

    public function pageDetail(Request $request, string $crawl, string $page)
    {
        $project = $request->get('project');
        $model = $project->crawls()->findOrFail($crawl);
        $pageModel = $model->pages()->with('outLinks')->findOrFail($page);

        return inertia('Crawls/Page', [
            'crawlId' => $model->id,
            'page' => [
                'url' => $pageModel->url,
                'final_url' => $pageModel->final_url,
                'status_code' => $pageModel->status_code,
                'redirect_chain' => $pageModel->redirect_chain,
                'title' => $pageModel->title,
                'meta_description' => $pageModel->meta_description,
                'canonical' => $pageModel->canonical,
                'meta_robots' => $pageModel->meta_robots,
                'word_count' => $pageModel->word_count,
                'headings' => $pageModel->headings ?? [],
                'is_indexable' => $pageModel->is_indexable,
                'indexability_reason' => $pageModel->indexability_reason,
                'in_sitemap' => $pageModel->in_sitemap,
                'inlinks_count' => $pageModel->inlinks_count,
                'structured_data' => $pageModel->structured_data ?? [],
                'images_missing_alt' => $pageModel->images_missing_alt ?? [],
                'issues' => $pageModel->issues ?? [],
                'out_links' => $pageModel->outLinks->map(fn ($l) => [
                    'to_url' => $l->to_url, 'type' => $l->type, 'anchor' => $l->anchor,
                ]),
            ],
        ]);
    }

    public function destroy(Request $request, string $crawl)
    {
        $project = $request->get('project');
        $project->crawls()->findOrFail($crawl)->delete();

        return redirect()
            ->route('app.project.crawls.index', ['project' => $project->id])
            ->with('success', __('crawler.deleted'));
    }

    public function export(Request $request, string $crawl): StreamedResponse
    {
        $project = $request->get('project');
        $model = $project->crawls()->findOrFail($crawl);

        return response()->streamDownload(function () use ($model) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['url', 'status_code', 'title', 'is_indexable', 'inlinks_count', 'depth', 'issues']);
            $model->pages()->orderBy('depth')->chunk(200, function ($pages) use ($out) {
                foreach ($pages as $p) {
                    fputcsv($out, [
                        $this->sanitizeCsvCell($p->url),
                        $p->status_code,
                        $this->sanitizeCsvCell($p->title),
                        $p->is_indexable ? 1 : 0,
                        $p->inlinks_count,
                        $p->depth,
                        $this->sanitizeCsvCell(implode('|', $p->issues ?? [])),
                    ]);
                }
            });
            fclose($out);
        }, "crawl-{$model->id}.csv", ['Content-Type' => 'text/csv']);
    }

    /**
     * Prevent CSV formula injection: if a cell's first character would be interpreted
     * by Excel/Sheets as the start of a formula or control sequence, prefix it with a
     * single quote so it's treated as literal text.
     */
    private function sanitizeCsvCell(?string $value): string
    {
        $value = (string) $value;

        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}

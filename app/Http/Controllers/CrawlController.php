<?php

namespace App\Http\Controllers;

use App\Crawler\CheckCatalog;
use App\Crawler\IssueCategory;
use App\Crawler\IssueCode;
use App\Enums\CrawlMode;
use App\Http\Requests\StoreCrawlRequest;
use App\Jobs\RunCrawlJob;
use App\Models\Crawl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
                'started_at' => $c->started_at?->toISOString(),
                'finished_at' => $c->finished_at?->toISOString(),
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

        $category = $request->query('category');   // content type (existing)
        $group = $request->query('group');         // issue category (new)
        $issue = $request->query('issue');         // single check (existing/new)
        $view = $request->query('view');
        $resourceType = $request->query('resource_type');
        $resource = $request->query('resource');

        $query = $model->pages()->orderBy('depth');

        if (is_string($category) && $category !== '') {
            $query->where('content_category', $category);
        }

        if (is_string($issue) && $issue !== '' && in_array($issue, CheckCatalog::activeCodes(), true)) {
            $query->whereJsonContains('issues', $issue);
        } elseif (is_string($group) && $group !== '') {
            $codes = CheckCatalog::activeCodesForCategory($group);
            $query->where(function ($q) use ($codes) {
                foreach ($codes as $code) {
                    $q->orWhereJsonContains('issues', $code);
                }
                if ($codes === []) {
                    $q->whereRaw('1 = 0'); // category has only planned checks → no rows
                }
            });
        }

        $pages = $query
            ->paginate(50)
            ->withQueryString()
            ->through(fn ($p) => [
                'id' => $p->id,
                'url' => $p->url,
                'status_code' => $p->status_code,
                'title' => $p->title,
                'content_category' => $p->content_category,
                'content_type' => $p->content_type,
                'size_bytes' => $p->size_bytes,
                'is_indexable' => $p->is_indexable,
                'inlinks_count' => $p->inlinks_count,
                'depth' => $p->depth,
                'issues' => $p->issues ?? [],
            ]);

        $categories = $model->pages()
            ->whereNotNull('content_category')
            ->distinct()
            ->orderBy('content_category')
            ->pluck('content_category');

        // Per-code and per-category counts, computed in one pass over the stored issues.
        $perCode = [];
        $perCategory = [];
        foreach ($model->pages()->pluck('issues') as $issues) {
            $seenCategories = [];
            foreach (array_unique($issues ?? []) as $code) {
                $perCode[$code] = ($perCode[$code] ?? 0) + 1;
                $cat = CheckCatalog::categoryOf($code);
                if ($cat !== null && CheckCatalog::isProblemCode($code)) {
                    $seenCategories[$cat] = true;
                }
            }
            foreach (array_keys($seenCategories) as $cat) {
                $perCategory[$cat] = ($perCategory[$cat] ?? 0) + 1;
            }
        }

        // Catalogue grouped by category, in enum order, with counts.
        $catalog = [];
        foreach (IssueCategory::cases() as $cat) {
            $checks = [];
            foreach (CheckCatalog::all() as $entry) {
                if ($entry['category'] === $cat->value) {
                    $checks[] = [
                        'code' => $entry['code'],
                        'severity' => $entry['severity'],
                        'status' => $entry['status'],
                        'count' => $perCode[$entry['code']] ?? 0,
                    ];
                }
            }
            $catalog[$cat->value] = [
                'count' => $perCategory[$cat->value] ?? 0,
                'checks' => $checks,
            ];
        }

        $resources = null;
        $resourceRefs = null;
        $resourceSummary = [];
        if ($view === 'resources') {
            $resourceSummary = $model->resources()
                ->select('url', 'type', 'size_bytes')->distinct()->get()
                ->groupBy('type')
                ->map(fn ($g) => ['count' => $g->count(), 'total_bytes' => (int) $g->sum('size_bytes')])
                ->all();

            if (is_string($resource) && $resource !== '') {
                $pageIds = $model->resources()->where('url', $resource)->distinct()->pluck('from_page_id');
                $resourceRefs = [
                    'url' => $resource,
                    'pages' => $model->pages()->whereIn('id', $pageIds)->orderBy('url')
                        ->paginate(50)->withQueryString()
                        ->through(fn ($p) => ['id' => $p->id, 'url' => $p->url, 'status_code' => $p->status_code]),
                ];
            } else {
                $rq = $model->resources()
                    ->selectRaw('url, min(type) as type, max(is_internal) as is_internal, count(distinct from_page_id) as ref_count, min(status_code) as status_code, min(size_bytes) as size_bytes')
                    ->groupBy('url');
                if (is_string($resourceType) && $resourceType !== '') {
                    $rq->where('type', $resourceType);
                }
                $paginator = $rq->orderByDesc('ref_count')->paginate(50)->withQueryString();
                $paginator->getCollection()->transform(fn ($row) => [
                    'url' => $row->url,
                    'type' => $row->type,
                    'is_internal' => (bool) $row->is_internal,
                    'ref_count' => (int) $row->ref_count,
                    'status_code' => $row->status_code !== null ? (int) $row->status_code : null,
                    'size_bytes' => $row->size_bytes !== null ? (int) $row->size_bytes : null,
                ]);
                $resources = $paginator;
            }
        }

        return inertia('Crawls/Show', [
            'crawl' => [
                'id' => $model->id,
                'start_url' => $model->start_url,
                'mode' => $model->mode->value,
                'status' => $model->status->value,
                'pages_crawled' => $model->pages_crawled,
                'summary' => $model->summary ?? [],
                'error' => $model->error,
                'started_at' => $model->started_at?->toISOString(),
                'finished_at' => $model->finished_at?->toISOString(),
            ],
            'pages' => $pages,
            'categories' => $categories,
            'catalog' => $catalog,
            'resources' => $resources,
            'resourceRefs' => $resourceRefs,
            'resourceSummary' => $resourceSummary,
            'filters' => [
                'category' => is_string($category) && $category !== '' ? $category : null,
                'group' => is_string($group) && $group !== '' ? $group : null,
                'issue' => is_string($issue) && $issue !== '' ? $issue : null,
                'view' => $view === 'resources' ? 'resources' : null,
                'resource_type' => is_string($resourceType) && $resourceType !== '' ? $resourceType : null,
                'resource' => is_string($resource) && $resource !== '' ? $resource : null,
            ],
        ]);
    }

    public function pageDetail(Request $request, string $crawl, string $page)
    {
        $project = $request->get('project');
        $model = $project->crawls()->findOrFail($crawl);
        $pageModel = $model->pages()->with('outLinks')->findOrFail($page);

        // "Found on": the pages that link to this URL (its origin). Match the link
        // target against the page URL allowing for trailing-slash normalisation.
        $normalized = rtrim($pageModel->url, '/');
        $inboundLinks = $model->links()
            ->whereIn('to_url', array_values(array_unique([$pageModel->url, $normalized, $normalized.'/'])))
            ->get(['from_page_id', 'anchor']);

        $sourceUrls = $model->pages()
            ->whereIn('id', $inboundLinks->pluck('from_page_id')->unique())
            ->pluck('url', 'id');

        $inLinks = $inboundLinks
            ->map(fn ($l) => [
                'from_page_id' => $l->from_page_id,
                'from_url' => $sourceUrls[$l->from_page_id] ?? null,
                'anchor' => $l->anchor,
            ])
            ->filter(fn ($l) => $l['from_url'] !== null)
            ->unique(fn ($l) => $l['from_page_id'].'|'.$l['anchor'])
            ->values();

        return inertia('Crawls/Page', [
            'crawlId' => $model->id,
            'page' => [
                'url' => $pageModel->url,
                'final_url' => $pageModel->final_url,
                'status_code' => $pageModel->status_code,
                'content_category' => $pageModel->content_category,
                'content_type' => $pageModel->content_type,
                'size_bytes' => $pageModel->size_bytes,
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
                'issue_severities' => collect($pageModel->issues ?? [])
                    ->mapWithKeys(fn ($code) => [$code => IssueCode::tryFrom($code)?->severity() ?? 'notice'])
                    ->all(),
                'out_links' => $pageModel->outLinks->map(fn ($l) => [
                    'to_url' => $l->to_url, 'type' => $l->type, 'anchor' => $l->anchor, 'status_code' => $l->status_code,
                ]),
                'in_links' => $inLinks,
                'screenshots_enabled' => $model->capture_screenshots,
                'screenshots' => [
                    'desktop' => $pageModel->screenshot_desktop_path
                        ? route('app.project.crawls.pages.screenshot', ['project' => $project->id, 'crawl' => $model->id, 'page' => $pageModel->id, 'variant' => 'desktop'])
                        : null,
                    'mobile' => $pageModel->screenshot_mobile_path
                        ? route('app.project.crawls.pages.screenshot', ['project' => $project->id, 'crawl' => $model->id, 'page' => $pageModel->id, 'variant' => 'mobile'])
                        : null,
                ],
            ],
        ]);
    }

    public function screenshot(Request $request, string $crawl, string $page, string $variant)
    {
        abort_unless(in_array($variant, ['desktop', 'mobile'], true), 404);

        $project = $request->get('project');
        $model = $project->crawls()->findOrFail($crawl);
        $pageModel = $model->pages()->findOrFail($page);

        $path = $variant === 'mobile' ? $pageModel->screenshot_mobile_path : $pageModel->screenshot_desktop_path;

        $disk = Storage::disk(config('filesystems.default'));
        abort_if($path === null || ! $disk->exists($path), 404);

        return $disk->response($path);
    }

    public function destroy(Request $request, string $crawl)
    {
        $project = $request->get('project');
        $model = $project->crawls()->findOrFail($crawl);

        Storage::disk(config('filesystems.default'))->deleteDirectory("crawl-screenshots/{$model->id}");
        $model->delete();

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
            fputcsv($out, ['url', 'status_code', 'type', 'size_bytes', 'title', 'is_indexable', 'inlinks_count', 'depth', 'issues']);
            $model->pages()->orderBy('depth')->chunk(200, function ($pages) use ($out) {
                foreach ($pages as $p) {
                    fputcsv($out, [
                        $this->sanitizeCsvCell($p->url),
                        $p->status_code,
                        $this->sanitizeCsvCell($p->content_category),
                        $p->size_bytes,
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

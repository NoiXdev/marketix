<?php

namespace App\Jobs;

use App\Crawler\IssueCode;
use App\Crawler\LinkStatusChecker;
use App\Crawler\SitemapReader;
use App\Enums\CrawlStatus;
use App\Models\Crawl;
use App\Models\CrawlPage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AggregateCrawlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Safety cap on how many external link targets we probe per crawl. */
    private const MAX_LINK_PROBES = 2000;

    public function __construct(public Crawl $crawl) {}

    public function handle(SitemapReader $sitemap): void
    {
        $norm = fn (?string $u) => $u === null ? '' : rtrim($u, '/');
        $start = $norm($this->crawl->start_url);

        // Lightweight identity scan: only the columns needed to resolve the start page,
        // build id-based BFS adjacency, and detect duplicate titles/descriptions. This
        // avoids hydrating full page models (with their heavier JSON columns) or
        // eager-loading every link relation for the whole crawl at once.
        $identities = $this->crawl->pages()
            ->select(['id', 'url', 'final_url', 'title', 'meta_description', 'created_at'])
            ->get();

        // The page that represents the crawl's start/home page. Prefer an exact match
        // on url or final_url (redirects like apex->www or http->https mean the page
        // actually fetched may only match via final_url); fall back to the earliest
        // created page if neither matches (e.g. the recorded url itself is already the
        // post-redirect form).
        $startPage = $identities->first(
            fn (CrawlPage $p) => $norm($p->url) === $start || $norm($p->final_url) === $start
        ) ?? $identities->sortBy('created_at')->first();
        $startPageId = $startPage?->id;

        // Map every url a page is known by (as-requested and, if redirected, its final
        // destination) to that page's id, so links using either form resolve to the
        // same BFS node.
        $pageIdByUrl = [];
        foreach ($identities as $p) {
            $pageIdByUrl[$norm($p->url)] = $p->id;
            if ($norm($p->final_url) !== '') {
                $pageIdByUrl[$norm($p->final_url)] = $p->id;
            }
        }

        // Single lightweight pass over internal links: feeds both the inlink counts
        // and the BFS adjacency, instead of eager-loading outLinks per page AND a
        // second full links() collection.
        $inlinks = [];
        $adjById = [];
        foreach ($identities as $p) {
            $adjById[$p->id] = [];
        }
        foreach ($this->crawl->links()->where('type', 'internal')->select(['from_page_id', 'to_url'])->cursor() as $link) {
            $toKey = $norm($link->to_url);
            $inlinks[$toKey] = ($inlinks[$toKey] ?? 0) + 1;

            $toId = $pageIdByUrl[$toKey] ?? null;
            if ($toId !== null && isset($adjById[$link->from_page_id])) {
                $adjById[$link->from_page_id][] = $toId;
            }
        }

        $depthsById = $this->computeDepths($adjById, $startPageId);

        // sitemap set
        $sitemapUrls = array_map($norm, $sitemap->urlsFor($this->crawl->start_url));
        $hasSitemap = $sitemapUrls !== [];
        $sitemapSet = array_flip($sitemapUrls);

        // duplicate title / description
        $dupTitles = $this->duplicates($identities, 'title');
        $dupDescriptions = $this->duplicates($identities, 'meta_description');

        // Broken links: probe link targets and collect the pages that link to a 4xx/5xx.
        $brokenPageIds = $this->checkBrokenLinks($norm);

        $summary = [];

        $this->crawl->pages()->chunkById(500, function (Collection $pages) use (
            $norm, $inlinks, $depthsById, $sitemapSet, $hasSitemap, $dupTitles, $dupDescriptions, $startPageId, $brokenPageIds, &$summary
        ) {
            foreach ($pages as $page) {
                $key = $norm($page->url);
                $issues = collect($page->issues ?? [])->flip();

                $count = $inlinks[$key] ?? 0;
                $page->inlinks_count = $count;

                $isStart = $startPageId !== null && $page->id === $startPageId;

                $orphan = $page->is_indexable && $count === 0 && ! $isStart;
                $page->is_orphan = $orphan;
                if ($orphan) {
                    $issues[IssueCode::OrphanPage->value] = true;
                }

                $page->depth = $depthsById[$page->id] ?? null;

                $inSitemap = isset($sitemapSet[$key]);
                $page->in_sitemap = $inSitemap;
                if ($hasSitemap && ! $inSitemap && $page->is_indexable && ! $isStart) {
                    $issues[IssueCode::NotInSitemap->value] = true;
                }

                if ($page->title !== null && ($dupTitles[$page->title] ?? 0) > 1) {
                    $issues[IssueCode::DuplicateTitle->value] = true;
                }
                if ($page->meta_description !== null && ($dupDescriptions[$page->meta_description] ?? 0) > 1) {
                    $issues[IssueCode::DuplicateMetaDescription->value] = true;
                }

                if (isset($brokenPageIds[$page->id])) {
                    $issues[IssueCode::BrokenLink->value] = true;
                }

                $page->issues = array_keys($issues->all());
                $page->save();

                foreach ($page->issues as $code) {
                    $summary[$code] = ($summary[$code] ?? 0) + 1;
                }
            }
        });

        $this->crawl->update([
            'summary' => $summary,
            'status' => CrawlStatus::Completed,
            'finished_at' => now(),
        ]);
    }

    /**
     * BFS over the page-id adjacency graph, starting at $startId.
     *
     * @param  array<string, array<int, string>>  $adj
     * @return array<string, int>
     */
    private function computeDepths(array $adj, ?string $startId): array
    {
        if ($startId === null) {
            return [];
        }

        $depths = [$startId => 0];
        $queue = [$startId];
        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($adj[$current] ?? [] as $next) {
                if (! isset($depths[$next])) {
                    $depths[$next] = $depths[$current] + 1;
                    $queue[] = $next;
                }
            }
        }

        return $depths;
    }

    /**
     * @param  Collection<int, CrawlPage>  $pages
     * @return array<string, int>
     */
    private function duplicates($pages, string $field): array
    {
        $counts = [];
        foreach ($pages as $page) {
            $value = $page->{$field};
            if ($value !== null && trim((string) $value) !== '') {
                $counts[$value] = ($counts[$value] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * Probe the crawl's link targets and persist each link's HTTP status. Internal
     * targets reuse the status of the page that was already crawled (no re-fetch);
     * external targets are probed (up to MAX_LINK_PROBES). Returns the set of
     * from_page_ids that link to at least one 4xx/5xx target, keyed for O(1) lookup.
     *
     * @param  callable(?string): string  $norm
     * @return array<string, true>
     */
    protected function checkBrokenLinks(callable $norm): array
    {
        // Known statuses from already-crawled pages (internal links resolve here for free).
        $statusByUrl = [];
        foreach ($this->crawl->pages()->select(['url', 'final_url', 'status_code'])->cursor() as $p) {
            if ($p->status_code === null) {
                continue;
            }
            $statusByUrl[$norm($p->url)] = $p->status_code;
            if ($norm($p->final_url) !== '') {
                $statusByUrl[$norm($p->final_url)] = $p->status_code;
            }
        }

        $checker = app(LinkStatusChecker::class);
        $cache = [];   // normalized url => status|null
        $probes = 0;

        foreach ($this->crawl->links()->select('to_url')->distinct()->pluck('to_url') as $to) {
            $key = $norm($to);

            if (! array_key_exists($key, $cache)) {
                if (isset($statusByUrl[$key])) {
                    $cache[$key] = $statusByUrl[$key];
                } elseif ($probes < self::MAX_LINK_PROBES) {
                    $cache[$key] = $checker->status($to);
                    $probes++;
                } else {
                    $cache[$key] = null;
                }
            }

            if ($cache[$key] !== null) {
                $this->crawl->links()->where('to_url', $to)->update(['status_code' => $cache[$key]]);
            }
        }

        if ($probes >= self::MAX_LINK_PROBES) {
            Log::warning('Crawl broken-link check hit the probe cap; some links were not checked.', [
                'crawl_id' => $this->crawl->id,
                'cap' => self::MAX_LINK_PROBES,
            ]);
        }

        $broken = [];
        foreach ($this->crawl->links()->where('status_code', '>=', 400)->distinct()->pluck('from_page_id') as $id) {
            $broken[$id] = true;
        }

        return $broken;
    }

    public function failed(\Throwable $e): void
    {
        $this->crawl->update([
            'status' => CrawlStatus::Failed,
            'error' => $e->getMessage(),
            'finished_at' => now(),
        ]);
    }
}

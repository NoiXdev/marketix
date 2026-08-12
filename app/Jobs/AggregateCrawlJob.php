<?php

namespace App\Jobs;

use App\Crawler\IssueCode;
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

class AggregateCrawlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

        $summary = [];

        $this->crawl->pages()->chunkById(500, function (Collection $pages) use (
            $norm, $inlinks, $depthsById, $sitemapSet, $hasSitemap, $dupTitles, $dupDescriptions, $startPageId, &$summary
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

    public function failed(\Throwable $e): void
    {
        $this->crawl->update([
            'status' => CrawlStatus::Failed,
            'error' => $e->getMessage(),
            'finished_at' => now(),
        ]);
    }
}

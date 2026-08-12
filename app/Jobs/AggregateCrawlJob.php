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
        $pages = $this->crawl->pages()->with('outLinks')->get();
        $norm = fn (?string $u) => $u === null ? '' : rtrim($u, '/');
        $start = $norm($this->crawl->start_url);

        // 1. inlink counts (internal links pointing at each page url)
        $inlinks = [];
        foreach ($this->crawl->links()->where('type', 'internal')->get() as $link) {
            $key = $norm($link->to_url);
            $inlinks[$key] = ($inlinks[$key] ?? 0) + 1;
        }

        // 3. depth via BFS over internal links from start
        $depths = $this->computeDepths($pages, $norm, $start);

        // 4. sitemap set
        $sitemapUrls = array_map($norm, $sitemap->urlsFor($this->crawl->start_url));
        $hasSitemap = $sitemapUrls !== [];
        $sitemapSet = array_flip($sitemapUrls);

        // 5. duplicate title / description
        $dupTitles = $this->duplicates($pages, 'title');
        $dupDescriptions = $this->duplicates($pages, 'meta_description');

        $summary = [];
        foreach ($pages as $page) {
            $key = $norm($page->url);
            $issues = collect($page->issues ?? [])->flip();

            $count = $inlinks[$key] ?? 0;
            $page->inlinks_count = $count;

            $orphan = $page->is_indexable && $count === 0 && $key !== $start;
            $page->is_orphan = $orphan;
            if ($orphan) {
                $issues[IssueCode::OrphanPage->value] = true;
            }

            $page->depth = $depths[$key] ?? null;

            $inSitemap = isset($sitemapSet[$key]);
            $page->in_sitemap = $inSitemap;
            if ($hasSitemap && ! $inSitemap && $page->is_indexable && $key !== $start) {
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

        $this->crawl->update([
            'summary' => $summary,
            'status' => CrawlStatus::Completed,
            'finished_at' => now(),
        ]);
    }

    /**
     * @param  Collection<int, CrawlPage>  $pages
     * @return array<string, int>
     */
    private function computeDepths($pages, callable $norm, string $start): array
    {
        $adj = [];
        foreach ($pages as $page) {
            $from = $norm($page->url);
            $adj[$from] = [];
            foreach ($page->outLinks as $link) {
                if ($link->type === 'internal') {
                    $adj[$from][] = $norm($link->to_url);
                }
            }
        }

        $depths = [$start => 0];
        $queue = [$start];
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
}

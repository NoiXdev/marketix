<?php

namespace App\Jobs;

use App\Crawler\CheckCatalog;
use App\Crawler\HtmlText;
use App\Crawler\IssueCode;
use App\Crawler\LinkChecks;
use App\Crawler\LinkStatusChecker;
use App\Crawler\LlmsTxtReader;
use App\Crawler\ResourceProbe;
use App\Crawler\RobotsTxtReader;
use App\Crawler\SafeBrowsershotRenderer;
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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;

class AggregateCrawlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * No timeout: aggregating a huge crawl (incl. probing many external link targets)
     * can legitimately take a long time. A value of 0 disables the worker's per-job
     * alarm regardless of the worker's --timeout, so the job is never killed mid-run.
     */
    public int $timeout = 0;

    /** Don't automatically re-run an expensive aggregation if it fails. */
    public int $tries = 1;

    /** Safety cap on how many external link targets we probe per crawl. */
    private const MAX_LINK_PROBES = 2000;

    /** Safety cap on how many external resources we probe per crawl. */
    private const MAX_RESOURCE_PROBES = 2000;

    /** Safety cap on how many off-host hreflang targets we probe per crawl. */
    private const MAX_HREFLANG_PROBES = 2000;

    public function __construct(public Crawl $crawl) {}

    public function handle(SitemapReader $sitemap, RobotsTxtReader $robots, LlmsTxtReader $llms): void
    {
        $norm = fn (?string $u) => $u === null ? '' : rtrim($u, '/');
        $start = $norm($this->crawl->start_url);

        // Lightweight identity scan: only the columns needed to resolve the start page,
        // build id-based BFS adjacency, and detect duplicate titles/descriptions. This
        // avoids hydrating full page models (with their heavier JSON columns) or
        // eager-loading every link relation for the whole crawl at once.
        $identities = $this->crawl->pages()
            ->select(['id', 'url', 'final_url', 'title', 'meta_description', 'meta_keywords', 'h1', 'content_hash', 'created_at', 'canonical', 'pagination_next', 'pagination_prev', 'status_code', 'is_indexable', 'hreflang'])
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

        $pageByUrl = [];
        $pageIndexableById = [];
        foreach ($identities as $p) {
            $canonicalNorm = null;
            if ($p->canonical !== null && trim($p->canonical) !== '') {
                $resolvedCanonical = $this->resolveUrl($p->canonical, $p->url);
                if ($resolvedCanonical !== null) {
                    $canonicalNorm = $norm($resolvedCanonical);
                }
            }

            // Hreflang annotations are stored as absolute {lang, href} pairs (T3); just
            // normalize the href here so cross-page lookups key consistently.
            $selfUrlNorm = $norm($p->url);
            $hreflangAnnotations = [];
            $selfLang = null;
            foreach ((is_array($p->hreflang) ? $p->hreflang : []) as $a) {
                $lang = is_array($a) ? ($a['lang'] ?? null) : null;
                $href = is_array($a) ? ($a['href'] ?? null) : null;
                if ($lang === null || $href === null || trim($href) === '') {
                    continue;
                }
                $hrefNorm = $norm($href);
                $hreflangAnnotations[] = ['lang' => $lang, 'hrefNorm' => $hrefNorm, 'href' => $href];
                // First non-"x-default" self-matching entry wins: "x-default" is not a
                // real language, so a self-referencing x-default entry (common, and
                // often listed before the real language entry) must never become
                // selfLang — otherwise it gets compared against a real language code
                // in the reciprocal-language check below and false-flags. If the only
                // self entry is x-default, selfLang stays null (that check requires
                // both langs known, so it correctly won't fire).
                if ($hrefNorm === $selfUrlNorm && $selfLang === null && $lang !== 'x-default') {
                    $selfLang = $lang;
                }
            }

            $entry = [
                'status' => $p->status_code,
                'indexable' => (bool) $p->is_indexable,
                'next' => $p->pagination_next !== null ? $norm($p->pagination_next) : null,
                'prev' => $p->pagination_prev !== null ? $norm($p->pagination_prev) : null,
                'canonical' => $canonicalNorm,
                'hreflang' => $hreflangAnnotations,
                'selfLang' => $selfLang,
            ];
            $pageByUrl[$norm($p->url)] = $entry;
            if ($norm($p->final_url) !== '') {
                $pageByUrl[$norm($p->final_url)] = $entry;
            }
            $pageIndexableById[$p->id] = (bool) $p->is_indexable;
        }

        // Same-host hreflang targets referenced by any page's annotations (for
        // hreflang_unlinked), and the deduped set of off-host targets to probe.
        $hreflangReferenced = [];
        $offHostHrefByNorm = [];
        foreach ($identities as $p) {
            $entry = $pageByUrl[$norm($p->url)] ?? null;
            if ($entry === null) {
                continue;
            }
            foreach ($entry['hreflang'] as $a) {
                if ($a['hrefNorm'] === '') {
                    continue;
                }
                if (isset($pageByUrl[$a['hrefNorm']])) {
                    $hreflangReferenced[$a['hrefNorm']] = true;
                } else {
                    $offHostHrefByNorm[$a['hrefNorm']] ??= $a['href'];
                }
            }
        }
        $externalStatus = $this->probeHreflangTargets($offHostHrefByNorm);

        // Single lightweight pass over all links: feeds the internal-only inlink counts
        // and BFS adjacency (unchanged), plus per-page outlink stats ($outStats) and
        // per-target inlink detail ($inlinkDetail) used by the link-quality checks below.
        $inlinks = [];
        $adjById = [];
        $outStats = [];
        $inlinkDetail = [];
        $emptyStats = ['internal' => 0, 'external' => 0, 'nofollow_internal' => false, 'no_anchor_internal' => false, 'non_descriptive_internal' => false, 'localhost' => false, 'non_crawlable_internal' => false];
        foreach ($identities as $p) {
            $adjById[$p->id] = [];
        }
        foreach ($this->crawl->links()->select(['from_page_id', 'to_url', 'type', 'rel', 'anchor'])->cursor() as $link) {
            $fid = $link->from_page_id;
            $outStats[$fid] ??= $emptyStats;
            if (LinkChecks::isLocalhost($link->to_url)) {
                $outStats[$fid]['localhost'] = true;
            }

            if ($link->type !== 'internal') {
                $outStats[$fid]['external']++;

                continue;
            }

            $toKey = $norm($link->to_url);
            $inlinks[$toKey] = ($inlinks[$toKey] ?? 0) + 1;
            $toId = $pageIdByUrl[$toKey] ?? null;
            if ($toId !== null && isset($adjById[$fid])) {
                $adjById[$fid][] = $toId;
            }

            $nofollow = str_contains(strtolower($link->rel ?? ''), 'nofollow');
            $anchor = trim($link->anchor ?? '');
            $outStats[$fid]['internal']++;
            if ($nofollow) {
                $outStats[$fid]['nofollow_internal'] = true;
            }
            if ($anchor === '') {
                $outStats[$fid]['no_anchor_internal'] = true;
            } elseif (LinkChecks::isNonDescriptive($anchor)) {
                $outStats[$fid]['non_descriptive_internal'] = true;
            }
            if (isset($pageByUrl[$toKey]) && $pageByUrl[$toKey]['indexable'] === false) {
                $outStats[$fid]['non_crawlable_internal'] = true;
            }

            $inlinkDetail[$toKey] ??= ['count' => 0, 'follow' => false, 'nofollow' => false, 'anyIndexableSource' => false];
            $inlinkDetail[$toKey]['count']++;
            if ($nofollow) {
                $inlinkDetail[$toKey]['nofollow'] = true;
            } else {
                $inlinkDetail[$toKey]['follow'] = true;
            }
            if (($pageIndexableById[$fid] ?? true) === true) {
                $inlinkDetail[$toKey]['anyIndexableSource'] = true;
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
        $dupKeywords = $this->duplicates($identities, 'meta_keywords');
        $dupH1 = $this->duplicates($identities, 'h1');
        $dupHashes = $this->duplicates($identities, 'content_hash');

        // Broken links: probe link targets and collect the pages that link to a 4xx/5xx.
        $brokenPageIds = $this->checkBrokenLinks($norm);
        $this->checkResources($norm);

        // Site-level GEO signals: robots.txt AI-bot blocking, llms.txt presence, and a
        // raw-vs-rendered content diff — all computed once against the crawl's start
        // URL rather than per-page, and applied only to the start page below.
        $geoBlockedBots = $startPageId !== null ? $robots->blockedAiBots($this->crawl->start_url) : [];
        $geoLlmsMissing = $startPageId !== null && ! $llms->exists($this->crawl->start_url);
        $geoStartJs = $startPageId !== null ? $this->startPageJsDependent($this->crawl->start_url) : null;

        $summary = [];

        $this->crawl->pages()->chunkById(500, function (Collection $pages) use (
            $norm, $inlinks, $depthsById, $sitemapSet, $hasSitemap, $dupTitles, $dupDescriptions, $dupKeywords, $dupH1, $dupHashes, $startPageId, $brokenPageIds, $pageByUrl, $outStats, $inlinkDetail, $emptyStats, $hreflangReferenced, $externalStatus, $geoBlockedBots, $geoLlmsMissing, $geoStartJs, &$summary
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
                if ($page->meta_keywords !== null && ($dupKeywords[$page->meta_keywords] ?? 0) > 1) {
                    $issues[IssueCode::DuplicateMetaKeywords->value] = true;
                }
                if ($page->h1 !== null && ($dupH1[$page->h1] ?? 0) > 1) {
                    $issues[IssueCode::DuplicateH1->value] = true;
                }
                if ($page->content_hash !== null && ($dupHashes[$page->content_hash] ?? 0) > 1) {
                    $issues[IssueCode::ExactDuplicates->value] = true;
                }

                if (isset($brokenPageIds[$page->id])) {
                    $issues[IssueCode::BrokenLink->value] = true;
                }

                // Cross-page canonical checks.
                if ($page->canonical !== null && trim($page->canonical) !== '') {
                    $canon = $this->resolveUrl($page->canonical, $page->url);
                    if ($canon !== null) {
                        $canonKey = $norm($canon);
                        if (isset($pageByUrl[$canonKey])) {
                            if ($pageByUrl[$canonKey]['indexable'] === false) {
                                $issues[IssueCode::NonIndexableCanonical->value] = true;
                            }
                        } else {
                            $issues[IssueCode::CanonicalNotLinked->value] = true;
                        }
                    }
                }

                // Cross-page pagination checks (next/prev are stored pre-resolved).
                foreach (array_filter([$page->pagination_next, $page->pagination_prev]) as $target) {
                    $paginationKey = $norm($target);
                    if (isset($pageByUrl[$paginationKey])) {
                        if ($pageByUrl[$paginationKey]['status'] !== null && $pageByUrl[$paginationKey]['status'] !== 200) {
                            $issues[IssueCode::PaginationNon200->value] = true;
                        }
                        if ($pageByUrl[$paginationKey]['indexable'] === false) {
                            $issues[IssueCode::PaginationNonIndexable->value] = true;
                        }
                    } else {
                        $issues[IssueCode::PaginationUnlinked->value] = true;
                    }
                }
                if ($page->pagination_next !== null) {
                    $nextKey = $norm($page->pagination_next);
                    if (isset($pageByUrl[$nextKey]) && $pageByUrl[$nextKey]['prev'] !== $norm($page->url)) {
                        $issues[IssueCode::PaginationSequenceError->value] = true;
                    }
                }

                // Cross-page hreflang checks.
                $hreflangAnnotations = is_array($page->hreflang) ? $page->hreflang : [];
                if ($hreflangAnnotations !== []) {
                    // Reuse the self-lang already computed into $pageByUrl (single source
                    // of truth) rather than re-deriving it from $page->hreflang here.
                    $selfLang = $pageByUrl[$key]['selfLang'] ?? null;

                    foreach ($hreflangAnnotations as $a) {
                        $lang = is_array($a) ? ($a['lang'] ?? null) : null;
                        $href = is_array($a) ? ($a['href'] ?? null) : null;
                        if ($lang === null || $href === null || trim($href) === '') {
                            continue;
                        }
                        $hrefNorm = $norm($href);
                        if ($hrefNorm === $key) {
                            continue; // skip the self entry for reciprocity checks
                        }

                        if (isset($pageByUrl[$hrefNorm])) {
                            $t = $pageByUrl[$hrefNorm];
                            if ($t['status'] !== null && $t['status'] !== 200) {
                                $issues[IssueCode::HreflangNon200->value] = true;
                            }
                            if ($t['indexable'] === false) {
                                $issues[IssueCode::HreflangNoindexReturnLink->value] = true;
                            }
                            if ($t['canonical'] !== null && $t['canonical'] !== $hrefNorm) {
                                $issues[IssueCode::HreflangNonCanonicalReturnLink->value] = true;
                            }

                            $returnLang = null;
                            $hasReturn = false;
                            foreach ($t['hreflang'] as $ta) {
                                if ($ta['hrefNorm'] === $key) {
                                    $hasReturn = true;
                                    $returnLang = $ta['lang'];
                                    break;
                                }
                            }
                            if (! $hasReturn) {
                                $issues[IssueCode::HreflangMissingReturnLink->value] = true;
                            } elseif ($selfLang !== null && $returnLang !== null && $returnLang !== $selfLang) {
                                $issues[IssueCode::HreflangInconsistentLanguage->value] = true;
                            }
                        } elseif (isset($externalStatus[$hrefNorm]) && $externalStatus[$hrefNorm] !== null && $externalStatus[$hrefNorm] !== 200) {
                            $issues[IssueCode::HreflangNon200->value] = true;
                        }
                    }
                }

                // "Unlinked Hreflang URLs": a same-host target referenced by someone
                // else's hreflang annotations with zero <a> inlinks, evaluated for every
                // page regardless of whether it declares hreflang of its own. Exempt the
                // crawl seed, same as orphan_page/not_in_sitemap above.
                if (isset($hreflangReferenced[$key]) && $count === 0 && ! $isStart) {
                    $issues[IssueCode::HreflangUnlinked->value] = true;
                }

                if ($page->content_category === 'html') {
                    foreach (LinkChecks::outlinkIssues($outStats[$page->id] ?? $emptyStats, $depthsById[$page->id] ?? null) as $code) {
                        $issues[$code] = true;
                    }
                    foreach (LinkChecks::inlinkIssues($inlinkDetail[$key] ?? ['count' => 0, 'follow' => false, 'nofollow' => false, 'anyIndexableSource' => false]) as $code) {
                        $issues[$code] = true;
                    }
                }

                if ($isStart) {
                    if ($geoBlockedBots !== []) {
                        $issues->put(IssueCode::AiCrawlerBlocked->value, true);
                    }
                    if ($geoLlmsMissing) {
                        $issues->put(IssueCode::MissingLlmsTxt->value, true);
                    }
                    if ($geoStartJs === true) {
                        $issues->put(IssueCode::JsDependentContent->value, true);
                    } elseif ($geoStartJs === false) {
                        $issues->forget(IssueCode::JsDependentContent->value);
                    }
                }

                $page->issues = array_keys($issues->all());
                $page->save();

                foreach ($page->issues as $code) {
                    if (! CheckCatalog::isProblemCode($code)) {
                        continue;
                    }
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

    /**
     * Fill status_code + size_bytes on every crawl_resources row. Internal resources
     * take the status/size of the crawled page they resolve to (normalised match);
     * external resources are probed (SSRF-safe, deduped, capped). Internal resources
     * that were not crawled, and probes over the cap, are left null.
     *
     * @param  callable(?string): string  $norm
     */
    protected function checkResources(callable $norm): void
    {
        $meta = [];
        foreach ($this->crawl->pages()->select(['url', 'final_url', 'status_code', 'size_bytes'])->cursor() as $p) {
            $m = ['status' => $p->status_code, 'size' => $p->size_bytes];
            $meta[$norm($p->url)] = $m;
            if ($norm($p->final_url) !== '') {
                $meta[$norm($p->final_url)] = $m;
            }
        }

        $probe = app(ResourceProbe::class);
        $probes = 0;

        foreach ($this->crawl->resources()->select(['url', 'is_internal'])->distinct()->get() as $res) {
            $key = $norm($res->url);

            if ($res->is_internal && isset($meta[$key])) {
                $status = $meta[$key]['status'];
                $size = $meta[$key]['size'];
            } elseif (! $res->is_internal && $probes < self::MAX_RESOURCE_PROBES) {
                ['status' => $status, 'size' => $size] = $probe->probe($res->url);
                $probes++;
            } else {
                continue; // internal-not-crawled, or over cap → leave null
            }

            if ($status !== null || $size !== null) {
                $this->crawl->resources()->where('url', $res->url)->update([
                    'status_code' => $status,
                    'size_bytes' => $size,
                ]);
            }
        }

        if ($probes >= self::MAX_RESOURCE_PROBES) {
            Log::warning('Crawl resource check hit the probe cap; some external resources were not probed.', [
                'crawl_id' => $this->crawl->id,
                'cap' => self::MAX_RESOURCE_PROBES,
            ]);
        }
    }

    /**
     * Probe every off-host hreflang target (SSRF-safe via LinkStatusChecker, capped
     * exactly like checkBrokenLinks/checkResources) and return its status keyed by the
     * same normalized URL used elsewhere in this job.
     *
     * @param  array<string, string>  $offHostHrefByNorm  normUrl => url to probe
     * @return array<string, int|null>
     */
    protected function probeHreflangTargets(array $offHostHrefByNorm): array
    {
        if ($offHostHrefByNorm === []) {
            return [];
        }

        $checker = app(LinkStatusChecker::class);
        $externalStatus = [];
        $probes = 0;

        foreach ($offHostHrefByNorm as $normUrl => $href) {
            if ($probes < self::MAX_HREFLANG_PROBES) {
                $externalStatus[$normUrl] = $checker->status($href);
                $probes++;
            } else {
                $externalStatus[$normUrl] = null;
            }
        }

        if ($probes >= self::MAX_HREFLANG_PROBES) {
            Log::warning('Crawl hreflang check hit the probe cap; some off-host targets were not checked.', [
                'crawl_id' => $this->crawl->id,
                'cap' => self::MAX_HREFLANG_PROBES,
            ]);
        }

        return $externalStatus;
    }

    /**
     * Compares the start page's raw HTML against its JS-rendered HTML: if the raw
     * response's visible text is far smaller than the rendered version's, the page's
     * content is JS-dependent (a crawler that doesn't execute JS would see little).
     *
     * @return bool|null true = JS-dependent, false = readable raw, null = undetermined
     */
    private function startPageJsDependent(string $startUrl): ?bool
    {
        try {
            $raw = Http::timeout(15)->get($startUrl);
            if (! $raw->successful()) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        $rendered = $this->jsRenderer()->getRenderedHtml($startUrl);
        if ($rendered === '') {
            return null; // render failed → keep the per-page heuristic result
        }
        $renderedLen = HtmlText::visibleLength($rendered);
        if ($renderedLen === 0) {
            return null;
        }

        return HtmlText::visibleLength($raw->body()) < max(200, (int) ($renderedLen * 0.25));
    }

    /**
     * Headless-browser renderer for the start-page JS diff. Configuration mirrors
     * RunCrawlJob::jsRenderer() exactly (no-sandbox + disable-dev-shm-usage, required
     * for Chromium to run as root in a container). Kept as its own overridable method
     * (rather than a container binding) so tests can substitute a deterministic fake
     * without risking prod resolving an unconfigured Browsershot instance via
     * container auto-wiring.
     */
    protected function jsRenderer(): SafeBrowsershotRenderer
    {
        return new SafeBrowsershotRenderer((new Browsershot)->noSandbox()->addChromiumArguments(['disable-dev-shm-usage']));
    }

    private function resolveUrl(string $href, string $base): ?string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $b = parse_url($base);
        if (! isset($b['scheme'], $b['host'])) {
            return null;
        }
        $origin = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');
        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }
        $path = rtrim(dirname($b['path'] ?? '/'), '/');

        return $origin.$path.'/'.$href;
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

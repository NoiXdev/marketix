# Crawler Phase 7 — Links — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Activate all 12 planned `links` checks — 9 per-page outlink/depth + 3 cross-page inlink — computed in `AggregateCrawlJob` from the stored `CrawlLink` rows via a pure `LinkChecks` class.

**Architecture:** `LinkChecks` (pure: thresholds + classification + flag→code logic). The aggregate's existing single links pass is widened to all links and enriched to build per-page `outStats` + per-target `inlinkDetail`, then calls `LinkChecks` in the chunk loop for HTML pages. No new severity, no frontend/lang.

**Tech Stack:** Laravel 13 / PHP 8.3, PHPUnit, Pint.

**Spec:** `docs/superpowers/specs/2026-08-15-crawler-links-phase7-design.md`

## Global Constraints

- **Bijection stays exact:** active catalogue codes === `IssueCode` cases. This phase adds 12 `IssueCode` cases and flips 12 catalogue entries planned→active, so both grow **92 → 104**.
- **`IssueCode::category()` and `::severity()` are exhaustive `match`** (no default arm). All 12 new cases → `IssueCategory::Links`. Severities: `warning` → `pages_non_crawlable_internal_outlinks`, `pages_no_internal_outlinks`, `only_internal_nofollow_inlinks`, `outlinks_to_localhost`, `only_non_indexable_inlinks`; `notice` → `pages_high_crawl_depth`, `internal_nofollow_outlinks`, `internal_outlinks_no_anchor`, `non_descriptive_anchor_internal_outlinks`, `pages_many_external_outlinks`, `pages_many_internal_outlinks`, `follow_nofollow_internal_inlinks`. Match the catalogue; the generalized severity-match test enforces it.
- **The 12 lang labels already exist** (Phase 0) — no rename, no lang change.
- **No new crawl-time network requests.** Reuses stored `CrawlLink` rows + page data.
- **Commands via DDEV.** Backend gate `ddev php artisan test`; frontend gate `ddev npm run build` (no change expected); Pint clean `ddev exec vendor/bin/pint --test`.

---

## File Structure

**Create**
- `app/Crawler/LinkChecks.php`
- `tests/Unit/Crawler/LinkChecksTest.php`

**Modify**
- `app/Crawler/IssueCode.php`, `app/Crawler/CheckCatalog.php`, `app/Jobs/AggregateCrawlJob.php`, `tests/Unit/Crawler/CheckCatalogTest.php`, `tests/Feature/Crawler/AggregateCrawlJobTest.php`, `tests/Feature/Crawler/CrawlControllerTest.php`.

---

## Task 1: IssueCode cases + catalogue flip (links)

**Files:** Modify `app/Crawler/IssueCode.php`, `app/Crawler/CheckCatalog.php`; Test `tests/Unit/Crawler/CheckCatalogTest.php`.

**Interfaces:** Produces 12 new `IssueCode` cases. Tasks 2 & 3 emit these.

- [ ] **Step 1: Add the 12 cases.** In `app/Crawler/IssueCode.php`, after the canonicals/pagination block, add:

```php
    // links
    case PagesNonCrawlableInternalOutlinks = 'pages_non_crawlable_internal_outlinks';
    case PagesHighCrawlDepth = 'pages_high_crawl_depth';
    case PagesNoInternalOutlinks = 'pages_no_internal_outlinks';
    case InternalNofollowOutlinks = 'internal_nofollow_outlinks';
    case InternalOutlinksNoAnchor = 'internal_outlinks_no_anchor';
    case NonDescriptiveAnchorInternalOutlinks = 'non_descriptive_anchor_internal_outlinks';
    case PagesManyExternalOutlinks = 'pages_many_external_outlinks';
    case PagesManyInternalOutlinks = 'pages_many_internal_outlinks';
    case FollowNofollowInternalInlinks = 'follow_nofollow_internal_inlinks';
    case OnlyInternalNofollowInlinks = 'only_internal_nofollow_inlinks';
    case OutlinksToLocalhost = 'outlinks_to_localhost';
    case OnlyNonIndexableInlinks = 'only_non_indexable_inlinks';
```

- [ ] **Step 2: `severity()` arms.** Add to `'warning'`: `self::PagesNonCrawlableInternalOutlinks, self::PagesNoInternalOutlinks, self::OnlyInternalNofollowInlinks, self::OutlinksToLocalhost, self::OnlyNonIndexableInlinks`. Add to `'notice'`: `self::PagesHighCrawlDepth, self::InternalNofollowOutlinks, self::InternalOutlinksNoAnchor, self::NonDescriptiveAnchorInternalOutlinks, self::PagesManyExternalOutlinks, self::PagesManyInternalOutlinks, self::FollowNofollowInternalInlinks`. (Extend existing arms; no default.)

- [ ] **Step 3: `category()` arm.** Extend the existing `IssueCategory::Links` arm (currently `self::OrphanPage, self::BrokenLink`) with all 12 new cases.

- [ ] **Step 4: Flip the 12 catalogue entries.** In `app/Crawler/CheckCatalog.php`, change `'status' => self::P` → `'status' => self::A` for all 12 planned `links` codes (lines with `'category' => 'links'` and `self::P`). `orphan_page` and `broken_link` are already active — leave them.

- [ ] **Step 5: Update `CheckCatalogTest`.** Extend `test_active_codes_for_category_filters`:

```php
        $this->assertCount(14, CheckCatalog::activeCodesForCategory('links'));
```

(The dynamic bijection test passes at 104; the generalized severity-match covers the new codes.)

- [ ] **Step 6: Run + Pint.**

Run: `ddev php artisan test --filter='CheckCatalogTest|IssueCodeTest'` → PASS.
Run: `ddev exec vendor/bin/pint --test app/Crawler/IssueCode.php app/Crawler/CheckCatalog.php` → PASS.

- [ ] **Step 7: Commit.** `git add app/Crawler/IssueCode.php app/Crawler/CheckCatalog.php tests/Unit/Crawler/CheckCatalogTest.php && git commit -m "feat(crawler): activate links catalogue checks + issue codes"`

---

## Task 2: `LinkChecks` (pure thresholds + flag→code logic)

**Files:** Create `app/Crawler/LinkChecks.php`, `tests/Unit/Crawler/LinkChecksTest.php`.

**Interfaces:** Consumes the 12 `IssueCode` cases (Task 1). Produces `LinkChecks::outlinkIssues(array $stats, ?int $depth): string[]`, `LinkChecks::inlinkIssues(array $detail): string[]`, `isNonDescriptive(string): bool`, `isLocalhost(string): bool`, and the `HIGH_CRAWL_DEPTH`/`MANY_OUTLINKS`/`NON_DESCRIPTIVE_ANCHORS` constants. Task 3's aggregate calls these.

- [ ] **Step 1: Write `LinkChecks`.** Create `app/Crawler/LinkChecks.php`:

```php
<?php

namespace App\Crawler;

final class LinkChecks
{
    public const HIGH_CRAWL_DEPTH = 4;

    public const MANY_OUTLINKS = 100;

    /** Anchor texts too generic to describe their target. */
    public const NON_DESCRIPTIVE_ANCHORS = [
        'click here', 'here', 'read more', 'more', 'this', 'link', 'click',
        'learn more', 'details', 'continue', 'read', 'info', 'this page', 'go',
    ];

    public static function isNonDescriptive(string $anchor): bool
    {
        return in_array(mb_strtolower(trim($anchor)), self::NON_DESCRIPTIVE_ANCHORS, true);
    }

    public static function isLocalhost(string $url): bool
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');

        return in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)
            || str_ends_with($host, '.localhost');
    }

    /**
     * @param  array{internal:int,external:int,nofollow_internal:bool,no_anchor_internal:bool,non_descriptive_internal:bool,localhost:bool,non_crawlable_internal:bool}  $stats
     * @return string[]
     */
    public static function outlinkIssues(array $stats, ?int $depth): array
    {
        $issues = [];
        if ($depth !== null && $depth >= self::HIGH_CRAWL_DEPTH) {
            $issues[] = IssueCode::PagesHighCrawlDepth->value;
        }
        if ($stats['internal'] === 0) {
            $issues[] = IssueCode::PagesNoInternalOutlinks->value;
        }
        if ($stats['internal'] > self::MANY_OUTLINKS) {
            $issues[] = IssueCode::PagesManyInternalOutlinks->value;
        }
        if ($stats['external'] > self::MANY_OUTLINKS) {
            $issues[] = IssueCode::PagesManyExternalOutlinks->value;
        }
        if ($stats['nofollow_internal']) {
            $issues[] = IssueCode::InternalNofollowOutlinks->value;
        }
        if ($stats['no_anchor_internal']) {
            $issues[] = IssueCode::InternalOutlinksNoAnchor->value;
        }
        if ($stats['non_descriptive_internal']) {
            $issues[] = IssueCode::NonDescriptiveAnchorInternalOutlinks->value;
        }
        if ($stats['localhost']) {
            $issues[] = IssueCode::OutlinksToLocalhost->value;
        }
        if ($stats['non_crawlable_internal']) {
            $issues[] = IssueCode::PagesNonCrawlableInternalOutlinks->value;
        }

        return $issues;
    }

    /**
     * @param  array{count:int,follow:bool,nofollow:bool,anyIndexableSource:bool}  $detail
     * @return string[]
     */
    public static function inlinkIssues(array $detail): array
    {
        if ($detail['count'] === 0) {
            return [];
        }
        $issues = [];
        if ($detail['follow'] && $detail['nofollow']) {
            $issues[] = IssueCode::FollowNofollowInternalInlinks->value;
        }
        if ($detail['nofollow'] && ! $detail['follow']) {
            $issues[] = IssueCode::OnlyInternalNofollowInlinks->value;
        }
        if (! $detail['anyIndexableSource']) {
            $issues[] = IssueCode::OnlyNonIndexableInlinks->value;
        }

        return $issues;
    }
}
```

- [ ] **Step 2: Write the unit test.** Create `tests/Unit/Crawler/LinkChecksTest.php`:

```php
<?php

namespace Tests\Unit\Crawler;

use App\Crawler\LinkChecks;
use PHPUnit\Framework\TestCase;

class LinkChecksTest extends TestCase
{
    private function stats(array $over = []): array
    {
        return array_merge([
            'internal' => 5, 'external' => 5, 'nofollow_internal' => false,
            'no_anchor_internal' => false, 'non_descriptive_internal' => false,
            'localhost' => false, 'non_crawlable_internal' => false,
        ], $over);
    }

    public function test_is_non_descriptive(): void
    {
        $this->assertTrue(LinkChecks::isNonDescriptive('Click Here'));
        $this->assertTrue(LinkChecks::isNonDescriptive('read more'));
        $this->assertFalse(LinkChecks::isNonDescriptive('Our pricing plans'));
    }

    public function test_is_localhost(): void
    {
        $this->assertTrue(LinkChecks::isLocalhost('http://localhost/x'));
        $this->assertTrue(LinkChecks::isLocalhost('http://127.0.0.1:8080/'));
        $this->assertFalse(LinkChecks::isLocalhost('https://example.com/'));
    }

    public function test_depth_and_count_thresholds(): void
    {
        $this->assertNotContains('pages_high_crawl_depth', LinkChecks::outlinkIssues($this->stats(), 3));
        $this->assertContains('pages_high_crawl_depth', LinkChecks::outlinkIssues($this->stats(), 4));
        $this->assertNotContains('pages_many_internal_outlinks', LinkChecks::outlinkIssues($this->stats(['internal' => 100]), 0));
        $this->assertContains('pages_many_internal_outlinks', LinkChecks::outlinkIssues($this->stats(['internal' => 101]), 0));
        $this->assertContains('pages_many_external_outlinks', LinkChecks::outlinkIssues($this->stats(['external' => 101]), 0));
    }

    public function test_no_internal_outlinks_and_flags(): void
    {
        $this->assertContains('pages_no_internal_outlinks', LinkChecks::outlinkIssues($this->stats(['internal' => 0]), 0));
        $codes = LinkChecks::outlinkIssues($this->stats([
            'nofollow_internal' => true, 'no_anchor_internal' => true,
            'non_descriptive_internal' => true, 'localhost' => true, 'non_crawlable_internal' => true,
        ]), 0);
        foreach (['internal_nofollow_outlinks', 'internal_outlinks_no_anchor', 'non_descriptive_anchor_internal_outlinks', 'outlinks_to_localhost', 'pages_non_crawlable_internal_outlinks'] as $c) {
            $this->assertContains($c, $codes);
        }
    }

    public function test_inlink_issues(): void
    {
        $this->assertSame([], LinkChecks::inlinkIssues(['count' => 0, 'follow' => false, 'nofollow' => false, 'anyIndexableSource' => false]));
        $this->assertContains('follow_nofollow_internal_inlinks', LinkChecks::inlinkIssues(['count' => 2, 'follow' => true, 'nofollow' => true, 'anyIndexableSource' => true]));
        $this->assertContains('only_internal_nofollow_inlinks', LinkChecks::inlinkIssues(['count' => 1, 'follow' => false, 'nofollow' => true, 'anyIndexableSource' => true]));
        $this->assertContains('only_non_indexable_inlinks', LinkChecks::inlinkIssues(['count' => 1, 'follow' => true, 'nofollow' => false, 'anyIndexableSource' => false]));
        // A clean follow-only inlink from an indexable source → nothing.
        $this->assertSame([], LinkChecks::inlinkIssues(['count' => 1, 'follow' => true, 'nofollow' => false, 'anyIndexableSource' => true]));
    }
}
```

- [ ] **Step 3: Run + Pint.**

Run: `ddev php artisan test --filter=LinkChecksTest` → PASS.
Run: `ddev exec vendor/bin/pint --test app/Crawler/LinkChecks.php tests/Unit/Crawler/LinkChecksTest.php` → PASS.

- [ ] **Step 4: Commit.** `git add app/Crawler/LinkChecks.php tests/Unit/Crawler/LinkChecksTest.php && git commit -m "feat(crawler): LinkChecks — pure link-issue thresholds and logic"`

---

## Task 3: aggregate wiring (enrich the links pass + call `LinkChecks`)

**Files:** Modify `app/Jobs/AggregateCrawlJob.php`; Test `tests/Feature/Crawler/AggregateCrawlJobTest.php`, `tests/Feature/Crawler/CrawlControllerTest.php`.

**Interfaces:** Consumes `LinkChecks` (Task 2), the 12 codes (Task 1), and the existing `$pageByUrl` map (Phase 6).

- [ ] **Step 1: Build `$pageIndexableById` + enrich the links pass.** In `app/Jobs/AggregateCrawlJob.php::handle()`, add `use App\Crawler\LinkChecks;`. Build `$pageIndexableById` from `$identities` (`id → (bool) is_indexable`) alongside the existing maps. Then REPLACE the existing internal-links pass:

```php
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
```

  with the widened + enriched version:

```php
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
```

- [ ] **Step 2: Thread the maps into the chunk closure + apply the checks (HTML pages only).** Add `$outStats, $inlinkDetail, $emptyStats` to the `chunkById(500, function (Collection $pages) use (...))` `use (...)` list. In the per-page loop, after the existing duplicate/broken blocks, add:

```php
                if ($page->content_category === 'html') {
                    foreach (LinkChecks::outlinkIssues($outStats[$page->id] ?? $emptyStats, $depthsById[$page->id] ?? null) as $code) {
                        $issues[$code] = true;
                    }
                    foreach (LinkChecks::inlinkIssues($inlinkDetail[$key] ?? ['count' => 0, 'follow' => false, 'nofollow' => false, 'anyIndexableSource' => false]) as $code) {
                        $issues[$code] = true;
                    }
                }
```

  (`$key = $norm($page->url)` is already computed at the top of the loop; `$issues` is the `collect(...)->flip()` set already in use; `$depthsById` is already threaded in.)

- [ ] **Step 3: Add the aggregate feature test.** In `tests/Feature/Crawler/AggregateCrawlJobTest.php`, add:

```php
    public function test_flags_link_structure_issues(): void
    {
        $crawl = Crawl::factory()->create();
        $noindex = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/noindex', 'content_category' => 'html', 'is_indexable' => false, 'issues' => []]);
        $source = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/source', 'content_category' => 'html', 'is_indexable' => true, 'issues' => []]);
        $target = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/target', 'content_category' => 'html', 'is_indexable' => true, 'issues' => []]);
        $image = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/logo.png', 'content_category' => 'image', 'is_indexable' => false, 'issues' => []]);

        // source → nofollow internal to target, localhost link, internal to a noindex page.
        CrawlLink::factory()->create(['crawl_id' => $crawl->id, 'from_page_id' => $source->id, 'to_url' => 'https://x.test/target', 'type' => 'internal', 'rel' => 'nofollow', 'anchor' => 'x']);
        CrawlLink::factory()->create(['crawl_id' => $crawl->id, 'from_page_id' => $source->id, 'to_url' => 'http://localhost/x', 'type' => 'external', 'rel' => null, 'anchor' => 'x']);
        CrawlLink::factory()->create(['crawl_id' => $crawl->id, 'from_page_id' => $source->id, 'to_url' => 'https://x.test/noindex', 'type' => 'internal', 'rel' => null, 'anchor' => 'x']);
        // noindex page → target (follow), making target's only follow-inlink source non-indexable.
        CrawlLink::factory()->create(['crawl_id' => $crawl->id, 'from_page_id' => $noindex->id, 'to_url' => 'https://x.test/target', 'type' => 'internal', 'rel' => null, 'anchor' => 'x']);

        (new AggregateCrawlJob($crawl))->handle(app(SitemapReader::class));

        $s = $source->refresh()->issues;
        $this->assertContains('internal_nofollow_outlinks', $s);
        $this->assertContains('outlinks_to_localhost', $s);
        $this->assertContains('pages_non_crawlable_internal_outlinks', $s);

        $t = $target->refresh()->issues;
        // target has 2 internal inlinks: one nofollow (source), one follow (noindex) → both follow+nofollow present.
        $this->assertContains('follow_nofollow_internal_inlinks', $t);

        // The image resource must NOT get a dead-end outlink flag.
        $this->assertNotContains('pages_no_internal_outlinks', $image->refresh()->issues);
    }
```

- [ ] **Step 4: Add the controller feature test.** In `tests/Feature/Crawler/CrawlControllerTest.php`, add:

```php
    public function test_links_category_lists_a_page_with_a_link_issue(): void
    {
        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create();
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/x', 'issues' => ['outlinks_to_localhost']]);
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/clean', 'issues' => []]);

        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'group' => 'links', 'issue' => 'outlinks_to_localhost']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('pages.data', 1)
                ->where('pages.data.0.url', 'https://example.com/x')
            );
    }
```

- [ ] **Step 5: Run tests + build + Pint.**

Run: `ddev php artisan test --filter='AggregateCrawlJobTest|CrawlControllerTest'` → PASS.
Run: `ddev npm run build` → green (no frontend change).
Run: `ddev exec vendor/bin/pint --test app/Jobs/AggregateCrawlJob.php tests/Feature/Crawler/AggregateCrawlJobTest.php tests/Feature/Crawler/CrawlControllerTest.php` → PASS.

- [ ] **Step 6: Commit.** `git add app/Jobs/AggregateCrawlJob.php tests/Feature/Crawler/AggregateCrawlJobTest.php tests/Feature/Crawler/CrawlControllerTest.php && git commit -m "feat(crawler): per-page + cross-page link checks in the aggregate"`

---

## Final verification (whole plan)

- [ ] `ddev php artisan test` — full suite green.
- [ ] `ddev exec vendor/bin/pint --test` — clean.
- [ ] `ddev npm run build` — green.

## Self-Review notes (author)

- **Spec coverage:** pure logic + constants in `LinkChecks` (Task 2), enum/catalogue (Task 1), aggregate wiring + HTML guard (Task 3). ✓
- **Type consistency:** the 12 snake_case strings match across enum values, catalogue codes, lang keys, and `LinkChecks` emissions; severities in Task 1 match the catalogue (generalized severity-match enforces it). ✓
- **Bijection:** +12 cases, +12 active → 104 = 104 (dynamic cardinality test). ✓
- **Single-pass preserved:** the widened pass keeps the existing `$inlinks`/`$adjById` behavior (internal-only) intact while adding `$outStats`/`$inlinkDetail`; `no_anchor` vs `non_descriptive` are `elseif` (empty anchor is not "non-descriptive"). ✓
- **HTML guard:** all 12 checks gated on `content_category === 'html'` — the aggregate test asserts a non-HTML image doesn't get `pages_no_internal_outlinks`. ✓
- **anyIndexableSource defaults true** for unknown source ids (a missing source never forces `only_non_indexable_inlinks`). ✓

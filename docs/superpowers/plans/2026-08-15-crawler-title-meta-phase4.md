# Crawler Phase 4 — Page Title / Meta Description / Meta Keywords — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Activate all 15 planned `page_title` / `meta_description` / `meta_keywords` checks — character-count, pixel-width, structural (multiple / outside-`<head>`), `title_same_as_h1`, and meta-keywords (missing / multiple / cross-page duplicate).

**Architecture:** A new pure `PixelWidth` estimator; `MetaAnalyzer` extended with the per-page checks + keyword extraction; a `meta_keywords` column; and cross-page `duplicate_meta_keywords` in `AggregateCrawlJob`. No new severity, no frontend/lang.

**Tech Stack:** Laravel 13 / PHP 8.3, Symfony DomCrawler, PHPUnit, Pint.

**Spec:** `docs/superpowers/specs/2026-08-15-crawler-title-meta-phase4-design.md`

## Global Constraints

- **Bijection stays exact:** active catalogue codes === `IssueCode` cases. This phase adds 15 `IssueCode` cases and flips 15 catalogue entries planned→active, so both grow **48 → 63**.
- **`IssueCode::category()` and `::severity()` are exhaustive `match`** (no default arm). The 15 new cases get an arm in both. Categories: 6 → `PageTitle`, 6 → `MetaDescription`, 3 → `MetaKeywords`. Severities: `notice` for the 3 title char/pixel + `title_same_as_h1`, the 4 description char/pixel, and all 3 meta_keywords; `warning` for `multiple_title`, `title_outside_head`, `multiple_meta_description`, `meta_description_outside_head`.
- **The 15 lang labels already exist** (Phase 0) — no rename, no lang change.
- **No new crawl-time network requests.** All checks read the parsed DOM already in hand.
- **Commands via DDEV.** Backend gate `ddev php artisan test`; frontend gate `ddev npm run build` (no change expected); Pint clean `ddev exec vendor/bin/pint --test`.

---

## File Structure

**Create**
- `app/Crawler/PixelWidth.php`
- `tests/Unit/Crawler/PixelWidthTest.php`
- `database/migrations/2026_08_15_000002_add_meta_keywords_to_crawl_pages.php`

**Modify**
- `app/Crawler/IssueCode.php` — 15 cases + arms.
- `app/Crawler/CheckCatalog.php` — flip 15 entries planned→active.
- `app/Crawler/Analyzers/MetaAnalyzer.php` — new checks + keyword extraction.
- `app/Jobs/AggregateCrawlJob.php` — `duplicate_meta_keywords`.
- `tests/Unit/Crawler/MetaAnalyzerTest.php`, `tests/Unit/Crawler/CheckCatalogTest.php`, `tests/Feature/Crawler/AggregateCrawlJobTest.php`, `tests/Feature/Crawler/CrawlControllerTest.php`.

---

## Task 1: IssueCode cases + catalogue flip (title/meta/keywords)

**Files:**
- Modify: `app/Crawler/IssueCode.php`
- Modify: `app/Crawler/CheckCatalog.php`
- Test: `tests/Unit/Crawler/CheckCatalogTest.php`

**Interfaces:**
- Produces: 15 new `IssueCode` cases (below). Tasks 3 & 4 emit these.

- [ ] **Step 1: Add the 15 `IssueCode` cases.** In `app/Crawler/IssueCode.php`, after the response-code refinement block, add:

```php
    // page title refinements
    case TitleBelow30Chars = 'title_below_30_chars';
    case TitleBelow200px = 'title_below_200px';
    case TitleOver561px = 'title_over_561px';
    case TitleSameAsH1 = 'title_same_as_h1';
    case MultipleTitle = 'multiple_title';
    case TitleOutsideHead = 'title_outside_head';
    // meta description refinements
    case MetaDescriptionOver155Chars = 'meta_description_over_155_chars';
    case MetaDescriptionBelow70Chars = 'meta_description_below_70_chars';
    case MetaDescriptionOver985px = 'meta_description_over_985px';
    case MetaDescriptionBelow400px = 'meta_description_below_400px';
    case MultipleMetaDescription = 'multiple_meta_description';
    case MetaDescriptionOutsideHead = 'meta_description_outside_head';
    // meta keywords
    case MissingMetaKeywords = 'missing_meta_keywords';
    case MultipleMetaKeywords = 'multiple_meta_keywords';
    case DuplicateMetaKeywords = 'duplicate_meta_keywords';
```

- [ ] **Step 2: Add `severity()` arms.** In `severity()`:
  - add to the `'notice'` arm: `self::TitleBelow30Chars, self::TitleBelow200px, self::TitleOver561px, self::TitleSameAsH1, self::MetaDescriptionOver155Chars, self::MetaDescriptionBelow70Chars, self::MetaDescriptionOver985px, self::MetaDescriptionBelow400px, self::MissingMetaKeywords, self::MultipleMetaKeywords, self::DuplicateMetaKeywords`,
  - add to the `'warning'` arm: `self::MultipleTitle, self::TitleOutsideHead, self::MultipleMetaDescription, self::MetaDescriptionOutsideHead`.

  (Extend the existing arms; no default arm.)

- [ ] **Step 3: Add `category()` arms.** In `category()`:
  - extend the existing `IssueCategory::PageTitle` arm (currently `self::MissingTitle, self::DuplicateTitle, self::TitleTooLong`) with `self::TitleBelow30Chars, self::TitleBelow200px, self::TitleOver561px, self::TitleSameAsH1, self::MultipleTitle, self::TitleOutsideHead`,
  - extend the existing `IssueCategory::MetaDescription` arm (currently `self::MissingMetaDescription, self::DuplicateMetaDescription`) with `self::MetaDescriptionOver155Chars, self::MetaDescriptionBelow70Chars, self::MetaDescriptionOver985px, self::MetaDescriptionBelow400px, self::MultipleMetaDescription, self::MetaDescriptionOutsideHead`,
  - add a new arm: `self::MissingMetaKeywords, self::MultipleMetaKeywords, self::DuplicateMetaKeywords => IssueCategory::MetaKeywords,`.

- [ ] **Step 4: Flip the 15 catalogue entries.** In `app/Crawler/CheckCatalog.php`, change `'status' => self::P` → `'status' => self::A` for all 6 `page_title` planned codes (`title_below_200px`, `title_below_30_chars`, `title_over_561px`, `title_same_as_h1`, `multiple_title`, `title_outside_head`), all 6 `meta_description` planned codes (`meta_description_over_155_chars`, `meta_description_over_985px`, `meta_description_below_70_chars`, `meta_description_below_400px`, `multiple_meta_description`, `meta_description_outside_head`), and all 3 `meta_keywords` codes (`missing_meta_keywords`, `duplicate_meta_keywords`, `multiple_meta_keywords`).

- [ ] **Step 5: Update `CheckCatalogTest`.** In `tests/Unit/Crawler/CheckCatalogTest.php`, extend `test_active_codes_for_category_filters`:

```php
        $this->assertCount(9, CheckCatalog::activeCodesForCategory('page_title'));
        $this->assertCount(8, CheckCatalog::activeCodesForCategory('meta_description'));
        $this->assertCount(3, CheckCatalog::activeCodesForCategory('meta_keywords'));
```

(The dynamic `test_bijection_cardinality_holds` passes at 63 with no edit.)

- [ ] **Step 6: Run tests + Pint.**

Run: `ddev php artisan test --filter='CheckCatalogTest|IssueCodeTest'`
Expected: PASS (no `UnhandledMatchError`; counts as asserted; bijection holds).
Run: `ddev exec vendor/bin/pint --test app/Crawler/IssueCode.php app/Crawler/CheckCatalog.php`
Expected: PASS.

- [ ] **Step 7: Commit.**

```bash
git add app/Crawler/IssueCode.php app/Crawler/CheckCatalog.php tests/Unit/Crawler/CheckCatalogTest.php
git commit -m "feat(crawler): activate title/meta/keywords catalogue checks + issue codes"
```

---

## Task 2: `PixelWidth` estimator

**Files:**
- Create: `app/Crawler/PixelWidth.php`
- Test: `tests/Unit/Crawler/PixelWidthTest.php`

**Interfaces:**
- Produces: `PixelWidth::widthPx(string $text, float $scale = 1.0): int`. Task 3's `MetaAnalyzer` calls it (title scale 1.0, description scale 0.72).

- [ ] **Step 1: Write `PixelWidth`.** Create `app/Crawler/PixelWidth.php`:

```php
<?php

namespace App\Crawler;

class PixelWidth
{
    private const DEFAULT_WIDTH = 10;

    /** Approximate Arial advance widths (px) at the reference title font size. */
    private const WIDTHS = [
        ' ' => 5,
        'a' => 10, 'b' => 10, 'c' => 9, 'd' => 10, 'e' => 10, 'f' => 5, 'g' => 10,
        'h' => 10, 'i' => 4, 'j' => 4, 'k' => 9, 'l' => 4, 'm' => 15, 'n' => 10,
        'o' => 10, 'p' => 10, 'q' => 10, 'r' => 6, 's' => 9, 't' => 5, 'u' => 10,
        'v' => 9, 'w' => 13, 'x' => 9, 'y' => 9, 'z' => 9,
        'A' => 12, 'B' => 12, 'C' => 13, 'D' => 13, 'E' => 12, 'F' => 11, 'G' => 14,
        'H' => 13, 'I' => 5, 'J' => 9, 'K' => 12, 'L' => 10, 'M' => 15, 'N' => 13,
        'O' => 14, 'P' => 12, 'Q' => 14, 'R' => 13, 'S' => 12, 'T' => 11, 'U' => 13,
        'V' => 12, 'W' => 17, 'X' => 12, 'Y' => 12, 'Z' => 11,
        '0' => 10, '1' => 10, '2' => 10, '3' => 10, '4' => 10, '5' => 10, '6' => 10,
        '7' => 10, '8' => 10, '9' => 10,
        '.' => 5, ',' => 5, ':' => 5, ';' => 5, '!' => 5, '?' => 10, '-' => 6,
        '_' => 10, '(' => 6, ')' => 6, '/' => 5, '|' => 5, "'" => 4, '"' => 7,
        '&' => 12, '@' => 18, '*' => 7, '+' => 11, '=' => 11, '#' => 10, '%' => 15,
    ];

    /**
     * Estimated rendered width in pixels. $scale adjusts for font size (title 1.0,
     * snippet/description ~0.72). Approximate — a calibrated char table, not real rendering.
     */
    public static function widthPx(string $text, float $scale = 1.0): int
    {
        $sum = 0;
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $sum += self::WIDTHS[$char] ?? self::DEFAULT_WIDTH;
        }

        return (int) round($sum * $scale);
    }
}
```

- [ ] **Step 2: Write the unit test.** Create `tests/Unit/Crawler/PixelWidthTest.php`:

```php
<?php

namespace Tests\Unit\Crawler;

use App\Crawler\PixelWidth;
use PHPUnit\Framework\TestCase;

class PixelWidthTest extends TestCase
{
    public function test_longer_string_is_wider(): void
    {
        $this->assertGreaterThan(
            PixelWidth::widthPx('short'),
            PixelWidth::widthPx('a considerably longer string of text'),
        );
    }

    public function test_long_title_exceeds_561px(): void
    {
        $this->assertGreaterThan(561, PixelWidth::widthPx(str_repeat('a', 70)));
    }

    public function test_short_title_below_200px(): void
    {
        $this->assertLessThan(200, PixelWidth::widthPx('Home'));
    }

    public function test_scale_multiplies(): void
    {
        $full = PixelWidth::widthPx('hello world this is a description');
        $scaled = PixelWidth::widthPx('hello world this is a description', 0.72);
        $this->assertLessThan($full, $scaled);
        $this->assertEqualsWithDelta($full * 0.72, $scaled, 1.0);
    }

    public function test_non_ascii_does_not_error_and_uses_default(): void
    {
        $this->assertGreaterThan(0, PixelWidth::widthPx('café ñ 日本語'));
    }
}
```

- [ ] **Step 3: Run tests + Pint.**

Run: `ddev php artisan test --filter=PixelWidthTest`
Expected: PASS.
Run: `ddev exec vendor/bin/pint --test app/Crawler/PixelWidth.php tests/Unit/Crawler/PixelWidthTest.php`
Expected: PASS.

- [ ] **Step 4: Commit.**

```bash
git add app/Crawler/PixelWidth.php tests/Unit/Crawler/PixelWidthTest.php
git commit -m "feat(crawler): PixelWidth estimator for title/description width checks"
```

---

## Task 3: `MetaAnalyzer` expansion + `meta_keywords` column

**Files:**
- Create: `database/migrations/2026_08_15_000002_add_meta_keywords_to_crawl_pages.php`
- Modify: `app/Crawler/Analyzers/MetaAnalyzer.php`
- Test: `tests/Unit/Crawler/MetaAnalyzerTest.php`

**Interfaces:**
- Consumes: the 15 `IssueCode` cases (Task 1) and `PixelWidth` (Task 2).
- Produces: the per-page title/description/keywords issues + `meta_keywords` in `$data` (persisted to the new column). Task 4's aggregate consumes `meta_keywords`.

- [ ] **Step 1: Write the migration.** Create `database/migrations/2026_08_15_000002_add_meta_keywords_to_crawl_pages.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->text('meta_keywords')->nullable()->after('meta_description');
        });
    }

    public function down(): void
    {
        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->dropColumn('meta_keywords');
        });
    }
};
```

- [ ] **Step 2: Extend `MetaAnalyzer`.** In `app/Crawler/Analyzers/MetaAnalyzer.php`, add `use App\Crawler\PixelWidth;`. Inside `analyze()`, AFTER the existing title block (the `if ($title === null …) MissingTitle … elseif … TitleTooLong` block) and BEFORE the description block, add the title checks:

```php
        if ($title !== null && trim($title) !== '') {
            if (mb_strlen($title) < 30) {
                $r->issue(IssueCode::TitleBelow30Chars);
            }
            $titlePx = PixelWidth::widthPx($title, 1.0);
            if ($titlePx < 200) {
                $r->issue(IssueCode::TitleBelow200px);
            } elseif ($titlePx > 561) {
                $r->issue(IssueCode::TitleOver561px);
            }
            $h1 = $this->first($dom, 'h1');
            if ($h1 !== null && $h1 !== '' && mb_strtolower(trim($h1)) === mb_strtolower(trim($title))) {
                $r->issue(IssueCode::TitleSameAsH1);
            }
        }

        // Structural title checks (whole document, excluding SVG <title>).
        $allTitles = $dom->filterXPath('//title[not(ancestor::svg)]')->count();
        $headTitles = $dom->filterXPath('//head//title[not(ancestor::svg)]')->count();
        if ($allTitles > 1) {
            $r->issue(IssueCode::MultipleTitle);
        }
        if ($allTitles > $headTitles) {
            $r->issue(IssueCode::TitleOutsideHead);
        }
```

  AFTER the existing description block (the `if ($desc === null …) MissingMetaDescription` block), add the description checks:

```php
        if ($desc !== null && trim($desc) !== '') {
            $descLen = mb_strlen($desc);
            if ($descLen < 70) {
                $r->issue(IssueCode::MetaDescriptionBelow70Chars);
            } elseif ($descLen > 155) {
                $r->issue(IssueCode::MetaDescriptionOver155Chars);
            }
            $descPx = PixelWidth::widthPx($desc, 0.72);
            if ($descPx < 400) {
                $r->issue(IssueCode::MetaDescriptionBelow400px);
            } elseif ($descPx > 985) {
                $r->issue(IssueCode::MetaDescriptionOver985px);
            }
        }

        // Structural description checks.
        $allDesc = $dom->filter('meta[name="description"]')->count();
        $headDesc = $dom->filter('head meta[name="description"]')->count();
        if ($allDesc > 1) {
            $r->issue(IssueCode::MultipleMetaDescription);
        }
        if ($allDesc > $headDesc) {
            $r->issue(IssueCode::MetaDescriptionOutsideHead);
        }

        // Meta keywords: extract + structural checks.
        $keywords = $this->attr($dom, 'meta[name="keywords"]', 'content');
        $keywords = $keywords !== null && trim($keywords) !== '' ? trim($keywords) : null;
        $r->add('meta_keywords', $keywords);
        $keywordCount = $dom->filter('meta[name="keywords"]')->count();
        if ($keywordCount === 0) {
            $r->issue(IssueCode::MissingMetaKeywords);
        }
        if ($keywordCount > 1) {
            $r->issue(IssueCode::MultipleMetaKeywords);
        }
```

  (The existing `canonical`/`meta_robots`/`word_count`/`ThinContent` lines stay after these. `$this->first` and `$this->attr` are existing private helpers.)

- [ ] **Step 3: Extend `MetaAnalyzerTest`.** In `tests/Unit/Crawler/MetaAnalyzerTest.php`, add:

```php
    public function test_title_length_and_pixel_and_same_as_h1(): void
    {
        // Short title (< 30 chars, < 200px) that also equals the h1.
        $r = $this->analyze('<html><head><title>Home</title></head><body><h1>Home</h1>'.str_repeat('w ', 150).'</body></html>');
        $this->assertContains(IssueCode::TitleBelow30Chars, $r->issues);
        $this->assertContains(IssueCode::TitleBelow200px, $r->issues);
        $this->assertContains(IssueCode::TitleSameAsH1, $r->issues);

        // Very long title (> 561px).
        $long = $this->analyze('<html><head><title>'.str_repeat('a', 70).'</title></head><body>'.str_repeat('w ', 150).'</body></html>');
        $this->assertContains(IssueCode::TitleOver561px, $long->issues);
    }

    public function test_structural_title_checks_and_svg_is_ignored(): void
    {
        $two = $this->analyze('<html><head><title>One</title></head><body><title>Two</title>'.str_repeat('w ', 150).'</body></html>');
        $this->assertContains(IssueCode::MultipleTitle, $two->issues);
        $this->assertContains(IssueCode::TitleOutsideHead, $two->issues);

        // An SVG <title> must NOT count as a page title.
        $svg = $this->analyze('<html><head><title>Only</title></head><body><svg><title>icon</title></svg>'.str_repeat('w ', 150).'</body></html>');
        $this->assertNotContains(IssueCode::MultipleTitle, $svg->issues);
        $this->assertNotContains(IssueCode::TitleOutsideHead, $svg->issues);
    }

    public function test_description_length_and_structural(): void
    {
        $short = $this->analyze('<html><head><title>A normal length title here</title><meta name="description" content="Too short."></head><body>'.str_repeat('w ', 150).'</body></html>');
        $this->assertContains(IssueCode::MetaDescriptionBelow70Chars, $short->issues);
        $this->assertContains(IssueCode::MetaDescriptionBelow400px, $short->issues);

        $two = $this->analyze('<html><head><title>A normal length title here</title><meta name="description" content="one"><meta name="description" content="two"></head><body>'.str_repeat('w ', 150).'</body></html>');
        $this->assertContains(IssueCode::MultipleMetaDescription, $two->issues);
    }

    public function test_meta_keywords_extraction_and_checks(): void
    {
        $none = $this->analyze('<html><head><title>A normal length title here</title></head><body>'.str_repeat('w ', 150).'</body></html>');
        $this->assertContains(IssueCode::MissingMetaKeywords, $none->issues);
        $this->assertNull($none->data['meta_keywords']);

        $kw = $this->analyze('<html><head><title>A normal length title here</title><meta name="keywords" content="a, b"><meta name="keywords" content="c"></head><body>'.str_repeat('w ', 150).'</body></html>');
        $this->assertContains(IssueCode::MultipleMetaKeywords, $kw->issues);
        $this->assertSame('a, b', $kw->data['meta_keywords']);
    }
```

- [ ] **Step 4: Migrate + run tests + Pint.**

Run: `ddev php artisan test --filter=MetaAnalyzerTest`
Expected: PASS (RefreshDatabase not needed — these are pure unit tests; the migration is exercised in Task 4's feature tests).
Run: `ddev exec vendor/bin/pint --test app/Crawler/Analyzers/MetaAnalyzer.php database/migrations/2026_08_15_000002_add_meta_keywords_to_crawl_pages.php`
Expected: PASS.

- [ ] **Step 5: Commit.**

```bash
git add app/Crawler/Analyzers/MetaAnalyzer.php database/migrations/2026_08_15_000002_add_meta_keywords_to_crawl_pages.php tests/Unit/Crawler/MetaAnalyzerTest.php
git commit -m "feat(crawler): title/description/keyword checks + meta_keywords column"
```

---

## Task 4: cross-page `duplicate_meta_keywords` + feature coverage

**Files:**
- Modify: `app/Jobs/AggregateCrawlJob.php`
- Test: `tests/Feature/Crawler/AggregateCrawlJobTest.php`
- Test: `tests/Feature/Crawler/CrawlControllerTest.php`

**Interfaces:**
- Consumes: the `meta_keywords` column (Task 3) and `IssueCode::DuplicateMetaKeywords` (Task 1).

- [ ] **Step 1: Add keyword duplicates to the aggregate.** In `app/Jobs/AggregateCrawlJob.php`:
  - Add `'meta_keywords'` to the `identities` `->select([...])` list (the select currently is `['id', 'url', 'final_url', 'title', 'meta_description', 'created_at']`).
  - After `$dupDescriptions = $this->duplicates($identities, 'meta_description');`, add:
    `$dupKeywords = $this->duplicates($identities, 'meta_keywords');`
  - Add `$dupKeywords` to the `use (...)` list of the `chunkById(500, function (Collection $pages) use (...))` closure.
  - After the existing description-duplicate block (`if ($page->meta_description !== null && ($dupDescriptions[$page->meta_description] ?? 0) > 1) { $issues[IssueCode::DuplicateMetaDescription->value] = true; }`), add:

```php
                if ($page->meta_keywords !== null && ($dupKeywords[$page->meta_keywords] ?? 0) > 1) {
                    $issues[IssueCode::DuplicateMetaKeywords->value] = true;
                }
```

- [ ] **Step 2: Add the aggregate test.** In `tests/Feature/Crawler/AggregateCrawlJobTest.php`, add:

```php
    public function test_flags_duplicate_meta_keywords(): void
    {
        $crawl = Crawl::factory()->create();
        $a = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/a', 'meta_keywords' => 'shoes, boots', 'issues' => []]);
        $b = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/b', 'meta_keywords' => 'shoes, boots', 'issues' => []]);
        $c = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/c', 'meta_keywords' => 'unique kw', 'issues' => []]);

        (new AggregateCrawlJob($crawl))->handle(app(SitemapReader::class));

        $this->assertContains('duplicate_meta_keywords', $a->refresh()->issues);
        $this->assertContains('duplicate_meta_keywords', $b->refresh()->issues);
        $this->assertNotContains('duplicate_meta_keywords', $c->refresh()->issues);
    }
```

  (Match the file's existing imports/setup — `Crawl`, `CrawlPage`, `AggregateCrawlJob`, `SitemapReader` are already imported there.)

- [ ] **Step 3: Add the controller feature test.** In `tests/Feature/Crawler/CrawlControllerTest.php`, add:

```php
    public function test_page_title_category_lists_a_page_with_a_title_issue(): void
    {
        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create();
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/x', 'issues' => ['title_over_561px']]);
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/clean', 'issues' => []]);

        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'group' => 'page_title', 'issue' => 'title_over_561px']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('pages.data', 1)
                ->where('pages.data.0.url', 'https://example.com/x')
            );
    }
```

- [ ] **Step 4: Run tests + build + Pint.**

Run: `ddev php artisan test --filter='AggregateCrawlJobTest|CrawlControllerTest'`
Expected: PASS (the aggregate test exercises the new `meta_keywords` column via the migration).
Run: `ddev npm run build`
Expected: green (no frontend change).
Run: `ddev exec vendor/bin/pint --test app/Jobs/AggregateCrawlJob.php tests/Feature/Crawler/AggregateCrawlJobTest.php tests/Feature/Crawler/CrawlControllerTest.php`
Expected: PASS.

- [ ] **Step 5: Commit.**

```bash
git add app/Jobs/AggregateCrawlJob.php tests/Feature/Crawler/AggregateCrawlJobTest.php tests/Feature/Crawler/CrawlControllerTest.php
git commit -m "feat(crawler): cross-page duplicate_meta_keywords detection"
```

---

## Final verification (whole plan)

- [ ] `ddev php artisan test` — full suite green.
- [ ] `ddev exec vendor/bin/pint --test` — clean.
- [ ] `ddev npm run build` — green.

## Self-Review notes (author)

- **Spec coverage:** char-count + pixel + structural + same_as_h1 in `MetaAnalyzer` (Task 3), pixel table in `PixelWidth` (Task 2), enum/catalogue (Task 1), cross-page keyword duplicate (Task 4). ✓
- **Type consistency:** the 15 snake_case strings match across enum values, catalogue codes, lang keys, and analyzer emissions; severities in Task 1 match the catalogue. ✓
- **Bijection:** +15 cases, +15 active → 63 = 63 (dynamic cardinality test). ✓
- **SVG safety:** title counting uses `//title[not(ancestor::svg)]`, tested. ✓
- **Guards:** length/pixel checks gated on non-empty field; below/over are mutually exclusive (`elseif`). ✓
- **Storage:** `meta_keywords` persists via the observer's `array_merge($data, ...)` once `MetaAnalyzer` adds it; migration adds the column; aggregate reads it. ✓

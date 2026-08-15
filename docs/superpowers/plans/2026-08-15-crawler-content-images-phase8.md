# Crawler Phase 8 — Content (Tier-A) / Images — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Activate the algorithmic content checks (`lorem_ipsum`, cross-page `exact_duplicates`) + 5 image checks; fold in the Phase-7 `::1` `isLocalhost` fix. Final activation phase.

**Architecture:** `lorem_ipsum` + a `content_hash` fingerprint in `MetaAnalyzer`; cross-page `exact_duplicates` in `AggregateCrawlJob`; 4 image DOM checks in `ImageAnalyzer`; `image_over_100kb` on the image resource in the observer. No new severity, no frontend/lang.

**Tech Stack:** Laravel 13 / PHP 8.3, Symfony DomCrawler, PHPUnit, Pint.

**Spec:** `docs/superpowers/specs/2026-08-15-crawler-content-images-phase8-design.md`

## Global Constraints

- **Bijection stays exact:** active catalogue codes === `IssueCode` cases. This phase adds 7 `IssueCode` cases and flips 7 catalogue entries planned→active, so both grow **104 → 111**.
- **`IssueCode::category()` and `::severity()` are exhaustive `match`** (no default arm). The 7 new cases: 2 → `Content` (`lorem_ipsum`, `exact_duplicates` — both `warning`), 5 → `Images` (`image_over_100kb`, `image_missing_size_attributes`, `image_missing_alt_attribute`, `image_alt_over_100_chars`, `background_images` — all `notice`). Match the catalogue; the generalized severity-match test enforces it.
- **The 7 lang labels already exist** (Phase 0) — no rename, no lang change.
- **Commands via DDEV.** Backend gate `ddev php artisan test`; frontend gate `ddev npm run build` (no change expected); Pint clean `ddev exec vendor/bin/pint --test`.

---

## File Structure

**Create**
- `database/migrations/2026_08_15_000005_add_content_hash_to_crawl_pages.php`
- `tests/Feature/Crawler/CrawlPageObserverImageTest.php`

**Modify**
- `app/Crawler/IssueCode.php`, `app/Crawler/CheckCatalog.php`, `app/Crawler/LinkChecks.php`, `app/Crawler/Analyzers/MetaAnalyzer.php`, `app/Crawler/Analyzers/ImageAnalyzer.php`, `app/Observers/CrawlPageObserver.php`, `app/Jobs/AggregateCrawlJob.php`, and the tests `CheckCatalogTest`, `LinkChecksTest`, `MetaAnalyzerTest`, `ImageAnalyzerTest`, `AggregateCrawlJobTest`, `CrawlControllerTest`.

---

## Task 1: IssueCode cases + catalogue flip + `::1` fix

**Files:** Modify `app/Crawler/IssueCode.php`, `app/Crawler/CheckCatalog.php`, `app/Crawler/LinkChecks.php`; Test `tests/Unit/Crawler/CheckCatalogTest.php`, `tests/Unit/Crawler/LinkChecksTest.php`.

**Interfaces:** Produces 7 new `IssueCode` cases. Tasks 2 & 3 emit them.

- [ ] **Step 1: Add the 7 cases.** In `app/Crawler/IssueCode.php`, after the links block, add:

```php
    // content (Tier-A)
    case LoremIpsum = 'lorem_ipsum';
    case ExactDuplicates = 'exact_duplicates';
    // images
    case ImageOver100kb = 'image_over_100kb';
    case ImageMissingSizeAttributes = 'image_missing_size_attributes';
    case ImageMissingAltAttribute = 'image_missing_alt_attribute';
    case ImageAltOver100Chars = 'image_alt_over_100_chars';
    case BackgroundImages = 'background_images';
```

- [ ] **Step 2: `severity()` arms.** Add to `'warning'`: `self::LoremIpsum, self::ExactDuplicates`. Add to `'notice'`: `self::ImageOver100kb, self::ImageMissingSizeAttributes, self::ImageMissingAltAttribute, self::ImageAltOver100Chars, self::BackgroundImages`.

- [ ] **Step 3: `category()` arms.** Extend the existing `IssueCategory::Content` arm (currently `self::ThinContent`) with `self::LoremIpsum, self::ExactDuplicates`. Extend the existing `IssueCategory::Images` arm (currently `self::MissingAltText`) with the 5 image cases.

- [ ] **Step 4: Flip the 7 catalogue entries.** In `app/Crawler/CheckCatalog.php`, change `'status' => self::P` → `'status' => self::A` for: `exact_duplicates`, `lorem_ipsum` (content) and `image_over_100kb`, `image_missing_size_attributes`, `image_missing_alt_attribute`, `image_alt_over_100_chars`, `background_images` (images). LEAVE all other content codes (`readability_hard`, `readability_very_hard`, `near_duplicates`, `semantically_similar`, `low_relevance`, `soft_404`, `spelling_errors`, `grammar_errors`) and `incorrectly_sized_images` as `self::P`.

- [ ] **Step 5: Fix `isLocalhost` (`::1`).** In `app/Crawler/LinkChecks.php`, change the host extraction in `isLocalhost` to strip brackets:

```php
        $host = trim(strtolower(parse_url($url, PHP_URL_HOST) ?? ''), '[]');
```

  (Keep the rest of the method the same.)

- [ ] **Step 6: Update tests.** In `tests/Unit/Crawler/CheckCatalogTest.php`, extend `test_active_codes_for_category_filters`:

```php
        $this->assertCount(3, CheckCatalog::activeCodesForCategory('content'));
        $this->assertCount(6, CheckCatalog::activeCodesForCategory('images'));
```

  In `tests/Unit/Crawler/LinkChecksTest.php`, add to `test_is_localhost`:

```php
        $this->assertTrue(LinkChecks::isLocalhost('http://[::1]/'));
```

- [ ] **Step 7: Run + Pint.**

Run: `ddev php artisan test --filter='CheckCatalogTest|IssueCodeTest|LinkChecksTest'` → PASS (bijection 111; content=3/images=6; `::1` matches).
Run: `ddev exec vendor/bin/pint --test app/Crawler/IssueCode.php app/Crawler/CheckCatalog.php app/Crawler/LinkChecks.php` → PASS.

- [ ] **Step 8: Commit.** `git add app/Crawler/IssueCode.php app/Crawler/CheckCatalog.php app/Crawler/LinkChecks.php tests/Unit/Crawler/CheckCatalogTest.php tests/Unit/Crawler/LinkChecksTest.php && git commit -m "feat(crawler): activate content/images catalogue checks + fix ::1 localhost"`

---

## Task 2: content — `lorem_ipsum` + `content_hash` + `exact_duplicates`

**Files:** Create `database/migrations/2026_08_15_000005_add_content_hash_to_crawl_pages.php`; Modify `app/Crawler/Analyzers/MetaAnalyzer.php`, `app/Jobs/AggregateCrawlJob.php`; Test `tests/Unit/Crawler/MetaAnalyzerTest.php`, `tests/Feature/Crawler/AggregateCrawlJobTest.php`.

**Interfaces:** Consumes `IssueCode::LoremIpsum` / `IssueCode::ExactDuplicates` (Task 1). Produces `$data['content_hash']` (persisted) consumed by the aggregate.

- [ ] **Step 1: Migration.** Create `database/migrations/2026_08_15_000005_add_content_hash_to_crawl_pages.php`:

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
            $table->string('content_hash')->nullable()->after('h1');
        });
    }

    public function down(): void
    {
        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->dropColumn('content_hash');
        });
    }
};
```

- [ ] **Step 2: Extend `MetaAnalyzer`.** In `app/Crawler/Analyzers/MetaAnalyzer.php`, replace the existing word-count block:

```php
        $words = str_word_count(strip_tags($this->bodyHtml($dom)));
        $r->add('word_count', $words);
        if ($words < 100) {
            $r->issue(IssueCode::ThinContent);
        }
```

  with:

```php
        $text = strip_tags($this->bodyHtml($dom));
        $words = str_word_count($text);
        $r->add('word_count', $words);
        if ($words < 100) {
            $r->issue(IssueCode::ThinContent);
        }
        if (stripos($text, 'lorem ipsum') !== false) {
            $r->issue(IssueCode::LoremIpsum);
        }
        $normalized = preg_replace('/\s+/', ' ', mb_strtolower(trim($text)));
        $r->add('content_hash', ($words >= 100 && $normalized !== '') ? md5($normalized) : null);
```

- [ ] **Step 3: Cross-page `exact_duplicates` in the aggregate.** In `app/Jobs/AggregateCrawlJob.php`:
  - Add `'content_hash'` to the `identities` `->select([...])`.
  - After `$dupH1 = $this->duplicates($identities, 'h1');`, add `$dupHashes = $this->duplicates($identities, 'content_hash');`.
  - Add `$dupHashes` to the `chunkById` closure's `use (...)`.
  - After the existing duplicate blocks in the per-page loop, add:

```php
                if ($page->content_hash !== null && ($dupHashes[$page->content_hash] ?? 0) > 1) {
                    $issues[IssueCode::ExactDuplicates->value] = true;
                }
```

- [ ] **Step 4: Extend `MetaAnalyzerTest`.** Add:

```php
    public function test_lorem_ipsum_and_content_hash(): void
    {
        $body = str_repeat('Lorem ipsum dolor sit amet ', 30); // ~150 words, contains "lorem ipsum"
        $r = $this->analyze('<html><head><title>A normal length title here</title></head><body>'.$body.'</body></html>');
        $this->assertContains(IssueCode::LoremIpsum, $r->issues);
        $this->assertNotNull($r->data['content_hash']);
    }

    public function test_thin_body_has_null_content_hash(): void
    {
        $r = $this->analyze('<html><head><title>A normal length title here</title></head><body>only a few words here</body></html>');
        $this->assertNull($r->data['content_hash']);
    }

    public function test_identical_bodies_hash_equal_ignoring_whitespace_and_case(): void
    {
        $a = '<html><head><title>A normal length title here</title></head><body>'.str_repeat('word ', 150).'</body></html>';
        $b = '<html><head><title>A normal length title here</title></head><body>'.str_repeat('WORD  ', 150).'</body></html>';
        $this->assertSame($this->analyze($a)->data['content_hash'], $this->analyze($b)->data['content_hash']);
    }
```

- [ ] **Step 5: Add the aggregate test.** In `tests/Feature/Crawler/AggregateCrawlJobTest.php`, add:

```php
    public function test_flags_exact_duplicates(): void
    {
        $crawl = Crawl::factory()->create();
        $a = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/a', 'content_hash' => 'abc123', 'issues' => []]);
        $b = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/b', 'content_hash' => 'abc123', 'issues' => []]);
        $c = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/c', 'content_hash' => 'unique-hash', 'issues' => []]);

        (new AggregateCrawlJob($crawl))->handle(app(SitemapReader::class));

        $this->assertContains('exact_duplicates', $a->refresh()->issues);
        $this->assertContains('exact_duplicates', $b->refresh()->issues);
        $this->assertNotContains('exact_duplicates', $c->refresh()->issues);
    }
```

- [ ] **Step 6: Run + Pint.**

Run: `ddev php artisan test --filter='MetaAnalyzerTest|AggregateCrawlJobTest'` → PASS.
Run: `ddev exec vendor/bin/pint --test app/Crawler/Analyzers/MetaAnalyzer.php app/Jobs/AggregateCrawlJob.php database/migrations/2026_08_15_000005_add_content_hash_to_crawl_pages.php tests/Unit/Crawler/MetaAnalyzerTest.php tests/Feature/Crawler/AggregateCrawlJobTest.php` → PASS.

- [ ] **Step 7: Commit.** `git add app/Crawler/Analyzers/MetaAnalyzer.php app/Jobs/AggregateCrawlJob.php database/migrations/2026_08_15_000005_add_content_hash_to_crawl_pages.php tests/Unit/Crawler/MetaAnalyzerTest.php tests/Feature/Crawler/AggregateCrawlJobTest.php && git commit -m "feat(crawler): lorem_ipsum + cross-page exact_duplicates via content_hash"`

---

## Task 3: images — `ImageAnalyzer` DOM checks + `image_over_100kb`

**Files:** Create `tests/Feature/Crawler/CrawlPageObserverImageTest.php`; Modify `app/Crawler/Analyzers/ImageAnalyzer.php`, `app/Observers/CrawlPageObserver.php`, `tests/Unit/Crawler/ImageAnalyzerTest.php`, `tests/Feature/Crawler/CrawlControllerTest.php`.

**Interfaces:** Consumes the 5 image `IssueCode` cases (Task 1).

- [ ] **Step 1: Extend `ImageAnalyzer`.** Replace `app/Crawler/Analyzers/ImageAnalyzer.php`'s `analyze()` with:

```php
    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;
        $missing = [];
        $missingAttr = false;
        $missingSize = false;
        $altTooLong = false;

        $dom->filter('img')->each(function (Crawler $node) use (&$missing, &$missingAttr, &$missingSize, &$altTooLong) {
            $alt = $node->attr('alt');
            if ($alt === null || trim($alt) === '') {
                $missing[] = $node->attr('src') ?? '';
            }
            if ($alt === null) {
                $missingAttr = true;
            }
            if ($alt !== null && mb_strlen($alt) > 100) {
                $altTooLong = true;
            }
            if ($node->attr('width') === null || $node->attr('height') === null) {
                $missingSize = true;
            }
        });

        $r->add('images_missing_alt', $missing);
        if ($missing !== []) {
            $r->issue(IssueCode::MissingAltText);
        }
        if ($missingAttr) {
            $r->issue(IssueCode::ImageMissingAltAttribute);
        }
        if ($missingSize) {
            $r->issue(IssueCode::ImageMissingSizeAttributes);
        }
        if ($altTooLong) {
            $r->issue(IssueCode::ImageAltOver100Chars);
        }

        $backgroundImage = false;
        $dom->filter('[style]')->each(function (Crawler $node) use (&$backgroundImage) {
            if (stripos($node->attr('style') ?? '', 'background-image') !== false) {
                $backgroundImage = true;
            }
        });
        if ($backgroundImage) {
            $r->issue(IssueCode::BackgroundImages);
        }

        return $r;
    }
```

- [ ] **Step 2: `image_over_100kb` in the observer.** In `app/Observers/CrawlPageObserver.php::recordResponse()`, in the `else` (non-HTML) branch, after the existing `if ($sizeIssue = ResourceClassifier::sizeIssue($category, $size)) { ... }`, add:

```php
            if ($category === ResourceClassifier::IMAGE && $size > 102400) {
                $issues[] = IssueCode::ImageOver100kb->value;
            }
```

- [ ] **Step 3: Update the existing `ImageAnalyzerTest` clean fixture.** In `tests/Unit/Crawler/ImageAnalyzerTest.php`, `test_clean_page_has_no_issue`'s `<img src="/a.png" alt="ok">` now legitimately trips `image_missing_size_attributes` (no width/height). Update that fixture to a truly-clean image and keep the `assertSame([], $r->issues)`:

```php
        $r = (new ImageAnalyzer)->analyze(new Crawler('<html><body><img src="/a.png" alt="ok" width="100" height="50"></body></html>'), new PageContext('https://x.test/', 200, 'x.test'));
```

  (The `test_flags_images_without_alt` case uses non-exhaustive `assertContains`, so it needs no change.)

- [ ] **Step 4: Add new `ImageAnalyzerTest` cases.** Add:

```php
    public function test_missing_alt_attribute_distinct_from_empty(): void
    {
        $r = (new ImageAnalyzer)->analyze(new Crawler('<html><body><img src="/a.png"></body></html>'), new PageContext('https://x.test/', 200, 'x.test'));
        $this->assertContains(IssueCode::MissingAltText, $r->issues);
        $this->assertContains(IssueCode::ImageMissingAltAttribute, $r->issues);
    }

    public function test_size_long_alt_and_background(): void
    {
        $this->assertContains(IssueCode::ImageMissingSizeAttributes, (new ImageAnalyzer)->analyze(new Crawler('<html><body><img src="/a.png" alt="ok"></body></html>'), new PageContext('https://x.test/', 200, 'x.test'))->issues);
        $this->assertContains(IssueCode::ImageAltOver100Chars, (new ImageAnalyzer)->analyze(new Crawler('<html><body><img src="/a.png" width="1" height="1" alt="'.str_repeat('a', 101).'"></body></html>'), new PageContext('https://x.test/', 200, 'x.test'))->issues);
        $this->assertContains(IssueCode::BackgroundImages, (new ImageAnalyzer)->analyze(new Crawler('<html><body><div style="background-image:url(x.png)"></div></body></html>'), new PageContext('https://x.test/', 200, 'x.test'))->issues);
    }
```

- [ ] **Step 5: Add the observer test.** Create `tests/Feature/Crawler/CrawlPageObserverImageTest.php`:

```php
<?php

namespace Tests\Feature\Crawler;

use App\Crawler\PageAnalyzer;
use App\Models\Crawl;
use App\Models\Project;
use App\Observers\CrawlPageObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlPageObserverImageTest extends TestCase
{
    use RefreshDatabase;

    private function observer(Crawl $crawl): CrawlPageObserver
    {
        return new CrawlPageObserver($crawl, new PageAnalyzer, 'example.com');
    }

    public function test_flags_image_over_100kb(): void
    {
        $crawl = Crawl::factory()->for(Project::factory())->create();
        $big = $this->observer($crawl)->recordResponse('https://example.com/big.png', 200, ['Content-Type' => ['image/png']], str_repeat('x', 102401), 5.0);
        $this->assertContains('image_over_100kb', $big->issues);

        $small = $this->observer($crawl)->recordResponse('https://example.com/small.png', 200, ['Content-Type' => ['image/png']], 'tiny', 5.0);
        $this->assertNotContains('image_over_100kb', $small->issues);
    }
}
```

- [ ] **Step 6: Add the controller feature test.** In `tests/Feature/Crawler/CrawlControllerTest.php`, add:

```php
    public function test_images_category_lists_a_page_with_an_image_issue(): void
    {
        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create();
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/x', 'issues' => ['background_images']]);
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/clean', 'issues' => []]);

        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'group' => 'images', 'issue' => 'background_images']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('pages.data', 1)
                ->where('pages.data.0.url', 'https://example.com/x')
            );
    }
```

- [ ] **Step 7: Run tests + build + Pint.**

Run: `ddev php artisan test --filter='ImageAnalyzerTest|CrawlPageObserverImageTest|CrawlControllerTest'` → PASS.
Run: `ddev npm run build` → green (no frontend change).
Run: `ddev exec vendor/bin/pint --test app/Crawler/Analyzers/ImageAnalyzer.php app/Observers/CrawlPageObserver.php tests/Unit/Crawler/ImageAnalyzerTest.php tests/Feature/Crawler/CrawlPageObserverImageTest.php tests/Feature/Crawler/CrawlControllerTest.php` → PASS.

- [ ] **Step 8: Commit.** `git add app/Crawler/Analyzers/ImageAnalyzer.php app/Observers/CrawlPageObserver.php tests/Unit/Crawler/ImageAnalyzerTest.php tests/Feature/Crawler/CrawlPageObserverImageTest.php tests/Feature/Crawler/CrawlControllerTest.php && git commit -m "feat(crawler): image DOM checks + image_over_100kb resource check"`

---

## Final verification (whole plan)

- [ ] `ddev php artisan test` — full suite green.
- [ ] `ddev exec vendor/bin/pint --test` — clean.
- [ ] `ddev npm run build` — green.

## Self-Review notes (author)

- **Spec coverage:** enum/catalogue + `::1` fix (Task 1), content lorem_ipsum + content_hash + exact_duplicates (Task 2), image DOM checks + image_over_100kb (Task 3). ✓
- **Type consistency:** the 7 snake_case strings match across enum values, catalogue codes, lang keys, and analyzer/observer/aggregate emissions; severities in Task 1 match the catalogue (generalized severity-match enforces it). ✓
- **Bijection:** +7 cases, +7 active → 111 = 111 (dynamic cardinality test). ✓
- **Existing-test consequence:** Task 3 Step 3 updates `ImageAnalyzerTest::test_clean_page_has_no_issue`'s fixture (now trips `image_missing_size_attributes`) — a correct consequence, called out explicitly. ✓
- **content_hash guard:** only for `word_count >= 100` (non-thin); normalizer collapses whitespace + lowercases so trivially-different markup hashes equal; the aggregate mirrors `duplicate_title` with a null-guard. ✓
- **image_over_100kb** keys on the image resource `size_bytes` in the observer's non-HTML branch, independent of `large_resource`. ✓

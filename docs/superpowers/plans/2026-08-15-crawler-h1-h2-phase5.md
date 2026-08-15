# Crawler Phase 5 — H1 / H2 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Activate all 8 planned `h1` + `h2` checks — heading length, image-alt-as-heading, missing/multiple/duplicate(within-page) H2, non-sequential H2, and cross-page duplicate H1.

**Architecture:** `HeadingAnalyzer` (which already extracts every `{level,text}`) gains the per-page checks + stores the first H1 text; a `h1` column + `AggregateCrawlJob` handle cross-page `duplicate_h1`. No new severity, no frontend/lang.

**Tech Stack:** Laravel 13 / PHP 8.3, Symfony DomCrawler, PHPUnit, Pint.

**Spec:** `docs/superpowers/specs/2026-08-15-crawler-h1-h2-phase5-design.md`

## Global Constraints

- **Bijection stays exact:** active catalogue codes === `IssueCode` cases. This phase adds 8 `IssueCode` cases and flips 8 catalogue entries planned→active, so both grow **63 → 71**.
- **`IssueCode::category()` and `::severity()` are exhaustive `match`** (no default arm). The 8 new cases get an arm in both. Categories: 3 → `H1`, 5 → `H2`. Severities: `notice` for `h1_over_70_chars`, `alt_text_in_h1`, `missing_h2`, `duplicate_h2`, `h2_over_70_chars`; `warning` for `duplicate_h1`, `multiple_h2`, `h2_non_sequential`.
- **The 8 lang labels already exist** (Phase 0) — no rename, no lang change.
- **No new crawl-time network requests.** All per-page checks read the parsed DOM / extracted headings; cross-page duplicate reads a stored column.
- **Commands via DDEV.** Backend gate `ddev php artisan test`; frontend gate `ddev npm run build` (no change expected); Pint clean `ddev exec vendor/bin/pint --test`.

---

## File Structure

**Create**
- `database/migrations/2026_08_15_000003_add_h1_to_crawl_pages.php`

**Modify**
- `app/Crawler/IssueCode.php` — 8 cases + arms.
- `app/Crawler/CheckCatalog.php` — flip 8 entries planned→active.
- `app/Crawler/Analyzers/HeadingAnalyzer.php` — per-page H1/H2 checks + `h1` storage.
- `app/Jobs/AggregateCrawlJob.php` — cross-page `duplicate_h1`.
- `tests/Unit/Crawler/HeadingAnalyzerTest.php`, `tests/Unit/Crawler/CheckCatalogTest.php`, `tests/Feature/Crawler/AggregateCrawlJobTest.php`, `tests/Feature/Crawler/CrawlControllerTest.php`.

---

## Task 1: IssueCode cases + catalogue flip + severity-match hardening

**Files:**
- Modify: `app/Crawler/IssueCode.php`
- Modify: `app/Crawler/CheckCatalog.php`
- Test: `tests/Unit/Crawler/CheckCatalogTest.php`

**Interfaces:**
- Produces: 8 new `IssueCode` cases (below). Tasks 2 & 3 emit these.

- [ ] **Step 1: Add the 8 `IssueCode` cases.** In `app/Crawler/IssueCode.php`, after the title/meta refinement block, add:

```php
    // h1 refinements
    case DuplicateH1 = 'duplicate_h1';
    case H1Over70Chars = 'h1_over_70_chars';
    case AltTextInH1 = 'alt_text_in_h1';
    // h2
    case MissingH2 = 'missing_h2';
    case DuplicateH2 = 'duplicate_h2';
    case H2Over70Chars = 'h2_over_70_chars';
    case MultipleH2 = 'multiple_h2';
    case H2NonSequential = 'h2_non_sequential';
```

- [ ] **Step 2: Add `severity()` arms.** In `severity()`:
  - add to the `'notice'` arm: `self::H1Over70Chars, self::AltTextInH1, self::MissingH2, self::DuplicateH2, self::H2Over70Chars`,
  - add to the `'warning'` arm: `self::DuplicateH1, self::MultipleH2, self::H2NonSequential`.

  (Extend existing arms; no default arm.)

- [ ] **Step 3: Add `category()` arms.** In `category()`:
  - extend the existing `IssueCategory::H1` arm (currently `self::MissingH1, self::MultipleH1, self::HeadingOrderSkip`) with `self::DuplicateH1, self::H1Over70Chars, self::AltTextInH1`,
  - add a new arm: `self::MissingH2, self::DuplicateH2, self::H2Over70Chars, self::MultipleH2, self::H2NonSequential => IssueCategory::H2,`.

- [ ] **Step 4: Flip the 8 catalogue entries.** In `app/Crawler/CheckCatalog.php`, change `'status' => self::P` → `'status' => self::A` for the 3 `h1` planned codes (`duplicate_h1`, `h1_over_70_chars`, `alt_text_in_h1`) and the 5 `h2` codes (`missing_h2`, `duplicate_h2`, `h2_over_70_chars`, `multiple_h2`, `h2_non_sequential`).

- [ ] **Step 5: Update `CheckCatalogTest`.** In `tests/Unit/Crawler/CheckCatalogTest.php`:
  - Extend `test_active_codes_for_category_filters`:

```php
        $this->assertCount(6, CheckCatalog::activeCodesForCategory('h1'));
        $this->assertCount(5, CheckCatalog::activeCodesForCategory('h2'));
```

  - Generalize the severity-match test from security to ALL active codes (replace `test_catalogue_severity_matches_issue_code_for_security` with):

```php
    public function test_catalogue_severity_matches_issue_code_for_all_active_codes(): void
    {
        foreach (CheckCatalog::all() as $entry) {
            if ($entry['status'] !== 'active') {
                continue;
            }
            $this->assertSame(
                IssueCode::from($entry['code'])->severity(),
                $entry['severity'],
                $entry['code'],
            );
        }
    }
```

  (The dynamic `test_bijection_cardinality_holds` passes at 71 with no edit.)

- [ ] **Step 6: Run tests + Pint.**

Run: `ddev php artisan test --filter='CheckCatalogTest|IssueCodeTest'`
Expected: PASS (no `UnhandledMatchError`; h1=6/h2=5; severity-match over all 71 active codes; bijection holds).
Run: `ddev exec vendor/bin/pint --test app/Crawler/IssueCode.php app/Crawler/CheckCatalog.php`
Expected: PASS.

- [ ] **Step 7: Commit.**

```bash
git add app/Crawler/IssueCode.php app/Crawler/CheckCatalog.php tests/Unit/Crawler/CheckCatalogTest.php
git commit -m "feat(crawler): activate h1/h2 catalogue checks + issue codes"
```

---

## Task 2: `HeadingAnalyzer` expansion + `h1` column

**Files:**
- Create: `database/migrations/2026_08_15_000003_add_h1_to_crawl_pages.php`
- Modify: `app/Crawler/Analyzers/HeadingAnalyzer.php`
- Test: `tests/Unit/Crawler/HeadingAnalyzerTest.php`

**Interfaces:**
- Consumes: the 8 `IssueCode` cases (Task 1). (`DuplicateH1` is emitted by Task 3, not here.)
- Produces: the per-page H1/H2 issues + the first H1 text in `$data['h1']` (persisted to the new column). Task 3's aggregate consumes `h1`.

- [ ] **Step 1: Write the migration.** Create `database/migrations/2026_08_15_000003_add_h1_to_crawl_pages.php`:

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
            $table->text('h1')->nullable()->after('meta_keywords');
        });
    }

    public function down(): void
    {
        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->dropColumn('h1');
        });
    }
};
```

- [ ] **Step 2: Extend `HeadingAnalyzer`.** In `app/Crawler/Analyzers/HeadingAnalyzer.php`, INSIDE `analyze()`, after the existing heading-order-skip loop and BEFORE `return $r;`, add the new checks + storage:

```php
        $h1Texts = array_values(array_map(fn ($h) => $h['text'], array_filter($headings, fn ($h) => $h['level'] === 1)));
        $h2Texts = array_values(array_map(fn ($h) => $h['text'], array_filter($headings, fn ($h) => $h['level'] === 2)));

        // First H1 text, for cross-page duplicate detection; null when absent or empty.
        $firstH1 = $h1Texts[0] ?? null;
        $r->add('h1', ($firstH1 !== null && $firstH1 !== '') ? $firstH1 : null);

        // H1 length + image-alt-as-heading.
        foreach ($h1Texts as $text) {
            if (mb_strlen($text) > 70) {
                $r->issue(IssueCode::H1Over70Chars);
                break;
            }
        }
        if ($this->hasImageWithAlt($dom, 'h1')) {
            $r->issue(IssueCode::AltTextInH1);
        }

        // H2 checks.
        if (count($h2Texts) === 0) {
            $r->issue(IssueCode::MissingH2);
        }
        if (count($h2Texts) > 1) {
            $r->issue(IssueCode::MultipleH2);
        }
        foreach ($h2Texts as $text) {
            if (mb_strlen($text) > 70) {
                $r->issue(IssueCode::H2Over70Chars);
                break;
            }
        }
        // Within-page duplicate H2 (trimmed already; case-insensitive; ignore empties).
        $normalizedH2 = array_map(fn ($t) => mb_strtolower($t), array_filter($h2Texts, fn ($t) => $t !== ''));
        if (count($normalizedH2) !== count(array_unique($normalizedH2))) {
            $r->issue(IssueCode::DuplicateH2);
        }
        // Non-sequential: an H2 before the first H1 (only when an H1 exists).
        if ($h1Count > 0 && count($h2Texts) > 0) {
            $firstH1Index = $firstH2Index = null;
            foreach ($headings as $i => $h) {
                if ($h['level'] === 1 && $firstH1Index === null) {
                    $firstH1Index = $i;
                }
                if ($h['level'] === 2 && $firstH2Index === null) {
                    $firstH2Index = $i;
                }
            }
            if ($firstH2Index !== null && $firstH1Index !== null && $firstH2Index < $firstH1Index) {
                $r->issue(IssueCode::H2NonSequential);
            }
        }
```

  (`$h1Count` and `$headings` are already in scope from the existing code.) Add this private helper method to the class:

```php
    private function hasImageWithAlt(Crawler $dom, string $selector): bool
    {
        $found = false;
        $dom->filter($selector.' img')->each(function (Crawler $img) use (&$found) {
            $alt = $img->attr('alt');
            if ($alt !== null && trim($alt) !== '') {
                $found = true;
            }
        });

        return $found;
    }
```

- [ ] **Step 3: Update existing `HeadingAnalyzerTest` fixtures that now trip `missing_h2`.** Any existing test whose fixture has an H1 (or headings) but NO `<h2>` will now legitimately emit `IssueCode::MissingH2`. Update those `assertSame([], $r->issues)` / issue expectations to include `IssueCode::MissingH2` where the fixture genuinely has no H2 — this is a correct consequence of the new check, not a regression. (Leave field/heading-extraction assertions untouched.)

- [ ] **Step 4: Add new `HeadingAnalyzerTest` cases.** In `tests/Unit/Crawler/HeadingAnalyzerTest.php`, add:

```php
    public function test_h1_length_and_alt_text_and_stored_h1(): void
    {
        $long = $this->analyze('<h1>'.str_repeat('a', 71).'</h1><h2>ok</h2>');
        $this->assertContains(IssueCode::H1Over70Chars, $long->issues);

        $alt = $this->analyze('<h1><img alt="Logo brand name"></h1><h2>ok</h2>');
        $this->assertContains(IssueCode::AltTextInH1, $alt->issues);

        $stored = $this->analyze('<h1>Real Heading</h1><h2>ok</h2>');
        $this->assertSame('Real Heading', $stored->data['h1']);
    }

    public function test_h2_missing_multiple_duplicate_and_length(): void
    {
        $missing = $this->analyze('<h1>Title</h1><p>no h2 here</p>');
        $this->assertContains(IssueCode::MissingH2, $missing->issues);

        $multi = $this->analyze('<h1>Title</h1><h2>One</h2><h2>Two</h2>');
        $this->assertContains(IssueCode::MultipleH2, $multi->issues);

        $dup = $this->analyze('<h1>Title</h1><h2>Same</h2><h2>same</h2>');
        $this->assertContains(IssueCode::DuplicateH2, $dup->issues);

        $long = $this->analyze('<h1>Title</h1><h2>'.str_repeat('b', 71).'</h2>');
        $this->assertContains(IssueCode::H2Over70Chars, $long->issues);
    }

    public function test_h2_non_sequential_only_before_h1(): void
    {
        $before = $this->analyze('<h2>Early</h2><h1>Title</h1>');
        $this->assertContains(IssueCode::H2NonSequential, $before->issues);

        $after = $this->analyze('<h1>Title</h1><h2>After</h2>');
        $this->assertNotContains(IssueCode::H2NonSequential, $after->issues);
    }
```

- [ ] **Step 5: Run tests + Pint.**

Run: `ddev php artisan test --filter=HeadingAnalyzerTest`
Expected: PASS (new cases + any updated existing fixtures).
Run: `ddev exec vendor/bin/pint --test app/Crawler/Analyzers/HeadingAnalyzer.php database/migrations/2026_08_15_000003_add_h1_to_crawl_pages.php`
Expected: PASS.

- [ ] **Step 6: Commit.**

```bash
git add app/Crawler/Analyzers/HeadingAnalyzer.php database/migrations/2026_08_15_000003_add_h1_to_crawl_pages.php tests/Unit/Crawler/HeadingAnalyzerTest.php
git commit -m "feat(crawler): H1/H2 checks + h1 column for duplicate detection"
```

---

## Task 3: cross-page `duplicate_h1` + feature coverage

**Files:**
- Modify: `app/Jobs/AggregateCrawlJob.php`
- Test: `tests/Feature/Crawler/AggregateCrawlJobTest.php`
- Test: `tests/Feature/Crawler/CrawlControllerTest.php`

**Interfaces:**
- Consumes: the `h1` column (Task 2) and `IssueCode::DuplicateH1` (Task 1).

- [ ] **Step 1: Add H1 duplicates to the aggregate.** In `app/Jobs/AggregateCrawlJob.php`:
  - Add `'h1'` to the `identities` `->select([...])` list (currently `['id', 'url', 'final_url', 'title', 'meta_description', 'meta_keywords', 'created_at']`).
  - After `$dupKeywords = $this->duplicates($identities, 'meta_keywords');`, add:
    `$dupH1 = $this->duplicates($identities, 'h1');`
  - Add `$dupH1` to the `chunkById(500, function (Collection $pages) use (...))` closure's `use (...)` list.
  - After the existing keyword-duplicate block (`if ($page->meta_keywords !== null && ($dupKeywords[$page->meta_keywords] ?? 0) > 1) { $issues[IssueCode::DuplicateMetaKeywords->value] = true; }`), add:

```php
                if ($page->h1 !== null && ($dupH1[$page->h1] ?? 0) > 1) {
                    $issues[IssueCode::DuplicateH1->value] = true;
                }
```

- [ ] **Step 2: Add the aggregate test.** In `tests/Feature/Crawler/AggregateCrawlJobTest.php`, add:

```php
    public function test_flags_duplicate_h1(): void
    {
        $crawl = Crawl::factory()->create();
        $a = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/a', 'h1' => 'Welcome', 'issues' => []]);
        $b = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/b', 'h1' => 'Welcome', 'issues' => []]);
        $c = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/c', 'h1' => 'Unique Heading', 'issues' => []]);

        (new AggregateCrawlJob($crawl))->handle(app(SitemapReader::class));

        $this->assertContains('duplicate_h1', $a->refresh()->issues);
        $this->assertContains('duplicate_h1', $b->refresh()->issues);
        $this->assertNotContains('duplicate_h1', $c->refresh()->issues);
    }
```

  (`Crawl`, `CrawlPage`, `AggregateCrawlJob`, `SitemapReader` are already imported.)

- [ ] **Step 3: Add the controller feature test.** In `tests/Feature/Crawler/CrawlControllerTest.php`, add:

```php
    public function test_h2_category_lists_a_page_with_an_h2_issue(): void
    {
        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create();
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/x', 'issues' => ['missing_h2']]);
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/clean', 'issues' => []]);

        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'group' => 'h2', 'issue' => 'missing_h2']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('pages.data', 1)
                ->where('pages.data.0.url', 'https://example.com/x')
            );
    }
```

- [ ] **Step 4: Run tests + build + Pint.**

Run: `ddev php artisan test --filter='AggregateCrawlJobTest|CrawlControllerTest'`
Expected: PASS (the aggregate test exercises the new `h1` column via the migration).
Run: `ddev npm run build`
Expected: green (no frontend change).
Run: `ddev exec vendor/bin/pint --test app/Jobs/AggregateCrawlJob.php tests/Feature/Crawler/AggregateCrawlJobTest.php tests/Feature/Crawler/CrawlControllerTest.php`
Expected: PASS.

- [ ] **Step 5: Commit.**

```bash
git add app/Jobs/AggregateCrawlJob.php tests/Feature/Crawler/AggregateCrawlJobTest.php tests/Feature/Crawler/CrawlControllerTest.php
git commit -m "feat(crawler): cross-page duplicate_h1 detection"
```

---

## Final verification (whole plan)

- [ ] `ddev php artisan test` — full suite green.
- [ ] `ddev exec vendor/bin/pint --test` — clean.
- [ ] `ddev npm run build` — green.

## Self-Review notes (author)

- **Spec coverage:** 7 per-page checks + `h1` storage in `HeadingAnalyzer` (Task 2), enum/catalogue + severity hardening (Task 1), cross-page `duplicate_h1` (Task 3). ✓
- **Type consistency:** the 8 snake_case strings match across enum values, catalogue codes, lang keys, analyzer/aggregate emissions; severities in Task 1 match the catalogue (verified all 63 prior active codes already agree, so the generalized test is safe). ✓
- **Bijection:** +8 cases, +8 active → 71 = 71 (dynamic cardinality test). ✓
- **Existing-test consequence:** Task 2 Step 3 updates fixtures that now trip `missing_h2` (h1-but-no-h2 pages) — a correct consequence, called out explicitly. ✓
- **h2_non_sequential guard:** only when an H1 exists (no double-flag with `missing_h1`). ✓
- **duplicate_h1 storage:** first H1 text (null when empty/image-only) persists via observer `array_merge`; aggregate mirrors the keyword/description dup pattern with a null-guard. ✓

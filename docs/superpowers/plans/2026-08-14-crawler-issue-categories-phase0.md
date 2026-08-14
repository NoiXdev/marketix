# Crawler Issue Categories — Phase 0 (Framework) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reorganise the crawl overview (`app.project.crawls.show`) into category tabs driven by a check catalogue, wiring the already-existing issues into their categories and greying out every not-yet-implemented check.

**Architecture:** A single source of truth `App\Crawler\CheckCatalog` lists every check (code, category, severity, status active|planned). `IssueCode` gains `category()`. The controller filters pages by category (`?group=`) / single check (`?issue=`) and passes the catalogue + per-category/per-check counts. The React Show page renders tabs (Overview / All URLs / 14 categories), a per-category check dropdown (planned entries disabled), and the filtered URL table. Phase 0 adds NO new crawl-time checks — it only reads the `issues` already stored.

**Tech Stack:** Laravel 13 / PHP 8.3, Inertia + React 19 / TS, MariaDB (dev) / sqlite (tests), PHPUnit.

**Spec:** `docs/superpowers/specs/2026-08-14-crawler-issue-categories-design.md`

## Global Constraints

- We work in the MAIN repo on branch `v3` (no worktree). Commands run via DDEV directly: `ddev php artisan …`, `ddev composer exec pint -- …`, `ddev npm run build`.
- Mandatory Pint gate: run `ddev composer exec pint -- <files>` and confirm clean before committing (CI runs `vendor/bin/pint --test`). PHP files: blank line after `<?php`, no stray `// path` header comments copied from this plan's code blocks.
- Frontend gate is `ddev npm run build` (tsc + vite); do NOT use `npm run lint` (broken).
- Ziggy: `route()` always with object params, e.g. `route('app.project.crawls.show', { project: id })`.
- Multi-tenant: the controller scopes everything via `$project = $request->get('project')` then `$project->crawls()…`.
- Enums are string-backed with `label()` reading `__('crawler.…')`; lang keys added in Task 3.
- Co-author trailer on every commit: `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`.
- Phase 0 must NOT change the page-detail view, and must NOT emit any new issue at crawl time.

---

## Task 1: IssueCategory enum + CheckCatalog registry + IssueCode::category()

**Files:**
- Create: `app/Crawler/IssueCategory.php`
- Create: `app/Crawler/CheckCatalog.php`
- Modify: `app/Crawler/IssueCode.php` (add `category()`)
- Test: `tests/Unit/Crawler/CheckCatalogTest.php`

**Interfaces:**
- Produces:
  - `IssueCategory` (string enum): `Security='security'`, `ResponseCodes='response_codes'`, `Url='url'`, `PageTitle='page_title'`, `MetaDescription='meta_description'`, `MetaKeywords='meta_keywords'`, `H1='h1'`, `H2='h2'`, `Content='content'`, `Images='images'`, `Canonicals='canonicals'`, `Pagination='pagination'`, `Links='links'`, `Other='other'`; `label(): string`.
  - `CheckCatalog::all(): array` of `['code'=>string,'category'=>string,'severity'=>string,'status'=>'active'|'planned']`; `CheckCatalog::activeCodes(): string[]`; `CheckCatalog::activeCodesForCategory(string $category): string[]`; `CheckCatalog::categoryOf(string $code): ?string`.
  - `IssueCode::category(): IssueCategory`.

- [ ] **Step 1: Write the failing test**

```
tests/Unit/Crawler/CheckCatalogTest.php
```
```php
<?php

namespace Tests\Unit\Crawler;

use App\Crawler\CheckCatalog;
use App\Crawler\IssueCategory;
use App\Crawler\IssueCode;
use PHPUnit\Framework\TestCase;

class CheckCatalogTest extends TestCase
{
    public function test_every_entry_is_well_formed(): void
    {
        $categories = array_map(fn ($c) => $c->value, IssueCategory::cases());

        foreach (CheckCatalog::all() as $entry) {
            $this->assertArrayHasKey('code', $entry);
            $this->assertContains($entry['category'], $categories, $entry['code']);
            $this->assertContains($entry['severity'], ['error', 'warning', 'notice'], $entry['code']);
            $this->assertContains($entry['status'], ['active', 'planned'], $entry['code']);
        }
    }

    public function test_codes_are_unique(): void
    {
        $codes = array_column(CheckCatalog::all(), 'code');
        $this->assertSame($codes, array_values(array_unique($codes)));
    }

    public function test_every_issue_code_is_an_active_catalogue_entry(): void
    {
        $active = CheckCatalog::activeCodes();

        foreach (IssueCode::cases() as $case) {
            $this->assertContains($case->value, $active, "IssueCode {$case->value} must be an active catalogue check");
            // category() agrees with the catalogue
            $this->assertSame(CheckCatalog::categoryOf($case->value), $case->category()->value, $case->value);
        }
    }

    public function test_active_codes_are_all_real_issue_codes(): void
    {
        foreach (CheckCatalog::all() as $entry) {
            if ($entry['status'] === 'active') {
                $this->assertNotNull(IssueCode::tryFrom($entry['code']), "active code {$entry['code']} has no IssueCode case");
            }
        }
    }

    public function test_active_codes_for_category_filters(): void
    {
        $this->assertContains('missing_title', CheckCatalog::activeCodesForCategory('page_title'));
        $this->assertSame([], CheckCatalog::activeCodesForCategory('security')); // all planned in Phase 0
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=CheckCatalogTest`
Expected: FAIL (classes missing).

- [ ] **Step 3: Create the IssueCategory enum**

```
app/Crawler/IssueCategory.php
```
```php
<?php

namespace App\Crawler;

enum IssueCategory: string
{
    case Security = 'security';
    case ResponseCodes = 'response_codes';
    case Url = 'url';
    case PageTitle = 'page_title';
    case MetaDescription = 'meta_description';
    case MetaKeywords = 'meta_keywords';
    case H1 = 'h1';
    case H2 = 'h2';
    case Content = 'content';
    case Images = 'images';
    case Canonicals = 'canonicals';
    case Pagination = 'pagination';
    case Links = 'links';
    case Other = 'other';

    public function label(): string
    {
        return __('crawler.category_group.'.$this->value);
    }
}
```

- [ ] **Step 4: Create the CheckCatalog**

```
app/Crawler/CheckCatalog.php
```
```php
<?php

namespace App\Crawler;

/**
 * The single source of truth for every crawler check: its category, severity and
 * whether it is implemented yet (active) or catalogued for a later phase (planned).
 * Active codes are real IssueCode cases the crawler emits; planned codes are shown
 * greyed in the UI and never emitted until implemented.
 */
class CheckCatalog
{
    private const A = 'active';

    private const P = 'planned';

    /** @return array<int, array{code: string, category: string, severity: string, status: string}> */
    public static function all(): array
    {
        return [
            // security (all planned — Phase 1)
            ['code' => 'missing_csp_header', 'category' => 'security', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'missing_x_frame_options', 'category' => 'security', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'missing_x_content_type_options', 'category' => 'security', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'missing_hsts_header', 'category' => 'security', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'unsafe_cross_origin_links', 'category' => 'security', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'missing_referrer_policy', 'category' => 'security', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'http_urls', 'category' => 'security', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'https_urls', 'category' => 'security', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'mixed_content', 'category' => 'security', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'form_url_insecure', 'category' => 'security', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'form_on_http', 'category' => 'security', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'protocol_relative_resource_links', 'category' => 'security', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'wrong_content_type', 'category' => 'security', 'severity' => 'warning', 'status' => self::P],

            // response_codes
            ['code' => 'robots_blocked', 'category' => 'response_codes', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'client_error', 'category' => 'response_codes', 'severity' => 'error', 'status' => self::A],
            ['code' => 'server_error', 'category' => 'response_codes', 'severity' => 'error', 'status' => self::A],
            ['code' => 'redirect_chain', 'category' => 'response_codes', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'external_server_error_5xx', 'category' => 'response_codes', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'internal_redirect_3xx', 'category' => 'response_codes', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'internal_redirect_loop', 'category' => 'response_codes', 'severity' => 'error', 'status' => self::P],
            ['code' => 'internal_http_refresh_redirect', 'category' => 'response_codes', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'internal_meta_refresh_redirect', 'category' => 'response_codes', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'internal_js_redirect', 'category' => 'response_codes', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'internal_success_2xx', 'category' => 'response_codes', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'internal_no_response', 'category' => 'response_codes', 'severity' => 'error', 'status' => self::P],
            ['code' => 'internal_blocked_resource', 'category' => 'response_codes', 'severity' => 'warning', 'status' => self::P],

            // url (all planned — Phase 2)
            ['code' => 'url_non_ascii', 'category' => 'url', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'url_underscores', 'category' => 'url', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'url_uppercase', 'category' => 'url', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'url_multiple_slashes', 'category' => 'url', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'url_repetitive_path', 'category' => 'url', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'url_contains_space', 'category' => 'url', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'url_internal_search', 'category' => 'url', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'url_parameters', 'category' => 'url', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'url_broken_bookmark', 'category' => 'url', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'url_ga_tracking_params', 'category' => 'url', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'url_over_115_chars', 'category' => 'url', 'severity' => 'notice', 'status' => self::P],

            // page_title
            ['code' => 'missing_title', 'category' => 'page_title', 'severity' => 'error', 'status' => self::A],
            ['code' => 'duplicate_title', 'category' => 'page_title', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'title_too_long', 'category' => 'page_title', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'title_below_200px', 'category' => 'page_title', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'title_below_30_chars', 'category' => 'page_title', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'title_over_561px', 'category' => 'page_title', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'title_same_as_h1', 'category' => 'page_title', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'multiple_title', 'category' => 'page_title', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'title_outside_head', 'category' => 'page_title', 'severity' => 'warning', 'status' => self::P],

            // meta_description
            ['code' => 'missing_meta_description', 'category' => 'meta_description', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'duplicate_meta_description', 'category' => 'meta_description', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'meta_description_over_155_chars', 'category' => 'meta_description', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'meta_description_over_985px', 'category' => 'meta_description', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'meta_description_below_70_chars', 'category' => 'meta_description', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'meta_description_below_400px', 'category' => 'meta_description', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'multiple_meta_description', 'category' => 'meta_description', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'meta_description_outside_head', 'category' => 'meta_description', 'severity' => 'warning', 'status' => self::P],

            // meta_keywords (all planned)
            ['code' => 'missing_meta_keywords', 'category' => 'meta_keywords', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'duplicate_meta_keywords', 'category' => 'meta_keywords', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'multiple_meta_keywords', 'category' => 'meta_keywords', 'severity' => 'notice', 'status' => self::P],

            // h1
            ['code' => 'missing_h1', 'category' => 'h1', 'severity' => 'error', 'status' => self::A],
            ['code' => 'multiple_h1', 'category' => 'h1', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'heading_order_skip', 'category' => 'h1', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'duplicate_h1', 'category' => 'h1', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'h1_over_70_chars', 'category' => 'h1', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'alt_text_in_h1', 'category' => 'h1', 'severity' => 'notice', 'status' => self::P],

            // h2 (all planned)
            ['code' => 'missing_h2', 'category' => 'h2', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'duplicate_h2', 'category' => 'h2', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'h2_over_70_chars', 'category' => 'h2', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'multiple_h2', 'category' => 'h2', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'h2_non_sequential', 'category' => 'h2', 'severity' => 'warning', 'status' => self::P],

            // content (thin_content active; Tier-C permanently planned)
            ['code' => 'thin_content', 'category' => 'content', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'exact_duplicates', 'category' => 'content', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'lorem_ipsum', 'category' => 'content', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'readability_hard', 'category' => 'content', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'readability_very_hard', 'category' => 'content', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'near_duplicates', 'category' => 'content', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'semantically_similar', 'category' => 'content', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'low_relevance', 'category' => 'content', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'soft_404', 'category' => 'content', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'spelling_errors', 'category' => 'content', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'grammar_errors', 'category' => 'content', 'severity' => 'notice', 'status' => self::P],

            // images
            ['code' => 'missing_alt_text', 'category' => 'images', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'image_over_100kb', 'category' => 'images', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'image_missing_size_attributes', 'category' => 'images', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'image_missing_alt_attribute', 'category' => 'images', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'image_alt_over_100_chars', 'category' => 'images', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'background_images', 'category' => 'images', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'incorrectly_sized_images', 'category' => 'images', 'severity' => 'notice', 'status' => self::P],

            // canonicals
            ['code' => 'canonical_mismatch', 'category' => 'canonicals', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'has_canonical', 'category' => 'canonicals', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'canonical_self_referencing', 'category' => 'canonicals', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'missing_canonical', 'category' => 'canonicals', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'multiple_canonical', 'category' => 'canonicals', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'multiple_conflicting_canonical', 'category' => 'canonicals', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'non_indexable_canonical', 'category' => 'canonicals', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'canonical_is_relative', 'category' => 'canonicals', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'canonical_not_linked', 'category' => 'canonicals', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'canonical_invalid_attribute', 'category' => 'canonicals', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'canonical_fragment_url', 'category' => 'canonicals', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'canonical_outside_head', 'category' => 'canonicals', 'severity' => 'warning', 'status' => self::P],

            // pagination (all planned)
            ['code' => 'has_pagination', 'category' => 'pagination', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'pagination_first_page', 'category' => 'pagination', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'paginated_2plus', 'category' => 'pagination', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'pagination_url_not_in_anchor', 'category' => 'pagination', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'pagination_non_200', 'category' => 'pagination', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'pagination_unlinked', 'category' => 'pagination', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'pagination_non_indexable', 'category' => 'pagination', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'multiple_pagination_urls', 'category' => 'pagination', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'pagination_loop', 'category' => 'pagination', 'severity' => 'error', 'status' => self::P],
            ['code' => 'pagination_sequence_error', 'category' => 'pagination', 'severity' => 'warning', 'status' => self::P],

            // links
            ['code' => 'orphan_page', 'category' => 'links', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'broken_link', 'category' => 'links', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'pages_non_crawlable_internal_outlinks', 'category' => 'links', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'pages_high_crawl_depth', 'category' => 'links', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'pages_no_internal_outlinks', 'category' => 'links', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'internal_nofollow_outlinks', 'category' => 'links', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'internal_outlinks_no_anchor', 'category' => 'links', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'non_descriptive_anchor_internal_outlinks', 'category' => 'links', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'pages_many_external_outlinks', 'category' => 'links', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'pages_many_internal_outlinks', 'category' => 'links', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'follow_nofollow_internal_inlinks', 'category' => 'links', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'only_internal_nofollow_inlinks', 'category' => 'links', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'outlinks_to_localhost', 'category' => 'links', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'only_non_indexable_inlinks', 'category' => 'links', 'severity' => 'warning', 'status' => self::P],

            // other (existing checks outside the 13 user categories)
            ['code' => 'noindex', 'category' => 'other', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'missing_structured_data', 'category' => 'other', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'not_in_sitemap', 'category' => 'other', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'large_resource', 'category' => 'other', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'oversized_resource', 'category' => 'other', 'severity' => 'error', 'status' => self::A],
        ];
    }

    /** @return string[] */
    public static function activeCodes(): array
    {
        return array_values(array_map(
            fn ($e) => $e['code'],
            array_filter(self::all(), fn ($e) => $e['status'] === self::A),
        ));
    }

    /** @return string[] */
    public static function activeCodesForCategory(string $category): array
    {
        return array_values(array_map(
            fn ($e) => $e['code'],
            array_filter(self::all(), fn ($e) => $e['status'] === self::A && $e['category'] === $category),
        ));
    }

    public static function categoryOf(string $code): ?string
    {
        foreach (self::all() as $entry) {
            if ($entry['code'] === $code) {
                return $entry['category'];
            }
        }

        return null;
    }
}
```

- [ ] **Step 5: Add `category()` to IssueCode**

In `app/Crawler/IssueCode.php`, add `use`-free (same namespace) method after `severity()`:

```php
    public function category(): IssueCategory
    {
        return match ($this) {
            self::ClientError, self::ServerError, self::RedirectChain, self::RobotsBlocked => IssueCategory::ResponseCodes,
            self::MissingTitle, self::DuplicateTitle, self::TitleTooLong => IssueCategory::PageTitle,
            self::MissingMetaDescription, self::DuplicateMetaDescription => IssueCategory::MetaDescription,
            self::MissingH1, self::MultipleH1, self::HeadingOrderSkip => IssueCategory::H1,
            self::ThinContent => IssueCategory::Content,
            self::MissingAltText => IssueCategory::Images,
            self::CanonicalMismatch => IssueCategory::Canonicals,
            self::OrphanPage, self::BrokenLink => IssueCategory::Links,
            self::Noindex, self::MissingStructuredData, self::NotInSitemap,
            self::LargeResource, self::OversizedResource => IssueCategory::Other,
        };
    }
```

- [ ] **Step 6: Run test to verify it passes**

Run: `ddev php artisan test --filter=CheckCatalogTest`
Expected: PASS.

- [ ] **Step 7: Pint + commit**

```bash
ddev composer exec pint -- app/Crawler/IssueCategory.php app/Crawler/CheckCatalog.php app/Crawler/IssueCode.php tests/Unit/Crawler/CheckCatalogTest.php
git add app/Crawler/IssueCategory.php app/Crawler/CheckCatalog.php app/Crawler/IssueCode.php tests/Unit/Crawler/CheckCatalogTest.php
git commit -m "feat(crawler): add issue category model + check catalogue"
```

---

## Task 2: Show controller — category/issue filters + catalogue payload

**Files:**
- Modify: `app/Http/Controllers/CrawlController.php` (`show()`)
- Test: `tests/Feature/Crawler/CrawlControllerTest.php`

**Interfaces:**
- Consumes: `CheckCatalog`, `IssueCategory` (Task 1).
- Produces: `show()` additionally accepts `?group=<category>` and `?issue=<code>` and passes `catalog` (grouped), `filters.group`, `filters.issue`. Existing `?category=` (content type) and pagination stay.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Crawler/CrawlControllerTest.php` (the file already imports `Crawl`, `CrawlPage`, `Project`, `User`, `AssertableInertia`):

```php
    public function test_show_groups_catalogue_and_filters_by_category_and_issue(): void
    {
        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create();
        $a = CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/a', 'issues' => ['missing_title']]);
        $b = CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/b', 'issues' => ['broken_link']]);
        $c = CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/c', 'issues' => []]);

        // Catalogue is exposed, grouped, with per-category counts.
        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Crawls/Show')
                ->has('catalog')
                ->where('catalog.page_title.count', 1)
                ->where('catalog.security.count', 0)
                ->has('pages.data', 3) // unfiltered = all URLs
            );

        // Filter by category → only pages with an active issue in it.
        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'group' => 'page_title']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.group', 'page_title')
                ->has('pages.data', 1)
                ->where('pages.data.0.url', 'https://example.com/a')
            );

        // Filter by a single issue.
        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'group' => 'links', 'issue' => 'broken_link']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.issue', 'broken_link')
                ->has('pages.data', 1)
                ->where('pages.data.0.url', 'https://example.com/b')
            );

        // A category with only planned checks yields no rows.
        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'group' => 'security']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('pages.data', 0));
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=test_show_groups_catalogue_and_filters_by_category_and_issue`
Expected: FAIL (no `catalog` prop / filters not applied).

- [ ] **Step 3: Implement the controller changes**

In `app/Http/Controllers/CrawlController.php`, add imports at the top (with the other `use App\Crawler\…;` lines):

```php
use App\Crawler\CheckCatalog;
use App\Crawler\IssueCategory;
```

Replace the body of `show()` (from reading the query params through the `inertia('Crawls/Show', …)` return) with:

```php
        $category = $request->query('category');   // content type (existing)
        $group = $request->query('group');         // issue category (new)
        $issue = $request->query('issue');         // single check (existing/new)

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
                if ($cat !== null) {
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
            'filters' => [
                'category' => is_string($category) && $category !== '' ? $category : null,
                'group' => is_string($group) && $group !== '' ? $group : null,
                'issue' => is_string($issue) && $issue !== '' ? $issue : null,
            ],
        ]);
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `ddev php artisan test --filter=CrawlControllerTest`
Expected: PASS (existing controller tests + the new one).

- [ ] **Step 5: Pint + commit**

```bash
ddev composer exec pint -- app/Http/Controllers/CrawlController.php tests/Feature/Crawler/CrawlControllerTest.php
git add app/Http/Controllers/CrawlController.php tests/Feature/Crawler/CrawlControllerTest.php
git commit -m "feat(crawler): expose check catalogue + category/issue filters on the crawl overview"
```

---

## Task 3: Lang strings — category labels + all catalogue check labels

**Files:**
- Modify: `lang/en/crawler.php`, `lang/de/crawler.php`

**Interfaces:**
- Produces: `crawler.category_group.<key>` for all 14 categories, and `crawler.issue.<code>` for every catalogue code that does not already have one (all planned codes). Consumed by the frontend in Task 4.

- [ ] **Step 1: Add category-group labels**

In `lang/en/crawler.php`, add (near the other top-level keys):

```php
    'category_group' => [
        'security' => 'Security',
        'response_codes' => 'Response codes',
        'url' => 'URL',
        'page_title' => 'Page titles',
        'meta_description' => 'Meta description',
        'meta_keywords' => 'Meta keywords',
        'h1' => 'H1',
        'h2' => 'H2',
        'content' => 'Content',
        'images' => 'Images',
        'canonicals' => 'Canonicals',
        'pagination' => 'Pagination',
        'links' => 'Links',
        'other' => 'Other',
    ],
    'all_urls' => 'All URLs',
    'planned' => 'planned',
    'category_planned_note' => 'These checks are coming in a later release.',
```

In `lang/de/crawler.php`, add:

```php
    'category_group' => [
        'security' => 'Sicherheit',
        'response_codes' => 'Antwort-Codes',
        'url' => 'URL',
        'page_title' => 'Seitentitel',
        'meta_description' => 'Meta Description',
        'meta_keywords' => 'Meta Keywords',
        'h1' => 'H1',
        'h2' => 'H2',
        'content' => 'Inhalt',
        'images' => 'Bilder',
        'canonicals' => 'Canonicals',
        'pagination' => 'Paginierung',
        'links' => 'Links',
        'other' => 'Weitere',
    ],
    'all_urls' => 'Alle URLs',
    'planned' => 'geplant',
    'category_planned_note' => 'Diese Checks kommen in einem späteren Release.',
```

- [ ] **Step 2: Add labels for the planned check codes**

Every catalogue code needs `crawler.issue.<code>`. The active codes already have entries. Add the following to the `issue` array in BOTH `lang/en/crawler.php` and `lang/de/crawler.php` (English shown; use the German column for the de file). Add exactly these keys (do not remove existing ones):

| code | en | de |
|---|---|---|
| missing_csp_header | Missing Content-Security-Policy header | Fehlender Content-Security-Policy-Header |
| missing_x_frame_options | Missing X-Frame-Options header | Fehlender X-Frame-Options-Header |
| missing_x_content_type_options | Missing X-Content-Type-Options header | Fehlender X-Content-Type-Options-Header |
| missing_hsts_header | Missing HSTS header | Fehlender HSTS-Header |
| unsafe_cross_origin_links | Unsafe cross-origin links | Unsichere Cross-Origin-Links |
| missing_referrer_policy | Missing Referrer-Policy header | Fehlender Secure-Referrer-Policy-Header |
| http_urls | HTTP URLs | HTTP-URLs |
| https_urls | HTTPS URLs | HTTPS-URLs |
| mixed_content | Mixed content | Gemischter Inhalt |
| form_url_insecure | Form URL insecure | Formular-URL unsicher |
| form_on_http | Form on HTTP URL | Formular auf HTTP-URL |
| protocol_relative_resource_links | Protocol-relative resource links | Protokollrelative Ressourcen-Links |
| wrong_content_type | Wrong content type | Falscher Inhaltstyp |
| external_server_error_5xx | External server error (5xx) | Externer Serverfehler (5xx) |
| internal_redirect_3xx | Internal redirect (3xx) | Interne Umleitung (3xx) |
| internal_redirect_loop | Internal redirect loop | Interne Umleitungsschleife |
| internal_http_refresh_redirect | Internal redirect (HTTP refresh) | Interne Weiterleitung (HTTP-Aktualisierung) |
| internal_meta_refresh_redirect | Internal redirect (meta refresh) | Interne Umleitung (Meta Refresh) |
| internal_js_redirect | Internal redirect (JavaScript) | Interne Umleitung (JavaScript) |
| internal_success_2xx | Internal success (2xx) | Interner Erfolg (2xx) |
| internal_no_response | Internal no response | Intern keine Antwort |
| internal_blocked_resource | Internal blocked resource | Interne blockierte Ressource |
| url_non_ascii | Non-ASCII characters | Nicht-ASCII-Zeichen |
| url_underscores | Underscores | Unterstriche |
| url_uppercase | Uppercase | Großbuchstaben |
| url_multiple_slashes | Multiple slashes | Mehrere Slashes |
| url_repetitive_path | Repetitive path | Sich wiederholender Pfad |
| url_contains_space | Contains space | Enthält Leerzeichen |
| url_internal_search | Internal search | Interne Suche |
| url_parameters | Parameters | Parameter |
| url_broken_bookmark | Broken bookmark | Fehlerhaftes Lesezeichen |
| url_ga_tracking_params | GA tracking parameters | GA-Verfolgungsparameter |
| url_over_115_chars | Over 115 characters | Über 115 Zeichen |
| title_below_200px | Below 200 pixels | Unter 200 Pixel |
| title_below_30_chars | Below 30 characters | Unter 30 Zeichen |
| title_over_561px | Over 561 pixels | Über 561 Pixel |
| title_same_as_h1 | Same as H1 | Gleich wie H1 |
| multiple_title | Multiple | Mehrere |
| title_outside_head | Outside &lt;head&gt; | Außerhalb von &lt;head&gt; |
| meta_description_over_155_chars | Over 155 characters | Über 155 Zeichen |
| meta_description_over_985px | Over 985 pixels | Über 985 Pixel |
| meta_description_below_70_chars | Below 70 characters | Unter 70 Zeichen |
| meta_description_below_400px | Below 400 pixels | Unter 400 Pixel |
| multiple_meta_description | Multiple | Mehrere |
| meta_description_outside_head | Outside &lt;head&gt; | Außerhalb von &lt;head&gt; |
| missing_meta_keywords | Missing meta keywords | Fehlende Meta Keywords |
| duplicate_meta_keywords | Duplicate meta keywords | Doppelte Meta Keywords |
| multiple_meta_keywords | Multiple meta keywords | Mehrere Meta Keywords |
| duplicate_h1 | Duplicate | Duplikate |
| h1_over_70_chars | Over 70 characters | Über 70 Zeichen |
| alt_text_in_h1 | Alt text in H1 | Alt-Text in H1 |
| missing_h2 | Missing | Fehlende |
| duplicate_h2 | Duplicate | Duplikate |
| h2_over_70_chars | Over 70 characters | Über 70 Zeichen |
| multiple_h2 | Multiple | Mehrere |
| h2_non_sequential | Non-sequential | Nicht sequenziell |
| exact_duplicates | Exact duplicates | Exakte Duplikate |
| lorem_ipsum | Lorem ipsum placeholder | Lorem Ipsum-Platzhalter |
| readability_hard | Readability difficult | Lesbarkeit schwierig |
| readability_very_hard | Very difficult to read | Sehr schwierig lesbar |
| near_duplicates | Near duplicates | Nahduplikate |
| semantically_similar | Semantically similar | Semantisch ähnlich |
| low_relevance | Low relevance content | Inhalte mit geringer Relevanz |
| soft_404 | Soft 404 pages | Soft-404-Seiten |
| spelling_errors | Spelling errors | Rechtschreibfehler |
| grammar_errors | Grammar errors | Grammatikfehler |
| image_over_100kb | Over 100 KB | Über 100 kB |
| image_missing_size_attributes | Missing size attributes | Fehlende Größenattribute |
| image_missing_alt_attribute | Missing alt attribute | Fehlendes Alt-Attribut |
| image_alt_over_100_chars | Alt text over 100 characters | Alt-Text über 100 Zeichen |
| background_images | Background images | Hintergrundbilder |
| incorrectly_sized_images | Incorrectly sized images | Bilder mit falscher Größe |
| has_canonical | Contains canonical | Enthält Canonical |
| canonical_self_referencing | Self-referencing | Selbstreferenzierung |
| missing_canonical | Missing | Fehlende |
| multiple_canonical | Multiple | Mehrere |
| multiple_conflicting_canonical | Multiple conflicting | Mehrere widersprüchlich |
| non_indexable_canonical | Non-indexable canonical | Nicht indexierbarer Canonical |
| canonical_is_relative | Canonical is relative | Kanonisch ist relativ |
| canonical_not_linked | Not linked | Nicht verknüpft |
| canonical_invalid_attribute | Invalid attribute in annotation | Ungültiges Attribut in der Annotation |
| canonical_fragment_url | Contains fragment URL | Enthält Fragment-URL |
| canonical_outside_head | Outside &lt;head&gt; | Außerhalb von &lt;head&gt; |
| has_pagination | Contains pagination | Enthält Paginierung |
| pagination_first_page | First page | Erste Seite |
| paginated_2plus | 2+ pages paginated | 2 oder mehr Seiten paginiert |
| pagination_url_not_in_anchor | Pagination URL not in anchor | Paginierungs-URL nicht im Anchor-Tag |
| pagination_non_200 | Pagination URLs not 200 | Paginierungs-URLs ohne Status-Code 200 |
| pagination_unlinked | Unlinked pagination URLs | Nicht verlinkte Paginierungs-URLs |
| pagination_non_indexable | Non-indexable | Nicht indexierbar |
| multiple_pagination_urls | Multiple pagination URLs | Mehrere Paginierungs-URLs |
| pagination_loop | Pagination loop | Paginierungsschleife |
| pagination_sequence_error | Sequence error | Sequenzfehler |
| pages_non_crawlable_internal_outlinks | Non-crawlable internal outlinks | Nicht crawlbare interne ausgehende Links |
| pages_high_crawl_depth | High crawl depth | Viel Crawltiefe |
| pages_no_internal_outlinks | No internal outlinks | Keine internen Outlinks |
| internal_nofollow_outlinks | Internal nofollow outlinks | Interne Nofollow-Outlinks |
| internal_outlinks_no_anchor | Internal outlinks without anchor text | Interne Outlinks ohne Ankertext |
| non_descriptive_anchor_internal_outlinks | Non-descriptive internal anchor text | Nicht beschreibender Ankertext (intern) |
| pages_many_external_outlinks | Many external outlinks | Viele externe Outlinks |
| pages_many_internal_outlinks | Many internal outlinks | Viele interne Outlinks |
| follow_nofollow_internal_inlinks | Follow &amp; nofollow internal inlinks | Follow &amp; Nofollow interne Inlinks |
| only_internal_nofollow_inlinks | Only internal nofollow inlinks | Nur interne Nofollow-Inlinks |
| outlinks_to_localhost | Outlinks to localhost | Outlinks zu Localhost |
| only_non_indexable_inlinks | Only non-indexable inlinks | Nur nicht indexierbare Inlinks |

- [ ] **Step 3: Sanity-check the lang files parse**

Run: `ddev php -r "require 'lang/en/crawler.php'; require 'lang/de/crawler.php'; echo 'ok';"`
Expected: prints `ok` (no parse error).

- [ ] **Step 4: Pint + commit**

```bash
ddev composer exec pint -- lang/en/crawler.php lang/de/crawler.php
git add lang/en/crawler.php lang/de/crawler.php
git commit -m "feat(crawler): add category + planned-check labels for the catalogue"
```

---

## Task 4: Show page — category tabs + per-category check dropdown + filtered table

**Files:**
- Modify: `resources/js/Pages/Crawls/Show.tsx` (rebuild the body around tabs)
- Test: `ddev npm run build` (tsc typecheck is the gate; no unit test for the view)

**Interfaces:**
- Consumes: the `catalog`, `filters` ({category, group, issue}), `pages`, `categories`, `crawl` props from Task 2; lang keys from Task 3.

- [ ] **Step 1: Rebuild Show.tsx**

Replace the whole file `resources/js/Pages/Crawls/Show.tsx` with:

```tsx
import AppLayout from '@/Layouts/AppLayout';
import { Badge, BackLink, Card, Flash, LinkButton, PageHeader, Pagination, Select, StatusPill, TableCard } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { formatBytes } from '@/lib/formatBytes';
import { durationBetween, formatDuration } from '@/lib/formatDuration';
import { CrawlContentCategory, CrawlPageRow, CrawlSummary, PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';

type CrawlStatus = 'queued' | 'running' | 'completed' | 'failed';

interface CrawlDetail {
  id: string;
  start_url: string;
  mode: string;
  status: CrawlStatus;
  pages_crawled: number;
  summary: CrawlSummary;
  error: string | null;
  started_at: string | null;
  finished_at: string | null;
}

interface Paginated<T> {
  data: T[];
  links: { url: string | null; label: string; active: boolean }[];
}

interface CatalogCheck {
  code: string;
  severity: 'error' | 'warning' | 'notice';
  status: 'active' | 'planned';
  count: number;
}
type Catalog = Record<string, { count: number; checks: CatalogCheck[] }>;
type Filters = { category: string | null; group: string | null; issue: string | null };

const statusVariant: Record<CrawlStatus, 'neutral' | 'success' | 'warning' | 'danger'> = {
  queued: 'neutral',
  running: 'warning',
  completed: 'success',
  failed: 'danger',
};

// Fixed tab order matching the catalogue's category order.
const CATEGORY_ORDER = [
  'security',
  'response_codes',
  'url',
  'page_title',
  'meta_description',
  'meta_keywords',
  'h1',
  'h2',
  'content',
  'images',
  'canonicals',
  'pagination',
  'links',
  'other',
];

export default function CrawlsShow({
  crawl,
  pages,
  categories,
  catalog,
  filters,
}: {
  crawl: CrawlDetail;
  pages: Paginated<CrawlPageRow>;
  categories: CrawlContentCategory[];
  catalog: Catalog;
  filters: Filters;
}) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  useEffect(() => {
    if (crawl.status !== 'running' && crawl.status !== 'queued') return;
    const id = setInterval(() => router.reload({ only: ['crawl', 'pages', 'catalog'] }), 3000);
    return () => clearInterval(id);
  }, [crawl.status]);

  const activeTab = filters.group ?? (filters.category ? 'all' : 'overview');

  function go(params: Record<string, string | null>) {
    const clean: Record<string, string> = {};
    for (const [k, v] of Object.entries(params)) if (v) clean[k] = v;
    router.get(route('app.project.crawls.show', { project: project!.id, crawl: crawl.id }), clean, {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    });
  }

  function pagesTable() {
    return (
      <>
        <TableCard
          columns={[
            { label: t('crawler.col_url') },
            { label: t('crawler.col_status') },
            { label: t('crawler.col_type') },
            { label: t('crawler.col_issues') },
          ]}
        >
          <tbody className="divide-y divide-line">
            {pages.data.map((p) => (
              <tr key={p.id} className="hover:bg-elevated">
                <td className="px-4 py-3">
                  <Link
                    href={route('app.project.crawls.pages.show', { project: project!.id, crawl: crawl.id, page: p.id })}
                    className="font-medium text-foreground hover:text-accent-soft-foreground"
                  >
                    {p.url}
                  </Link>
                </td>
                <td className="px-4 py-3 text-muted">{p.status_code ?? '—'}</td>
                <td className="px-4 py-3">
                  {p.content_category ? <Badge>{t(`crawler.category.${p.content_category}`)}</Badge> : <span className="text-muted">—</span>}
                </td>
                <td className="px-4 py-3 text-muted">{p.issues.length}</td>
              </tr>
            ))}
          </tbody>
        </TableCard>
        <div className="mt-2">
          <Pagination links={pages.links} />
        </div>
      </>
    );
  }

  const summaryEntries = Object.entries(crawl.summary);

  return (
    <AppLayout title={crawl.start_url}>
      <div className="px-8 py-8">
        <div className="mb-6">
          <BackLink href={route('app.project.crawls.index', { project: project!.id })}>{t('crawler.title')}</BackLink>
        </div>

        <PageHeader
          title={crawl.start_url}
          subtitle={
            <span className="inline-flex flex-wrap items-center gap-2">
              <StatusPill status={statusVariant[crawl.status]}>{t(`crawler.status_${crawl.status}`)}</StatusPill>
              <span>
                {crawl.pages_crawled} {t('crawler.pages')}
              </span>
              {crawl.finished_at && (
                <span className="text-muted">
                  · {t('crawler.duration')}: {formatDuration(durationBetween(crawl.started_at, crawl.finished_at))}
                </span>
              )}
            </span>
          }
          action={
            <LinkButton variant="secondary" href={route('app.project.crawls.export', { project: project!.id, crawl: crawl.id })}>
              {t('crawler.export_csv')}
            </LinkButton>
          }
        />
        <Flash />

        {crawl.error && (
          <Card className="mb-6 border-[color:color-mix(in_srgb,var(--danger-foreground)_35%,transparent)] bg-danger-soft p-4 text-sm text-danger-foreground">
            {crawl.error}
          </Card>
        )}

        {/* Tab bar: Overview · All URLs · categories */}
        <div className="mb-4 flex flex-wrap gap-1 border-b border-line" role="tablist">
          <TabButton active={activeTab === 'overview'} onClick={() => go({})} label={t('crawler.tab_overview')} />
          <TabButton active={activeTab === 'all'} onClick={() => go({ category: filters.category })} label={t('crawler.all_urls')} />
          {CATEGORY_ORDER.map((key) => (
            <TabButton
              key={key}
              active={activeTab === key}
              onClick={() => go({ group: key })}
              label={t(`crawler.category_group.${key}`)}
              count={catalog[key]?.count ?? 0}
            />
          ))}
        </div>

        {activeTab === 'overview' && (
          <>
            {summaryEntries.length > 0 && (
              <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                {summaryEntries.map(([code, count]) => (
                  <Card key={code} className="p-4">
                    <div className="text-2xl font-semibold text-foreground">{count}</div>
                    <div className="text-xs text-muted">{t(`crawler.issue.${code}`)}</div>
                  </Card>
                ))}
              </div>
            )}
            {pagesTable()}
          </>
        )}

        {activeTab === 'all' && (
          <>
            {categories.length > 1 && (
              <div className="mb-3 flex items-center gap-2">
                <span className="text-sm text-muted">{t('crawler.filter_type')}</span>
                <Select value={filters.category ?? ''} onChange={(e) => go({ category: e.target.value || null })} className="w-48">
                  <option value="">{t('crawler.filter_all')}</option>
                  {categories.map((c) => (
                    <option key={c} value={c}>
                      {t(`crawler.category.${c}`)}
                    </option>
                  ))}
                </Select>
              </div>
            )}
            {pagesTable()}
          </>
        )}

        {CATEGORY_ORDER.includes(activeTab) && (
          <>
            <div className="mb-3 flex items-center gap-2">
              <span className="text-sm text-muted">{t('crawler.filter_issue')}</span>
              <Select value={filters.issue ?? ''} onChange={(e) => go({ group: activeTab, issue: e.target.value || null })} className="w-72">
                <option value="">{t('crawler.filter_all')}</option>
                {(catalog[activeTab]?.checks ?? []).map((check) => (
                  <option key={check.code} value={check.code} disabled={check.status === 'planned'}>
                    {t(`crawler.issue.${check.code}`)}
                    {check.status === 'planned' ? ` (${t('crawler.planned')})` : ` (${check.count})`}
                  </option>
                ))}
              </Select>
            </div>
            {(catalog[activeTab]?.count ?? 0) === 0 && !filters.issue ? (
              <Card className="p-6 text-sm text-muted">{t('crawler.category_planned_note')}</Card>
            ) : (
              pagesTable()
            )}
          </>
        )}
      </div>
    </AppLayout>
  );
}

function TabButton({ active, onClick, label, count }: { active: boolean; onClick: () => void; label: string; count?: number }) {
  return (
    <button
      type="button"
      role="tab"
      aria-selected={active}
      onClick={onClick}
      className={`-mb-px inline-flex items-center gap-1.5 border-b-2 px-4 py-2 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] ${
        active ? 'border-accent text-foreground' : 'border-transparent text-muted hover:text-foreground'
      } ${count === 0 ? 'opacity-60' : ''}`}
    >
      {label}
      {count !== undefined && count > 0 && <span className="rounded-full bg-neutral-soft px-1.5 text-xs text-neutral-foreground">{count}</span>}
    </button>
  );
}
```

- [ ] **Step 2: Build**

Run: `ddev npm run build`
Expected: tsc + vite succeed with no errors. If a UI-kit prop mismatch appears, open the referenced component in `resources/js/Components/ui/` and adapt (the kit exports: Badge, StatusPill, Card, PageHeader, Flash, TableCard, LinkButton, Pagination, Select, BackLink).

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Crawls/Show.tsx
git commit -m "feat(crawler): category tabs + per-category check filter on the crawl overview"
```

---

## Task 5: Verification

- [ ] **Step 1: Full crawler suite**

Run: `ddev php artisan test --filter="ResourceClassifier|Crawler"`
Expected: all green.

- [ ] **Step 2: Full suite + Pint + build**

Run: `ddev php artisan test` (all green), `ddev composer exec pint -- --test` (clean), `ddev npm run build` (success).

- [ ] **Step 3: Manual smoke**

Open an existing completed crawl's overview: the category tabs render with counts; the "All URLs" tab lists every page; a category tab's dropdown lists its checks (planned ones greyed) and filtering narrows the URL table.

---

## Self-Review (author checklist — completed)

- **Spec coverage:** catalogue + categories (Task 1); Show controller group/issue filters + catalogue payload + counts + All-URLs (Task 2); category-group + planned-check labels (Task 3); Show tabs + dropdown + table + greyed planned (Task 4); verification (Task 5). Page-detail untouched; no new crawl-time checks — matches "Phase 0 framework only".
- **Placeholder scan:** none — every step has concrete code or an explicit lang table.
- **Type consistency:** `CheckCatalog::activeCodes/activeCodesForCategory/categoryOf/all`, `IssueCode::category(): IssueCategory`, controller `catalog[category] = {count, checks:[{code,severity,status,count}]}`, `filters {category,group,issue}` — used identically in the controller and the React `Catalog`/`Filters` types.

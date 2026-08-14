# Design: Categorised issue checks + category tabs on the crawl overview (v3)

**Date:** 2026-08-14
**Branch:** `v3`
**Status:** Design approved (chat); spec under review

## Purpose

Expand the SEO crawler with a large catalogue of categorised checks (Screaming-Frog
style) and reorganise the crawl overview (`app.project.crawls.show`) so issues are
browsed **by category**. Each category is a tab listing the URLs affected by that
category's problems, with a dropdown to filter to a single check. Checks that are not
yet implemented (or need AI/NLP) are shown **greyed out ("planned")** so the full
catalogue is visible.

This is built in **phases**. This spec covers **Phase 0 — the framework**: the check
catalogue, the category model on `IssueCode`, and the Show-page restructure, wiring the
**already-existing** checks into their categories and greying out everything else.
Later phases implement the planned checks category by category, flipping each from
`planned` to `active`.

## Approved UI (Show page)

**Tabs:** `Overview` · `All URLs` · one tab per category (14 — the user's 13 plus the
`other` catch-all, labelled "Weitere"/"Other"). Category tabs show a count badge;
categories with zero affected URLs are dimmed.

- **Overview tab:** the current dashboard — issue summary cards + the existing
  content-type filter. (Unchanged behaviour.)
- **All URLs tab:** every crawled URL regardless of issues, paginated, with the
  existing content-type (`?category=`) filter.
- **Category tab:** a **dropdown** of that category's checks (`All` + each check;
  planned checks appear greyed/disabled) and a **URL table** of pages that have an
  active issue in the category — or, when a single check is selected, only pages with
  that check. Server-side filtered + paginated; row → page detail.

## Categories (14)

`security`, `response_codes`, `url`, `page_title`, `meta_description`, `meta_keywords`,
`h1`, `h2`, `content`, `images`, `canonicals`, `pagination`, `links`, and a catch-all
`other` for existing checks that fall outside the user's 13 (noindex, missing
structured data, not-in-sitemap). Category labels live in `lang/{en,de}/crawler.php`
under `crawler.category_group.<key>`.

## Check catalogue (source of truth for all phases)

A registry `App\Crawler\CheckCatalog` returns, for every check, an entry:
`{ code, category, severity, status }` where `status ∈ {active, planned}`. `active`
codes are real `IssueCode` cases that analysers emit today or in a later phase; a check
becomes `active` only when its analyser logic ships. `planned` codes are shown greyed
and are never emitted until implemented. Each code has a label
(`crawler.issue.<code>`) and, for checks with guidance, `crawler.issue_help.<code>`.

Severity: `error` (broken/critical), `warning` (should fix), `notice` (informational).
Status below is the **Phase-0** status; planned items flip to active in later phases.

### security  *(Phase 1 — needs response headers captured in the observer)*
| code | severity | status |
|---|---|---|
| missing_csp_header | notice | planned |
| missing_x_frame_options | warning | planned |
| missing_x_content_type_options | warning | planned |
| missing_hsts_header | warning | planned |
| unsafe_cross_origin_links | warning | planned |
| missing_referrer_policy | notice | planned |
| http_urls | warning | planned |
| https_urls | notice | planned |
| mixed_content | warning | planned |
| form_url_insecure | warning | planned |
| form_on_http | warning | planned |
| protocol_relative_resource_links | notice | planned |
| wrong_content_type | warning | planned |

### response_codes  *(Phase 3; some active today)*
| code | severity | status |
|---|---|---|
| robots_blocked *(existing)* | warning | active |
| client_error *(existing, internal 4xx)* | error | active |
| server_error *(existing, internal 5xx)* | error | active |
| redirect_chain *(existing, internal chain)* | warning | active |
| external_server_error_5xx | warning | planned |
| internal_redirect_3xx | notice | planned |
| internal_redirect_loop | error | planned |
| internal_http_refresh_redirect | warning | planned |
| internal_meta_refresh_redirect | warning | planned |
| internal_js_redirect | warning | planned |
| internal_success_2xx | notice | planned |
| internal_no_response | error | planned |
| internal_blocked_resource | warning | planned |

### url  *(Phase 2 — pure string checks)*
`url_non_ascii`, `url_underscores`, `url_uppercase`, `url_multiple_slashes`,
`url_repetitive_path`, `url_contains_space`, `url_internal_search`, `url_parameters`,
`url_broken_bookmark`, `url_ga_tracking_params`, `url_over_115_chars` — all `notice`,
`planned`.

### page_title  *(Phase 4)*
| code | severity | status |
|---|---|---|
| missing_title *(existing)* | error | active |
| duplicate_title *(existing)* | warning | active |
| title_over_60_chars → **title_too_long** *(existing)* | notice | active |
| title_below_200px | notice | planned |
| title_below_30_chars | notice | planned |
| title_over_561px | notice | planned |
| title_same_as_h1 | notice | planned |
| multiple_title | warning | planned |
| title_outside_head | warning | planned |

### meta_description  *(Phase 4)*
| code | severity | status |
|---|---|---|
| missing_meta_description *(existing)* | warning | active |
| duplicate_meta_description *(existing)* | warning | active |
| meta_description_over_155_chars | notice | planned |
| meta_description_over_985px | notice | planned |
| meta_description_below_70_chars | notice | planned |
| meta_description_below_400px | notice | planned |
| multiple_meta_description | warning | planned |
| meta_description_outside_head | warning | planned |

### meta_keywords  *(Phase 4)*
`missing_meta_keywords` (notice), `duplicate_meta_keywords` (notice),
`multiple_meta_keywords` (notice) — all `planned`.

### h1  *(Phase 5)*
| code | severity | status |
|---|---|---|
| missing_h1 *(existing)* | error | active |
| multiple_h1 *(existing)* | warning | active |
| heading_order_skip → **h1 non-sequential** *(existing)* | warning | active |
| duplicate_h1 | warning | planned |
| h1_over_70_chars | notice | planned |
| alt_text_in_h1 | notice | planned |

### h2  *(Phase 5)*
`missing_h2`, `duplicate_h2`, `h2_over_70_chars`, `multiple_h2`, `h2_non_sequential` —
`notice`/`warning`, all `planned`.

### content  *(Phase 8 for Tier-A; Tier-C stays planned indefinitely)*
| code | severity | status | tier |
|---|---|---|---|
| thin_content *(existing, "low content")* | notice | active | A |
| exact_duplicates | warning | planned | A |
| lorem_ipsum | warning | planned | A |
| readability_hard | notice | planned | A |
| readability_very_hard | notice | planned | A |
| near_duplicates | warning | planned | C |
| semantically_similar | notice | planned | C |
| low_relevance | notice | planned | C |
| soft_404 | warning | planned | C |
| spelling_errors | notice | planned | C |
| grammar_errors | notice | planned | C |

Tier-C checks are permanently greyed until a separate AI/NLP sub-project is decided.

### images  *(Phase 8)*
| code | severity | status |
|---|---|---|
| missing_alt_text *(existing)* | notice | active |
| image_over_100kb | notice | planned |
| image_missing_size_attributes | notice | planned |
| image_missing_alt_attribute | notice | planned |
| image_alt_over_100_chars | notice | planned |
| background_images | notice | planned |
| incorrectly_sized_images | notice | planned |

### canonicals  *(Phase 6)*
| code | severity | status |
|---|---|---|
| canonical_mismatch → **canonicalised** *(existing)* | warning | active |
| has_canonical | notice | planned |
| canonical_self_referencing | notice | planned |
| missing_canonical | notice | planned |
| multiple_canonical | warning | planned |
| multiple_conflicting_canonical | warning | planned |
| non_indexable_canonical | warning | planned |
| canonical_is_relative | notice | planned |
| canonical_not_linked | notice | planned |
| canonical_invalid_attribute | warning | planned |
| canonical_fragment_url | notice | planned |
| canonical_outside_head | warning | planned |

### pagination  *(Phase 6)*
`has_pagination`, `pagination_first_page`, `paginated_2plus`,
`pagination_url_not_in_anchor`, `pagination_non_200`, `pagination_unlinked`,
`pagination_non_indexable`, `multiple_pagination_urls`, `pagination_loop`,
`pagination_sequence_error` — `notice`/`warning`, all `planned`.

### links  *(Phase 7)*
| code | severity | status |
|---|---|---|
| orphan_page *(existing)* | warning | active |
| pages_non_crawlable_internal_outlinks | warning | planned |
| pages_high_crawl_depth | notice | planned |
| pages_no_internal_outlinks | warning | planned |
| internal_nofollow_outlinks | notice | planned |
| internal_outlinks_no_anchor | notice | planned |
| non_descriptive_anchor_internal_outlinks | notice | planned |
| pages_many_external_outlinks | notice | planned |
| pages_many_internal_outlinks | notice | planned |
| follow_nofollow_internal_inlinks | notice | planned |
| only_internal_nofollow_inlinks | warning | planned |
| outlinks_to_localhost | warning | planned |
| only_non_indexable_inlinks | warning | planned |

### other  *(existing checks outside the user's 13 categories)*
| code | severity | status |
|---|---|---|
| noindex *(existing)* | warning | active |
| missing_structured_data *(existing)* | notice | active |
| not_in_sitemap *(existing)* | notice | active |

## Components (Phase 0)

- **`App\Crawler\IssueCategory`** — a string enum of the 14 categories with `label()`.
- **`App\Crawler\CheckCatalog`** — the registry above as a static array; helpers:
  `all(): array` (entries), `forCategory(string): array`, `activeCodes(): string[]`,
  `categoryOf(string $code): ?string`. Single source of truth for tabs/dropdown/greying.
- **`IssueCode::category(): IssueCategory`** — maps each active code to its category
  (derived from / consistent with the catalogue). Existing `severity()` unchanged.
- **`CrawlController::show()`** — additionally passes:
  - `catalog` — the full catalogue grouped by category (code, label, severity, status).
  - `category_counts` — per category, the number of pages with ≥1 active issue in it.
  - handles `?group=<category>` (filter pages to those with any active code of the
    category) and `?issue=<code>` (single code) in addition to the existing
    `?category=` (content type). `group` + `issue` compose (issue must belong to group).
  - the **All URLs** tab needs no new query — it's the unfiltered paginated table.
- **`resources/js/Pages/Crawls/Show.tsx`** — rebuilt with the tab bar (Overview / All
  URLs / categories from the catalogue), the per-category dropdown (greyed planned
  entries via `<option disabled>`), and the filtered URL table. Reuses the existing
  pagination + table.

## Data flow (Phase 0)

No new crawl-time work: Phase 0 only reads the `issues` already stored per page. The
category filter expands to `WHERE (issues contains code1 OR code2 …)` over the
category's **active** codes (via `whereJsonContains` OR-group). Counts are computed the
same way. Nothing is emitted for `planned` codes, so their tabs show 0 / their dropdown
entries are greyed.

## Error handling

- Unknown `group`/`issue` query values → ignored (fall back to unfiltered).
- A category with only planned checks renders an empty table + the greyed dropdown and
  an explanatory "these checks are coming in a later release" note.

## Testing (Phase 0)

- `CheckCatalog`: every active code is a real `IssueCode`; every `IssueCode` case
  appears in the catalogue exactly once; every entry has a known category + severity +
  status.
- `IssueCode::category()` covers all cases (exhaustive, no `UnhandledMatchError`).
- Controller: `?group=security` (all planned → empty), `?group=page_title` returns
  pages with title issues, `?issue=missing_title` narrows further, `?group`+`?issue`
  compose, All-URLs tab returns every page. Tenant scoping unchanged.
- Frontend: `npm run build` (tsc) — the tab/dropdown render from the catalogue prop.

## Phasing (after Phase 0)

Each later phase implements one category's planned checks: add the `IssueCode` cases,
the analyser logic (+ any new crawl-time data such as response headers for `security`),
flip the catalogue entries to `active`, add lang strings, and add tests. Order:
1 security · 2 url · 3 response_codes · 4 title/description/keywords · 5 h1/h2 ·
6 canonicals/pagination · 7 links · 8 content(Tier-A)/images. Tier-C content checks
remain `planned` pending a separate AI/NLP decision.

## Out of scope

- The page **detail** view (its per-issue tabs, SERP, screenshots) is unchanged.
- Tier-C content checks (spelling, grammar, semantic/near-duplicate, low-relevance,
  soft-404) — catalogued as `planned`, greyed, not implemented.
- A pass/fail matrix (showing passed checks) — only failed checks + greyed planned.

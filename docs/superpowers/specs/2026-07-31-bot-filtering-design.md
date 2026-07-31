# Bot Filtering for Click Statistics — Design

**Date:** 2026-07-31
**Status:** Approved (pending spec review)

## Summary

Click statistics currently count every request, including crawlers and
link-preview bots (`facebookexternalhit`, `Slackbot`, `Twitterbot`, search
engines, …), which inflates totals and breakdowns. This adds bot detection at
click-recording time: each click is still stored, now tagged with an `is_bot`
flag, and bot rows are excluded from every statistic aggregation and from the
denormalized click counters. Bots are still redirected normally so link
previews keep working.

## Problem & Context

`RedirectController::dispatchStat` dispatches `RecordClickStatisticJob` for every
resolved redirect. The job (`app/Jobs/RecordClickStatisticJob.php`) creates a
`statistics` row and increments `urls.clicks` / `urls.unique_clicks`. There is
no bot detection anywhere, so automated traffic is counted as real clicks.

The requirement is a *clean* click statistic that still retains bot rows for
possible later inspection — so we flag rather than drop, and exclude the flagged
rows everywhere clicks are counted.

## Detection & Recording

### Dependency + wrapper
- Add Composer dependency `jaybizzle/crawler-detect` (the maintained,
  widely-used PHP crawler pattern set — a hand-rolled list would miss the long
  tail of bots).
- Add a thin wrapper `App\Support\CrawlerDetector` (mirrors the existing
  `App\Support\UserAgent` support helper) exposing:
  `public static function isBot(string $ua): bool` — delegates to
  `(new \Jaybizzle\CrawlerDetect\CrawlerDetect)->isCrawler($ua)`. An empty UA
  returns `false` (not a known bot).

### Schema
- Migration adds `statistics.is_bot` — boolean, `default(false)`, placed after
  an existing column (e.g. `os`). No dedicated index (the filter runs as a
  residual on the existing project/url/date composite indexes; YAGNI).
- `Statistic::$fillable` gains `is_bot`.
- `StatisticFactory`: default `'is_bot' => false`; add a `bot()` state that sets
  `is_bot => true`.

### Job change (`RecordClickStatisticJob::handle`)
- Compute `$isBot = CrawlerDetector::isBot($this->userAgent)`.
- Always create the `statistics` row, now including `'is_bot' => $isBot` (bot
  rows are retained).
- **Gate the counters:** only when `! $isBot` do we
  `Url::whereKey(...)->increment('clicks')` and (if unique)
  `increment('unique_clicks')`. Bots never touch the denormalized counters, so
  headline totals and breakdowns stay consistent (both bot-free).
- The existing same-day dedup logic that computes `$isUnique` is unchanged; it
  is only consulted inside the `! $isBot` branch.

### Redirect path
- Unchanged. Bots are still resolved and redirected — only the *recording* side
  distinguishes them.

## Aggregation Exclusion

Every place that reads `statistics` for counts must exclude `is_bot = true`.

- **`StatisticsAggregator::base()`** — add `->where('is_bot', false)`. This is
  the single choke point for `totalClicks`, `uniqueClicks`, `clicksByDay`,
  `clicksByDayBetween`, `breakdown`, `breakdownByCountryCode`, and
  `recentClicks`.
- **`StatisticsController::show` → `$topLinks`** (raw query joining
  `statistics`+`urls`+`domains`): add `->where('statistics.is_bot', false)`.
- **`DashboardController::clicksByDay`** (raw `statistics` query): add
  `->where('is_bot', false)`.
- **`ReportDataService`** (raw top-links `statistics` query, ~line 80, used by
  the PDF/email report): add `->where('statistics.is_bot', false)`. Its other
  figures come through `StatisticsAggregator` and are covered by `base()`.
- **Dashboard totals** (`$project->urls()->sum('clicks')` /
  `sum('unique_clicks')`): no change — they read the denormalized counters,
  which are already bot-free because the job skips incrementing them for bots.

The reset-stats path (`UrlController` `Statistic::where(...)->forceDelete()`) and
the prune command are unaffected (they operate on all rows regardless of flag).

## Testing

- **`CrawlerDetector`:** `isBot()` returns `true` for a Googlebot and a
  `facebookexternalhit` UA and `false` for a real Chrome desktop UA and for an
  empty string.
- **`RecordClickStatisticJob`:** a bot UA creates a row with `is_bot = true` and
  leaves `urls.clicks` / `urls.unique_clicks` at 0; a human UA creates a row with
  `is_bot = false` and increments `clicks` (and `unique_clicks` on first visit).
- **`StatisticsAggregator`:** with a mix of bot and human rows, `totalClicks`,
  `uniqueClicks`, `breakdown`, `breakdownByCountryCode`, and `recentClicks` count
  only the human rows.
- **Raw-query site:** one test that `StatisticsController`'s `topLinks` excludes
  bot clicks (representative of the three raw-query sites).
- **Regression:** the existing statistics suite stays green (factory default
  `is_bot = false` keeps all prior fixtures counted as human).
- Follow existing patterns (`RefreshDatabase`, factories, `route()` /
  explicit-host redirect requests).

## Out of Scope (YAGNI)

- Prefetch / speculative-browser requests (`Sec-Purpose: prefetch`,
  `Purpose: prefetch`): a separate signal from bot UAs; not addressed here.
- A dedicated `is_bot` index, a bot-analytics UI, or surfacing bot counts to
  users — the flag is retained purely for possible later inspection.
- Backfilling `is_bot` on historical rows (they remain `false` / counted).

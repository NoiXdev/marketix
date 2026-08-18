<?php

namespace App\Crawler;

use App\Models\Crawl;

class CrawlScore
{
    private const WEIGHTS = ['error' => 3, 'warning' => 2, 'notice' => 1];

    /**
     * Per-page penalty budget for a set spanning the whole catalogue (i.e. "overall").
     * Smaller sets get a proportionally scaled slice of this budget (see budget() below),
     * so a single-category set can still discriminate between healthy and unhealthy pages
     * instead of being swamped by the catalogue-wide worst case.
     */
    private const B_PAGE = 18;

    /**
     * @return array{overall:int, seo:int, geo:int, categories:array<string,int>,
     *   topActions:array<int,array{code:string,category:string,severity:string,count:int}>}
     */
    public static function for(Crawl $crawl): array
    {
        // Severity is sourced once from IssueCode (equal to the catalogue's severity by
        // invariant) and reused everywhere below, so every weight is computed on the same basis.
        $severityOf = fn (string $code): string => IssueCode::tryFrom($code)?->severity() ?? 'notice';

        // Per-category max weight from the catalogue: sum of weights of every active,
        // non-info (problem) check in that category. This defines how much of the overall
        // budget each category is entitled to.
        $catWeight = [];
        foreach (CheckCatalog::all() as $entry) {
            if ($entry['status'] !== 'active' || ! CheckCatalog::isProblemCode($entry['code'])) {
                continue;
            }
            $cat = $entry['category'];
            $catWeight[$cat] = ($catWeight[$cat] ?? 0) + (self::WEIGHTS[$severityOf($entry['code'])] ?? 1);
        }
        $totalWeight = array_sum($catWeight);

        // Per-set budget: B_PAGE scaled by that set's share of the total catalogue weight.
        $budget = function (callable $inSet) use ($catWeight, $totalWeight): float {
            if ($totalWeight === 0) {
                return 0.0;
            }
            $sum = 0;
            foreach ($catWeight as $cat => $w) {
                if ($inSet($cat)) {
                    $sum += $w;
                }
            }

            return self::B_PAGE * $sum / $totalWeight;
        };

        // Single pass over pages: per-page, per-category penalty (unique problem codes on
        // that page), plus the global per-code page counts used for topActions.
        $perCode = [];
        $pagePenalties = [];
        foreach ($crawl->pages()->pluck('issues') as $issues) {
            $catPenalty = [];
            foreach (array_unique($issues ?? []) as $code) {
                if (! CheckCatalog::isProblemCode($code)) {
                    continue;
                }
                $cat = CheckCatalog::categoryOf($code);
                if ($cat === null) {
                    continue;
                }
                $catPenalty[$cat] = ($catPenalty[$cat] ?? 0) + (self::WEIGHTS[$severityOf($code)] ?? 1);
                $perCode[$code] = ($perCode[$code] ?? 0) + 1;
            }
            $pagePenalties[] = $catPenalty;
        }

        // score(S) = round(100 x mean(health over all pages)), health = max(0, 1 - pen_S/budget(S)).
        $score = function (callable $inSet) use ($pagePenalties, $budget): int {
            if ($pagePenalties === []) {
                return 100;
            }

            $b = $budget($inSet);
            if ($b <= 0) {
                return 100;
            }

            $sumHealth = 0.0;
            foreach ($pagePenalties as $catPenalty) {
                $pen = 0;
                foreach ($catPenalty as $cat => $p) {
                    if ($inSet($cat)) {
                        $pen += $p;
                    }
                }
                $sumHealth += max(0.0, 1 - $pen / $b);
            }

            return (int) round(100 * $sumHealth / count($pagePenalties));
        };

        $overall = $score(fn (string $c) => true);
        $geo = $score(fn (string $c) => $c === 'geo');
        $seo = $score(fn (string $c) => $c !== 'geo');

        $categories = [];
        foreach (IssueCategory::cases() as $cat) {
            if (($catWeight[$cat->value] ?? 0) === 0) {
                continue;
            }
            $categories[$cat->value] = $score(fn (string $c) => $c === $cat->value);
        }

        $rank = self::WEIGHTS;
        $actions = [];
        foreach ($perCode as $code => $count) {
            $cat = CheckCatalog::categoryOf($code);
            if ($cat === null) {
                continue;
            }
            $sev = $severityOf($code);
            $actions[] = ['code' => $code, 'category' => $cat, 'severity' => $sev, 'count' => $count];
        }
        usort($actions, fn ($a, $b) => (($rank[$b['severity']] ?? 0) <=> ($rank[$a['severity']] ?? 0)) ?: ($b['count'] <=> $a['count']));
        $actions = array_slice($actions, 0, 10);

        return [
            'overall' => $overall,
            'seo' => $seo,
            'geo' => $geo,
            'categories' => $categories,
            'topActions' => $actions,
        ];
    }
}

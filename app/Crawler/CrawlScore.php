<?php

namespace App\Crawler;

use App\Models\Crawl;

class CrawlScore
{
    private const WEIGHTS = ['error' => 3, 'warning' => 2, 'notice' => 1];

    /**
     * @return array{overall:int, seo:int, geo:int, categories:array<string,int>,
     *   topActions:array<int,array{code:string,category:string,severity:string,count:int}>}
     */
    public static function for(Crawl $crawl): array
    {
        // Pages affected per problem code (unique per page), info excluded.
        $perCode = [];
        foreach ($crawl->pages()->pluck('issues') as $issues) {
            foreach (array_unique($issues ?? []) as $code) {
                if (CheckCatalog::isProblemCode($code)) {
                    $perCode[$code] = ($perCode[$code] ?? 0) + 1;
                }
            }
        }

        $pages = max(1, (int) $crawl->pages_crawled);

        // Severity is sourced once from IssueCode (equal to the catalogue's severity by
        // invariant) and reused for both the penalty and the maximum-possible-penalty side
        // of the ratio, so the two are always computed on the same basis.
        $severityOf = fn (string $code): string => IssueCode::tryFrom($code)?->severity() ?? 'notice';

        $penalty = function (callable $inSet) use ($perCode, $severityOf): int {
            $p = 0;
            foreach ($perCode as $code => $count) {
                $cat = CheckCatalog::categoryOf($code);
                if ($cat === null || ! $inSet($cat)) {
                    continue;
                }
                $p += $count * (self::WEIGHTS[$severityOf($code)] ?? 1);
            }

            return $p;
        };

        // Maximum possible weighted penalty for a set of categories: every active,
        // non-info (problem) check in those categories failing on every page.
        $maxWeight = function (callable $inSet) use ($severityOf): int {
            $w = 0;
            foreach (CheckCatalog::all() as $entry) {
                if ($entry['status'] !== 'active'
                    || ! CheckCatalog::isProblemCode($entry['code'])
                    || ! $inSet($entry['category'])
                ) {
                    continue;
                }
                $w += self::WEIGHTS[$severityOf($entry['code'])] ?? 1;
            }

            return $w;
        };

        $score = function (int $pen, int $maxW) use ($pages): int {
            if ($maxW === 0) {
                return 100;
            }

            return (int) round(100 * (1 - min(1, $pen / ($pages * $maxW))));
        };

        $overall = $score($penalty(fn (string $c) => true), $maxWeight(fn (string $c) => true));
        $geo = $score($penalty(fn (string $c) => $c === 'geo'), $maxWeight(fn (string $c) => $c === 'geo'));
        $seo = $score($penalty(fn (string $c) => $c !== 'geo'), $maxWeight(fn (string $c) => $c !== 'geo'));

        $categories = [];
        foreach (IssueCategory::cases() as $cat) {
            $inCat = fn (string $c) => $c === $cat->value;
            $maxW = $maxWeight($inCat);
            if ($maxW === 0) {
                continue;
            }
            $categories[$cat->value] = $score($penalty($inCat), $maxW);
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

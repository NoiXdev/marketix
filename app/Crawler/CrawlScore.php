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

        $penalty = function (callable $inSet) use ($perCode): int {
            $p = 0;
            foreach ($perCode as $code => $count) {
                $cat = CheckCatalog::categoryOf($code);
                if ($cat === null || ! $inSet($cat)) {
                    continue;
                }
                $sev = IssueCode::tryFrom($code)?->severity() ?? 'notice';
                $p += $count * (self::WEIGHTS[$sev] ?? 1);
            }

            return $p;
        };

        $score = fn (int $pen): int => (int) round(100 * (1 - min(1, $pen / ($pages * 3))));

        $overall = $score($penalty(fn (string $c) => true));
        $geo = $score($penalty(fn (string $c) => $c === 'geo'));
        $seo = $score($penalty(fn (string $c) => $c !== 'geo'));

        $categories = [];
        foreach (IssueCategory::cases() as $cat) {
            if (CheckCatalog::activeCodesForCategory($cat->value) === []) {
                continue;
            }
            $categories[$cat->value] = $score($penalty(fn (string $c) => $c === $cat->value));
        }

        $rank = self::WEIGHTS;
        $actions = [];
        foreach ($perCode as $code => $count) {
            $cat = CheckCatalog::categoryOf($code);
            if ($cat === null) {
                continue;
            }
            $sev = IssueCode::tryFrom($code)?->severity() ?? 'notice';
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

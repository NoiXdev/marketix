<?php

namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use Symfony\Component\DomCrawler\Crawler;

class CanonicalAnalyzer implements Analyzer
{
    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;

        $nodes = $dom->filter('link[rel="canonical"]');
        $count = $nodes->count();

        if ($count === 0) {
            $r->issue(IssueCode::MissingCanonical);

            return $r;
        }

        $r->issue(IssueCode::HasCanonical);

        $hrefs = [];
        $hasInvalid = false;
        $nodes->each(function (Crawler $n) use (&$hrefs, &$hasInvalid) {
            $href = $n->attr('href');
            if ($href === null || trim($href) === '') {
                $hasInvalid = true;
            } else {
                $hrefs[] = trim($href);
            }
        });

        if ($hasInvalid) {
            $r->issue(IssueCode::CanonicalInvalidAttribute);
        }

        if ($count > 1) {
            $r->issue(IssueCode::MultipleCanonical);
            $distinct = array_unique(array_map(fn ($h) => $this->normPath($h), $hrefs));
            if (count($distinct) >= 2) {
                $r->issue(IssueCode::MultipleConflictingCanonical);
            }
        }

        $first = $hrefs[0] ?? null;
        if ($first !== null) {
            if (! $this->differs($first, $ctx->url)) {
                $r->issue(IssueCode::CanonicalSelfReferencing);
            }
            if (! preg_match('#^https?://#i', $first)) {
                $r->issue(IssueCode::CanonicalIsRelative);
            }
            if (str_contains($first, '#')) {
                $r->issue(IssueCode::CanonicalFragmentUrl);
            }
        }

        // Outside <head>: a canonical link exists that is not under <head>.
        $all = $dom->filterXPath('//link[@rel="canonical"]')->count();
        $inHead = $dom->filterXPath('//head//link[@rel="canonical"]')->count();
        if ($all > $inHead) {
            $r->issue(IssueCode::CanonicalOutsideHead);
        }

        return $r;
    }

    private function normPath(string $u): string
    {
        return rtrim(parse_url($u, PHP_URL_PATH) ?? '/', '/') ?: '/';
    }

    private function differs(string $canonical, string $url): bool
    {
        return $this->normPath($canonical) !== $this->normPath($url);
    }
}

<?php

namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use Symfony\Component\DomCrawler\Crawler;

class PaginationAnalyzer implements Analyzer
{
    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;

        $nextNodes = $dom->filter('link[rel="next"]');
        $prevNodes = $dom->filter('link[rel="prev"]');

        $next = $this->firstResolved($nextNodes, $ctx->url);
        $prev = $this->firstResolved($prevNodes, $ctx->url);
        $r->add('pagination_next', $next);
        $r->add('pagination_prev', $prev);

        $hasNext = $nextNodes->count() > 0;
        $hasPrev = $prevNodes->count() > 0;
        if (! $hasNext && ! $hasPrev) {
            return $r;
        }

        $r->issue(IssueCode::HasPagination);
        if ($hasNext && ! $hasPrev) {
            $r->issue(IssueCode::PaginationFirstPage);
        }
        if ($hasPrev) {
            $r->issue(IssueCode::Paginated2plus);
        }
        if ($nextNodes->count() > 1 || $prevNodes->count() > 1) {
            $r->issue(IssueCode::MultiplePaginationUrls);
        }

        // Resolved anchor targets on the page.
        $anchors = [];
        $dom->filter('a[href]')->each(function (Crawler $a) use (&$anchors, $ctx) {
            $abs = $this->resolve(trim($a->attr('href') ?? ''), $ctx->url);
            if ($abs !== null) {
                $anchors[$this->norm($abs)] = true;
            }
        });
        foreach (array_filter([$next, $prev]) as $target) {
            if (! isset($anchors[$this->norm($target)])) {
                $r->issue(IssueCode::PaginationUrlNotInAnchor);
                break;
            }
        }

        // Self-loop.
        foreach (array_filter([$next, $prev]) as $target) {
            if ($this->norm($target) === $this->norm($ctx->url)) {
                $r->issue(IssueCode::PaginationLoop);
                break;
            }
        }

        return $r;
    }

    private function firstResolved(Crawler $nodes, string $base): ?string
    {
        if ($nodes->count() === 0) {
            return null;
        }
        $href = trim($nodes->first()->attr('href') ?? '');

        return $href === '' ? null : $this->resolve($href, $base);
    }

    private function resolve(string $href, string $base): ?string
    {
        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $b = parse_url($base);
        if (! isset($b['scheme'], $b['host'])) {
            return null;
        }
        $origin = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');
        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }
        $path = rtrim(dirname($b['path'] ?? '/'), '/');

        return $origin.$path.'/'.$href;
    }

    private function norm(string $u): string
    {
        return rtrim($u, '/');
    }
}

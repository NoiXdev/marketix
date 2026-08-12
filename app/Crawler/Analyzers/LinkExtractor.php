<?php

namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\PageContext;
use Symfony\Component\DomCrawler\Crawler;

class LinkExtractor implements Analyzer
{
    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;
        $links = [];

        $dom->filter('a[href]')->each(function (Crawler $node) use (&$links, $ctx) {
            $href = trim($node->attr('href') ?? '');
            if ($href === '' || str_starts_with($href, '#')
                || preg_match('/^(mailto:|tel:|javascript:)/i', $href)) {
                return;
            }

            $abs = $this->resolve($href, $ctx->url);
            if ($abs === null) {
                return;
            }

            $host = parse_url($abs, PHP_URL_HOST) ?? '';
            $internal = $host === $ctx->baseHost || str_ends_with($host, '.'.$ctx->baseHost);

            $links[] = [
                'to_url' => $abs,
                'type' => $internal ? 'internal' : 'external',
                'anchor' => trim($node->text('')),
                'rel' => $node->attr('rel'),
            ];
        });

        $r->add('links', $links);

        return $r;
    }

    private function resolve(string $href, string $base): ?string
    {
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
}

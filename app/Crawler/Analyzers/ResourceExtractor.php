<?php

namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\PageContext;
use Symfony\Component\DomCrawler\Crawler;

class ResourceExtractor implements Analyzer
{
    private const FONT_EXT = ['woff', 'woff2', 'ttf', 'otf', 'eot'];

    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;
        $byUrl = [];

        $add = function (?string $raw, string $type) use (&$byUrl, $ctx) {
            $abs = $this->resolve(trim((string) $raw), $ctx->url);
            if ($abs === null || isset($byUrl[$abs])) {
                return;
            }
            if ($type !== 'font' && in_array($this->ext($abs), self::FONT_EXT, true)) {
                $type = 'font';
            }
            $host = strtolower(parse_url($abs, PHP_URL_HOST) ?? '');
            $internal = $host === $ctx->baseHost || str_ends_with($host, '.'.$ctx->baseHost);
            $byUrl[$abs] = ['url' => $abs, 'type' => $type, 'is_internal' => $internal];
        };

        $dom->filter('script[src]')->each(fn (Crawler $n) => $add($n->attr('src'), 'javascript'));
        $dom->filter('img[src]')->each(fn (Crawler $n) => $add($n->attr('src'), 'image'));
        $dom->filter('img[data-src]')->each(fn (Crawler $n) => $add($n->attr('data-src'), 'image'));
        $dom->filter('source[src]')->each(fn (Crawler $n) => $add($n->attr('src'), 'image'));
        $dom->filter('link[rel]')->each(function (Crawler $n) use ($add) {
            $rel = strtolower($n->attr('rel') ?? '');
            $as = strtolower($n->attr('as') ?? '');
            if (str_contains($rel, 'stylesheet') || $as === 'style') {
                $add($n->attr('href'), 'css');
            } elseif (str_contains($rel, 'preload') && $as === 'font') {
                $add($n->attr('href'), 'font');
            } elseif (str_contains($rel, 'icon')) {
                $add($n->attr('href'), 'image');
            }
        });

        // Responsive images: srcset is a comma-separated list of "URL [descriptor]".
        $dom->filter('img[srcset], source[srcset]')->each(function (Crawler $n) use ($add) {
            foreach (explode(',', $n->attr('srcset') ?? '') as $candidate) {
                $url = trim(explode(' ', trim($candidate))[0]);
                if ($url !== '') {
                    $add($url, 'image');
                }
            }
        });

        // Inline background-image URLs (inline styles only — external CSS is not parsed).
        $dom->filter('[style]')->each(function (Crawler $n) use ($add) {
            if (preg_match_all('/background-image\s*:\s*[^;}]*?url\(\s*(["\']?)([^"\')]+)\1\s*\)/i', $n->attr('style') ?? '', $m)) {
                foreach ($m[2] as $url) {
                    $add(trim($url), 'image');
                }
            }
        });

        $r->add('resources', array_values($byUrl));

        return $r;
    }

    private function ext(string $url): string
    {
        return strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
    }

    private function resolve(string $href, string $base): ?string
    {
        if ($href === '' || str_starts_with($href, '#') || preg_match('/^(data:|mailto:|tel:|javascript:)/i', $href)) {
            return null;
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        if (str_starts_with($href, '//')) {
            return (parse_url($base, PHP_URL_SCHEME) ?: 'https').':'.$href;
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

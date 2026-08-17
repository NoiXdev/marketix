<?php

namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\HreflangCodes;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use Symfony\Component\DomCrawler\Crawler;

class HreflangAnalyzer implements Analyzer
{
    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;

        $domAnnotations = [];
        $dom->filter('link[rel="alternate"][hreflang]')->each(function (Crawler $n) use (&$domAnnotations, $ctx) {
            $lang = trim($n->attr('hreflang') ?? '');
            $href = trim($n->attr('href') ?? '');
            if ($lang === '' || $href === '') {
                return;
            }
            $domAnnotations[] = ['lang' => $lang, 'href' => $this->resolveAbs($href, $ctx->url)];
        });

        // Outside <head>: an alternate/hreflang link exists that is not under <head>.
        $all = $dom->filterXPath('//link[@rel="alternate" and @hreflang]')->count();
        $inHead = $dom->filterXPath('//head//link[@rel="alternate" and @hreflang]')->count();
        if ($all > $inHead) {
            $r->issue(IssueCode::HreflangOutsideHead);
        }

        $headerAnnotations = $this->parseLinkHeader($ctx->linkHeader, $ctx->url);

        $annotations = $this->dedupe(array_merge($domAnnotations, $headerAnnotations));

        if ($annotations !== []) {
            if ($this->hasInvalidCode($annotations)) {
                $r->issue(IssueCode::HreflangIncorrectCodes);
            }

            if ($this->hasDuplicateLanguage($annotations)) {
                $r->issue(IssueCode::HreflangMultipleEntries);
            }

            if (! $this->hasSelfReference($annotations, $ctx->url)) {
                $r->issue(IssueCode::HreflangMissingSelfReference);
            }

            if (! $this->hasXDefault($annotations)) {
                $r->issue(IssueCode::HreflangMissingXDefault);
            }

            if ($this->canonicalDiffers($dom, $ctx->url)) {
                $r->issue(IssueCode::HreflangNotUsingCanonical);
            }
        }

        $r->data['hreflang'] = $annotations ?: null;

        return $r;
    }

    /** @param array{lang: string, href: string}[] $annotations */
    private function hasInvalidCode(array $annotations): bool
    {
        foreach ($annotations as $a) {
            if (! HreflangCodes::isValid($a['lang'])) {
                return true;
            }
        }

        return false;
    }

    /** @param array{lang: string, href: string}[] $annotations */
    private function hasDuplicateLanguage(array $annotations): bool
    {
        $langs = array_map(fn ($a) => $a['lang'], $annotations);

        return count($langs) !== count(array_unique($langs));
    }

    /** @param array{lang: string, href: string}[] $annotations */
    private function hasSelfReference(array $annotations, string $url): bool
    {
        $selfNorm = $this->normPath($url);
        foreach ($annotations as $a) {
            if ($this->normPath($a['href']) === $selfNorm) {
                return true;
            }
        }

        return false;
    }

    /** @param array{lang: string, href: string}[] $annotations */
    private function hasXDefault(array $annotations): bool
    {
        foreach ($annotations as $a) {
            if ($a['lang'] === 'x-default') {
                return true;
            }
        }

        return false;
    }

    private function canonicalDiffers(Crawler $dom, string $url): bool
    {
        $nodes = $dom->filter('link[rel="canonical"]');
        if ($nodes->count() === 0) {
            return false;
        }
        $href = trim($nodes->first()->attr('href') ?? '');
        if ($href === '') {
            return false;
        }
        $canonical = $this->resolveAbs($href, $url);

        return $this->normPath($canonical) !== $this->normPath($url);
    }

    /**
     * Parse the RFC 8288 `Link` header for `rel="alternate"` + `hreflang="xx"` entries.
     * Best-effort: malformed entries are silently skipped.
     *
     * @return array{lang: string, href: string}[]
     */
    private function parseLinkHeader(?string $header, string $base): array
    {
        $out = [];
        if ($header === null || trim($header) === '') {
            return $out;
        }

        if (! preg_match_all('/<([^>]*)>\s*;\s*([^,]+)/', $header, $matches, PREG_SET_ORDER)) {
            return $out;
        }

        foreach ($matches as $m) {
            $url = trim($m[1]);
            $params = $m[2];
            if ($url === '') {
                continue;
            }

            if (! preg_match('/rel\s*=\s*"?alternate"?/i', $params)) {
                continue;
            }

            if (! preg_match('/hreflang\s*=\s*"?([a-zA-Z0-9-]+)"?/i', $params, $hm)) {
                continue;
            }

            $lang = trim($hm[1]);
            if ($lang === '') {
                continue;
            }

            $out[] = ['lang' => $lang, 'href' => $this->resolveAbs($url, $base)];
        }

        return $out;
    }

    /**
     * @param  array{lang: string, href: string}[]  $annotations
     * @return array{lang: string, href: string}[]
     */
    private function dedupe(array $annotations): array
    {
        $seen = [];
        $out = [];
        foreach ($annotations as $a) {
            $key = $a['lang'].'|'.$a['href'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $a;
        }

        return $out;
    }

    private function resolveAbs(string $href, string $base): string
    {
        if ($href === '') {
            return $href;
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $b = parse_url($base);
        $scheme = $b['scheme'] ?? 'https';

        if (str_starts_with($href, '//')) {
            return $scheme.':'.$href;
        }

        if (! isset($b['host'])) {
            return $href;
        }

        $origin = $scheme.'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');
        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        $path = rtrim(dirname($b['path'] ?? '/'), '/');

        return $origin.$path.'/'.$href;
    }

    private function normPath(string $u): string
    {
        $host = strtolower(parse_url($u, PHP_URL_HOST) ?? '');
        $path = rtrim(parse_url($u, PHP_URL_PATH) ?? '/', '/') ?: '/';

        return $host.$path;
    }
}

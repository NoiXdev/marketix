<?php

namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\HtmlText;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use Symfony\Component\DomCrawler\Crawler;

class GeoAnalyzer implements Analyzer
{
    /** Below this visible-text length, a body with a SPA root marker looks JS-dependent. */
    private const THIN_TEXT = 200;

    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;

        if ($dom->filter('main, article')->count() === 0) {
            $r->issue(IssueCode::NoSemanticHtml);
        }

        if (! $this->hasDateSignal($dom)) {
            $r->issue(IssueCode::MissingDateSignal);
        }

        $body = $dom->filter('body');
        $bodyHtml = $body->count() ? $body->html() : '';
        $spaMarker = $dom->filter('#root, #app, #__next, [data-reactroot], [ng-app], [data-server-rendered]')->count() > 0;
        if ($spaMarker && HtmlText::visibleLength($bodyHtml) < self::THIN_TEXT) {
            $r->issue(IssueCode::JsDependentContent);
        }

        return $r;
    }

    private function hasDateSignal(Crawler $dom): bool
    {
        if ($dom->filter('time[datetime]')->count() > 0) {
            return true;
        }
        foreach (['article:published_time', 'article:modified_time', 'og:updated_time'] as $prop) {
            if ($dom->filter('meta[property="'.$prop.'"]')->count() > 0) {
                return true;
            }
        }
        if ($dom->filter('meta[name="date"]')->count() > 0) {
            return true;
        }
        $found = false;
        $dom->filter('script[type="application/ld+json"]')->each(function (Crawler $n) use (&$found) {
            $t = $n->text('');
            if (stripos($t, 'datePublished') !== false || stripos($t, 'dateModified') !== false) {
                $found = true;
            }
        });

        return $found;
    }
}

<?php

namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use Symfony\Component\DomCrawler\Crawler;

class ImageAnalyzer implements Analyzer
{
    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;
        $missing = [];

        $dom->filter('img')->each(function (Crawler $node) use (&$missing) {
            $alt = $node->attr('alt');
            if ($alt === null || trim($alt) === '') {
                $missing[] = $node->attr('src') ?? '';
            }
        });

        $r->add('images_missing_alt', $missing);
        if ($missing !== []) {
            $r->issue(IssueCode::MissingAltText);
        }

        return $r;
    }
}

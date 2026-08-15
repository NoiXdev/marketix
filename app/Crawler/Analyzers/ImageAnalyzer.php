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
        $missingAttr = false;
        $missingSize = false;
        $altTooLong = false;

        $dom->filter('img')->each(function (Crawler $node) use (&$missing, &$missingAttr, &$missingSize, &$altTooLong) {
            $alt = $node->attr('alt');
            if ($alt === null || trim($alt) === '') {
                $missing[] = $node->attr('src') ?? '';
            }
            if ($alt === null) {
                $missingAttr = true;
            }
            if ($alt !== null && mb_strlen($alt) > 100) {
                $altTooLong = true;
            }
            if ($node->attr('width') === null || $node->attr('height') === null) {
                $missingSize = true;
            }
        });

        $r->add('images_missing_alt', $missing);
        if ($missing !== []) {
            $r->issue(IssueCode::MissingAltText);
        }
        if ($missingAttr) {
            $r->issue(IssueCode::ImageMissingAltAttribute);
        }
        if ($missingSize) {
            $r->issue(IssueCode::ImageMissingSizeAttributes);
        }
        if ($altTooLong) {
            $r->issue(IssueCode::ImageAltOver100Chars);
        }

        $backgroundImage = false;
        $dom->filter('[style]')->each(function (Crawler $node) use (&$backgroundImage) {
            if (stripos($node->attr('style') ?? '', 'background-image') !== false) {
                $backgroundImage = true;
            }
        });
        if ($backgroundImage) {
            $r->issue(IssueCode::BackgroundImages);
        }

        return $r;
    }
}

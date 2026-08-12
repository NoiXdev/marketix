<?php

namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use Symfony\Component\DomCrawler\Crawler;

class HeadingAnalyzer implements Analyzer
{
    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;

        $headings = [];
        $dom->filter('h1,h2,h3,h4,h5,h6')->each(function (Crawler $node) use (&$headings) {
            $headings[] = [
                'level' => (int) substr($node->nodeName(), 1),
                'text' => trim($node->text('')),
            ];
        });
        $r->add('headings', $headings);

        $h1Count = count(array_filter($headings, fn ($h) => $h['level'] === 1));
        if ($h1Count === 0) {
            $r->issue(IssueCode::MissingH1);
        } elseif ($h1Count > 1) {
            $r->issue(IssueCode::MultipleH1);
        }

        $prev = null;
        foreach ($headings as $h) {
            if ($prev !== null && $h['level'] > $prev + 1) {
                $r->issue(IssueCode::HeadingOrderSkip);
                break;
            }
            $prev = $h['level'];
        }

        return $r;
    }
}

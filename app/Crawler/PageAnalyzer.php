<?php

namespace App\Crawler;

use App\Crawler\Analyzers\Analyzer;
use App\Crawler\Analyzers\CanonicalAnalyzer;
use App\Crawler\Analyzers\HeadingAnalyzer;
use App\Crawler\Analyzers\HreflangAnalyzer;
use App\Crawler\Analyzers\ImageAnalyzer;
use App\Crawler\Analyzers\IndexabilityAnalyzer;
use App\Crawler\Analyzers\LinkExtractor;
use App\Crawler\Analyzers\MetaAnalyzer;
use App\Crawler\Analyzers\PaginationAnalyzer;
use App\Crawler\Analyzers\ResourceExtractor;
use App\Crawler\Analyzers\SecurityAnalyzer;
use App\Crawler\Analyzers\StructuredDataAnalyzer;
use Symfony\Component\DomCrawler\Crawler;

class PageAnalyzer
{
    /** @return Analyzer[] */
    private function analyzers(): array
    {
        return [
            new MetaAnalyzer,
            new HeadingAnalyzer,
            new IndexabilityAnalyzer,
            new LinkExtractor,
            new ImageAnalyzer,
            new StructuredDataAnalyzer,
            new SecurityAnalyzer,
            new CanonicalAnalyzer,
            new PaginationAnalyzer,
            new HreflangAnalyzer,
            new ResourceExtractor,
        ];
    }

    public function analyze(string $html, PageContext $ctx): AnalyzerResult
    {
        $dom = new Crawler($html);
        $merged = new AnalyzerResult;
        $seen = [];

        foreach ($this->analyzers() as $analyzer) {
            $result = $analyzer->analyze($dom, $ctx);
            $merged->data = array_merge($merged->data, $result->data);
            foreach ($result->issues as $issue) {
                if (! isset($seen[$issue->value])) {
                    $seen[$issue->value] = true;
                    $merged->issues[] = $issue;
                }
            }
        }

        return $merged;
    }
}

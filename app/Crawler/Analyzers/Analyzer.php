<?php

namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\PageContext;
use Symfony\Component\DomCrawler\Crawler;

interface Analyzer
{
    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult;
}

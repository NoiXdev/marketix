<?php

namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use Symfony\Component\DomCrawler\Crawler;

class StructuredDataAnalyzer implements Analyzer
{
    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;
        $types = [];

        $dom->filter('script[type="application/ld+json"]')->each(function (Crawler $node) use (&$types) {
            $data = json_decode($node->text(''), true);
            if (! is_array($data)) {
                return;
            }
            foreach ($this->extractTypes($data) as $t) {
                $types[] = $t;
            }
        });

        $types = array_values(array_unique($types));
        $r->add('structured_data', $types);
        if ($types === []) {
            $r->issue(IssueCode::MissingStructuredData);
        }

        return $r;
    }

    /** @return string[] */
    private function extractTypes(array $data): array
    {
        $out = [];
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            foreach ($data['@graph'] as $node) {
                if (is_array($node)) {
                    $out = array_merge($out, $this->extractTypes($node));
                }
            }
        }
        if (isset($data['@type'])) {
            foreach ((array) $data['@type'] as $t) {
                if (is_string($t)) {
                    $out[] = $t;
                }
            }
        }

        return $out;
    }
}

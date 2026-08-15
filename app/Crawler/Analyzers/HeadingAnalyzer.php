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

        $h1Texts = array_values(array_map(fn ($h) => $h['text'], array_filter($headings, fn ($h) => $h['level'] === 1)));
        $h2Texts = array_values(array_map(fn ($h) => $h['text'], array_filter($headings, fn ($h) => $h['level'] === 2)));

        // First H1 text, for cross-page duplicate detection; null when absent or empty.
        $firstH1 = $h1Texts[0] ?? null;
        $r->add('h1', ($firstH1 !== null && $firstH1 !== '') ? $firstH1 : null);

        // H1 length + image-alt-as-heading.
        foreach ($h1Texts as $text) {
            if (mb_strlen($text) > 70) {
                $r->issue(IssueCode::H1Over70Chars);
                break;
            }
        }
        if ($this->hasImageWithAlt($dom, 'h1')) {
            $r->issue(IssueCode::AltTextInH1);
        }

        // H2 checks.
        if (count($h2Texts) === 0) {
            $r->issue(IssueCode::MissingH2);
        }
        if (count($h2Texts) > 1) {
            $r->issue(IssueCode::MultipleH2);
        }
        foreach ($h2Texts as $text) {
            if (mb_strlen($text) > 70) {
                $r->issue(IssueCode::H2Over70Chars);
                break;
            }
        }
        // Within-page duplicate H2 (trimmed already; case-insensitive; ignore empties).
        $normalizedH2 = array_map(fn ($t) => mb_strtolower($t), array_filter($h2Texts, fn ($t) => $t !== ''));
        if (count($normalizedH2) !== count(array_unique($normalizedH2))) {
            $r->issue(IssueCode::DuplicateH2);
        }
        // Non-sequential: an H2 before the first H1 (only when an H1 exists).
        if ($h1Count > 0 && count($h2Texts) > 0) {
            $firstH1Index = $firstH2Index = null;
            foreach ($headings as $i => $h) {
                if ($h['level'] === 1 && $firstH1Index === null) {
                    $firstH1Index = $i;
                }
                if ($h['level'] === 2 && $firstH2Index === null) {
                    $firstH2Index = $i;
                }
            }
            if ($firstH2Index !== null && $firstH1Index !== null && $firstH2Index < $firstH1Index) {
                $r->issue(IssueCode::H2NonSequential);
            }
        }

        return $r;
    }

    private function hasImageWithAlt(Crawler $dom, string $selector): bool
    {
        $found = false;
        $dom->filter($selector.' img')->each(function (Crawler $img) use (&$found) {
            $alt = $img->attr('alt');
            if ($alt !== null && trim($alt) !== '') {
                $found = true;
            }
        });

        return $found;
    }
}

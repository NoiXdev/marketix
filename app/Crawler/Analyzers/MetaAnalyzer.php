<?php

namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use App\Crawler\PixelWidth;
use Symfony\Component\DomCrawler\Crawler;

class MetaAnalyzer implements Analyzer
{
    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;

        $title = $this->first($dom, 'head title');
        $r->add('title', $title);
        $r->add('title_length', $title !== null ? mb_strlen($title) : 0);
        if ($title === null || trim($title) === '') {
            $r->issue(IssueCode::MissingTitle);
        } elseif (mb_strlen($title) > 60) {
            $r->issue(IssueCode::TitleTooLong);
        }

        if ($title !== null && trim($title) !== '') {
            if (mb_strlen($title) < 30) {
                $r->issue(IssueCode::TitleBelow30Chars);
            }
            $titlePx = PixelWidth::widthPx($title, 1.0);
            if ($titlePx < 200) {
                $r->issue(IssueCode::TitleBelow200px);
            } elseif ($titlePx > 561) {
                $r->issue(IssueCode::TitleOver561px);
            }
            $h1 = $this->first($dom, 'h1');
            if ($h1 !== null && $h1 !== '' && mb_strtolower(trim($h1)) === mb_strtolower(trim($title))) {
                $r->issue(IssueCode::TitleSameAsH1);
            }
        }

        // Structural title checks (whole document, excluding SVG <title>).
        $allTitles = $dom->filterXPath('//title[not(ancestor::svg)]')->count();
        $headTitles = $dom->filterXPath('//head//title[not(ancestor::svg)]')->count();
        if ($allTitles > 1) {
            $r->issue(IssueCode::MultipleTitle);
        }
        if ($allTitles > $headTitles) {
            $r->issue(IssueCode::TitleOutsideHead);
        }

        $desc = $this->attr($dom, 'head meta[name="description"]', 'content');
        $r->add('meta_description', $desc);
        $r->add('meta_description_length', $desc !== null ? mb_strlen($desc) : 0);
        if ($desc === null || trim($desc) === '') {
            $r->issue(IssueCode::MissingMetaDescription);
        }

        if ($desc !== null && trim($desc) !== '') {
            $descLen = mb_strlen($desc);
            if ($descLen < 70) {
                $r->issue(IssueCode::MetaDescriptionBelow70Chars);
            } elseif ($descLen > 155) {
                $r->issue(IssueCode::MetaDescriptionOver155Chars);
            }
            $descPx = PixelWidth::widthPx($desc, 0.72);
            if ($descPx < 400) {
                $r->issue(IssueCode::MetaDescriptionBelow400px);
            } elseif ($descPx > 985) {
                $r->issue(IssueCode::MetaDescriptionOver985px);
            }
        }

        // Structural description checks.
        $allDesc = $dom->filter('meta[name="description"]')->count();
        $headDesc = $dom->filter('head meta[name="description"]')->count();
        if ($allDesc > 1) {
            $r->issue(IssueCode::MultipleMetaDescription);
        }
        if ($allDesc > $headDesc) {
            $r->issue(IssueCode::MetaDescriptionOutsideHead);
        }

        // Meta keywords: extract + structural checks.
        $keywords = $this->attr($dom, 'meta[name="keywords"]', 'content');
        $keywords = $keywords !== null && trim($keywords) !== '' ? trim($keywords) : null;
        $r->add('meta_keywords', $keywords);
        $keywordCount = $dom->filter('meta[name="keywords"]')->count();
        if ($keywordCount === 0) {
            $r->issue(IssueCode::MissingMetaKeywords);
        }
        if ($keywordCount > 1) {
            $r->issue(IssueCode::MultipleMetaKeywords);
        }

        $r->add('canonical', $this->attr($dom, 'head link[rel="canonical"]', 'href'));
        $r->add('meta_robots', $this->attr($dom, 'head meta[name="robots"]', 'content'));

        $text = strip_tags($this->bodyHtml($dom));
        $words = str_word_count($text);
        $r->add('word_count', $words);
        if ($words < 100) {
            $r->issue(IssueCode::ThinContent);
        }
        if (stripos($text, 'lorem ipsum') !== false) {
            $r->issue(IssueCode::LoremIpsum);
        }
        $normalized = preg_replace('/\s+/', ' ', mb_strtolower(trim($text)));
        $r->add('content_hash', ($words >= 100 && $normalized !== '') ? md5($normalized) : null);

        return $r;
    }

    private function first(Crawler $dom, string $selector): ?string
    {
        $node = $dom->filter($selector);

        return $node->count() ? trim($node->first()->text('')) : null;
    }

    private function attr(Crawler $dom, string $selector, string $attr): ?string
    {
        $node = $dom->filter($selector);

        return $node->count() ? $node->first()->attr($attr) : null;
    }

    private function bodyHtml(Crawler $dom): string
    {
        $body = $dom->filter('body');

        return $body->count() ? $body->first()->html('') : '';
    }
}

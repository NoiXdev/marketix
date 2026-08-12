<?php

namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
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

        $desc = $this->attr($dom, 'head meta[name="description"]', 'content');
        $r->add('meta_description', $desc);
        $r->add('meta_description_length', $desc !== null ? mb_strlen($desc) : 0);
        if ($desc === null || trim($desc) === '') {
            $r->issue(IssueCode::MissingMetaDescription);
        }

        $r->add('canonical', $this->attr($dom, 'head link[rel="canonical"]', 'href'));
        $r->add('meta_robots', $this->attr($dom, 'head meta[name="robots"]', 'content'));

        $words = str_word_count(strip_tags($this->bodyHtml($dom)));
        $r->add('word_count', $words);
        if ($words < 100) {
            $r->issue(IssueCode::ThinContent);
        }

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

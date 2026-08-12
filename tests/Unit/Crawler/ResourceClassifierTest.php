<?php

namespace Tests\Unit\Crawler;

use App\Crawler\IssueCode;
use App\Crawler\ResourceClassifier;
use PHPUnit\Framework\TestCase;

class ResourceClassifierTest extends TestCase
{
    public function test_categorizes_content_types(): void
    {
        $this->assertSame('html', ResourceClassifier::categorize('text/html; charset=UTF-8'));
        $this->assertSame('html', ResourceClassifier::categorize('application/xhtml+xml'));
        $this->assertSame('image', ResourceClassifier::categorize('image/png'));
        $this->assertSame('image', ResourceClassifier::categorize('image/svg+xml'));
        $this->assertSame('pdf', ResourceClassifier::categorize('application/pdf'));
        $this->assertSame('media', ResourceClassifier::categorize('video/mp4'));
        $this->assertSame('media', ResourceClassifier::categorize('audio/mpeg'));
        $this->assertSame('other', ResourceClassifier::categorize('text/css'));
        $this->assertSame('other', ResourceClassifier::categorize(null));
    }

    public function test_is_html(): void
    {
        $this->assertTrue(ResourceClassifier::isHtml('text/html'));
        $this->assertFalse(ResourceClassifier::isHtml('image/png'));
    }

    public function test_image_size_thresholds(): void
    {
        $this->assertNull(ResourceClassifier::sizeIssue('image', 100 * 1024));
        $this->assertSame(IssueCode::LargeResource, ResourceClassifier::sizeIssue('image', 400 * 1024));
        $this->assertSame(IssueCode::OversizedResource, ResourceClassifier::sizeIssue('image', 2 * 1024 * 1024));
    }

    public function test_other_asset_size_threshold(): void
    {
        $this->assertNull(ResourceClassifier::sizeIssue('pdf', 1 * 1024 * 1024));
        $this->assertSame(IssueCode::LargeResource, ResourceClassifier::sizeIssue('pdf', 3 * 1024 * 1024));
        $this->assertSame(IssueCode::LargeResource, ResourceClassifier::sizeIssue('media', 5 * 1024 * 1024));
    }

    public function test_html_is_never_size_flagged(): void
    {
        $this->assertNull(ResourceClassifier::sizeIssue('html', 50 * 1024 * 1024));
    }
}

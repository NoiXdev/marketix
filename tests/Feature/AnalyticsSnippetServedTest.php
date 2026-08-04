<?php

namespace Tests\Feature;

use Tests\TestCase;

class AnalyticsSnippetServedTest extends TestCase
{
    public function test_snippet_file_exists_and_is_self_contained(): void
    {
        $path = public_path('mx.js');
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertStringContainsString('/a/event', $contents);
        $this->assertStringContainsString('/a/config/', $contents);
        $this->assertStringContainsString('data-site', $contents);
        // no external dependencies
        $this->assertStringNotContainsString('import ', $contents);
        $this->assertStringNotContainsString('require(', $contents);
    }
}

<?php

namespace Tests\Feature\Demo;

use Tests\TestCase;

class DemoConfigTest extends TestCase
{
    public function test_demo_mode_is_disabled_by_default(): void
    {
        $this->assertFalse(config('demo.enabled'));
    }

    public function test_config_exposes_the_documented_shape(): void
    {
        $this->assertIsString(config('demo.email'));
        $this->assertIsString(config('demo.reset_at'));
        $this->assertIsArray(config('demo.allowed_target_hosts'));
        $this->assertSame(25, config('demo.crawl.max_pages'));
        $this->assertSame(5, config('demo.crawl.max_per_host'));
        $this->assertSame(1, config('demo.crawl.max_concurrent'));
    }
}

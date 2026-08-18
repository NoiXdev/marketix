<?php

namespace Tests\Feature\Demo;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Deliberately no env/config manipulation here: this class asserts the
// route simply does not exist on a normal (demo-disabled) boot, which is a
// different bootstrap than DemoLoginTest's demo-enabled setUp().
class DemoLoginDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_login_route_does_not_exist_when_disabled(): void
    {
        $this->assertFalse(config('demo.enabled'));

        $this->assertNull(app('router')->getRoutes()->getByName('app.demo.login'));
    }
}

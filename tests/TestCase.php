<?php

namespace Tests;

use App\Jobs\RegenerateTraefikConfigJob;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Queue;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Creating a Domain dispatches RegenerateTraefikConfigJob, which writes a
        // Traefik config to a host path that isn't present (or writable) in test
        // environments such as CI. Fake only that job so it never runs; every
        // other job still dispatches normally (QUEUE_CONNECTION=sync).
        Queue::fake([RegenerateTraefikConfigJob::class]);
    }
}

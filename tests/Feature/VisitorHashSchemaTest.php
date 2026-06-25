<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VisitorHashSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_statistics_has_visitor_hash_and_no_ip_column(): void
    {
        $this->assertTrue(Schema::hasColumn('statistics', 'visitor_hash'));
        $this->assertFalse(Schema::hasColumn('statistics', 'ip'));
    }
}

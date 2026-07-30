<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DomainsNameIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_domains_name_column_is_indexed(): void
    {
        $indexes = collect(Schema::getIndexes('domains'));

        $this->assertTrue(
            $indexes->contains(fn (array $i) => in_array('name', $i['columns'], true)),
            'Expected an index covering domains.name (added in 2026_06_11 perf-indexes migration)',
        );
    }
}

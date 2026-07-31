<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StatisticsCreatedAtIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_statistics_has_a_standalone_created_at_index(): void
    {
        $indexes = collect(Schema::getIndexes('statistics'));

        $this->assertTrue(
            $indexes->contains(fn (array $i) => $i['columns'] === ['created_at']),
            'Expected a standalone index on statistics.created_at for the prune range scan',
        );
    }
}

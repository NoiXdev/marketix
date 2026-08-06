<?php

namespace Tests\Feature\Mcp;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_mcp_endpoint_requires_authentication(): void
    {
        $this->postJson('/mcp/marketix', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->assertUnauthorized();
    }

    public function test_the_mcp_route_rejects_get_requests(): void
    {
        $this->getJson('/mcp/marketix')
            ->assertStatus(405);
    }
}

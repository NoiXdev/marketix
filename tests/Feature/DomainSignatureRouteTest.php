<?php

namespace Tests\Feature;

use Tests\TestCase;

class DomainSignatureRouteTest extends TestCase
{
    public function test_signature_route_returns_marker(): void
    {
        $this->get('/.well-known/marketix')
            ->assertOk()
            ->assertExactJson(['app' => 'marketix']);
    }
}

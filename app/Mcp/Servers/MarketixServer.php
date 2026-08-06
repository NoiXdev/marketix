<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Marketix')]
#[Version('1.0.0')]
#[Instructions('Marketix MCP server: manage projects, URLs, domains and view analytics via authenticated tools.')]
class MarketixServer extends Server
{
    /**
     * @var array<int, \Laravel\Mcp\Server\Tool|class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        //
    ];
}

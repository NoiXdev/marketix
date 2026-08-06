<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetLinkStatsTool;
use App\Mcp\Tools\ListDomainsTool;
use App\Mcp\Tools\ListLinksTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListQrCodesTool;
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
        ListProjectsTool::class,
        ListLinksTool::class,
        ListDomainsTool::class,
        ListQrCodesTool::class,
        GetLinkStatsTool::class,
    ];
}

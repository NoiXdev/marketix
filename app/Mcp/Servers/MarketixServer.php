<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateLinkTool;
use App\Mcp\Tools\CreateQrCodeTool;
use App\Mcp\Tools\GetLinkStatsTool;
use App\Mcp\Tools\GetSiteBreakdownTool;
use App\Mcp\Tools\GetSiteStatsTool;
use App\Mcp\Tools\ListDomainsTool;
use App\Mcp\Tools\ListLinksTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListQrCodesTool;
use App\Mcp\Tools\ListSitesTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Marketix')]
#[Version('1.0.0')]
#[Instructions('Marketix MCP server: manage projects, URLs, domains and QR codes, view link click statistics and web analytics for tracked websites via authenticated tools.')]
class MarketixServer extends Server
{
    /**
     * @var array<int, Tool|class-string<Tool>>
     */
    protected array $tools = [
        ListProjectsTool::class,
        ListLinksTool::class,
        ListDomainsTool::class,
        ListQrCodesTool::class,
        GetLinkStatsTool::class,
        ListSitesTool::class,
        GetSiteStatsTool::class,
        GetSiteBreakdownTool::class,
        CreateLinkTool::class,
        CreateQrCodeTool::class,
    ];
}

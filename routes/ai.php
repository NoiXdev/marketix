<?php

use App\Mcp\Servers\MarketixServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/marketix', MarketixServer::class)->middleware(['auth:sanctum', 'throttle:100,1']);

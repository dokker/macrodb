<?php

use App\Mcp\Servers\MacroDbServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();

Mcp::web('/mcp', MacroDbServer::class)->middleware('auth:api');

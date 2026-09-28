<?php

use App\Mcp\Servers\MacroDbServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', MacroDbServer::class);

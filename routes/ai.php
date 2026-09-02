<?php

use App\Mcp\Servers\AdminServer;
use App\Mcp\Servers\CustomerServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::domain('admin.birdcar.dev')->group(function () {
    Mcp::web('/mcp', AdminServer::class);
});

Route::domain('{organization:slug}.birdcar.dev')->group(function () {
    Mcp::web('/mcp', CustomerServer::class);
});

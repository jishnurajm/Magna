<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

// Exposed at: GET /api/v1/hello-world/greet
// Requires: magna.api middleware (bearer token) + hello-world.greet permission.
Route::middleware(['magna.api', 'can:hello-world.greet'])
    ->get('/greet', function (): JsonResponse {
        return response()->json(['message' => 'Hello from Magna!']);
    })
    ->name('hello-world.greet');

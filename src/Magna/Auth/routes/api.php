<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Magna\Auth\Http\Controllers\ApiTokenController;

/*
|--------------------------------------------------------------------------
| Magna Auth — API token management routes
|--------------------------------------------------------------------------
| Prefix: /api/v1  (set in AuthServiceProvider)
| Protected by the magna.api:management middleware — delivery tokens cannot
| manage tokens (by design: a headless delivery client should not be able
| to mint new management tokens).
*/

Route::middleware('magna.api:management')->group(function (): void {
    Route::post('/tokens', [ApiTokenController::class, 'store'])->name('api.tokens.store');
    Route::get('/tokens', [ApiTokenController::class, 'index'])->name('api.tokens.index');
    Route::delete('/tokens/{id}', [ApiTokenController::class, 'destroy'])->name('api.tokens.destroy');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Magna\Install\Http\InstallController;
use Magna\Install\Http\Middleware\EnsureNotInstalled;

Route::middleware(['web', EnsureNotInstalled::class])->prefix('install')->group(function (): void {
    Route::get('/', [InstallController::class, 'requirements']);
    Route::get('/site', [InstallController::class, 'site']);
    Route::post('/site', [InstallController::class, 'storeSite']);
    Route::get('/database', [InstallController::class, 'database']);
    Route::post('/database', [InstallController::class, 'storeDatabase']);
    Route::get('/account', [InstallController::class, 'account']);
    Route::post('/account', [InstallController::class, 'storeAccount']);
});

// The success screen must remain reachable immediately after the lock is
// written, so it lives outside the EnsureNotInstalled guard.
Route::middleware('web')->get('/install/complete', [InstallController::class, 'complete']);

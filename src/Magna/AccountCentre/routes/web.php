<?php

use Illuminate\Support\Facades\Route;
use Magna\AccountCentre\AccountCentreController;

// Magna Account connect handshake — the core-side legs. See
// docs/account-centre-plan.md for the full sequence and
// AccountCentreController for what each step does. Registered under
// AccountCentreServiceProvider::registerRoutes() with the 'web' middleware
// group (session + CSRF), same as Magna\Auth\AuthServiceProvider's routes.
Route::prefix('account-centre')->name('account-centre.')->group(function (): void {
    Route::get('/connect/{provider}', [AccountCentreController::class, 'connect'])
        ->middleware('auth')
        ->name('connect');
    Route::get('/callback', [AccountCentreController::class, 'callback'])
        ->middleware('auth')
        ->name('callback');
    Route::post('/disconnect', [AccountCentreController::class, 'disconnect'])
        ->middleware('auth')
        ->name('disconnect');
});

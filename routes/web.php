<?php

use Illuminate\Support\Facades\Route;
use Magna\Plugins\PluginIconController;

// The Magna admin panel (Filament) is mounted at the root path "/".
// Guests visiting "/" are redirected to "/login" by the panel's auth
// middleware; "/login", the dashboard, and all resources are registered by
// AdminPanelProvider (src/Magna/Admin/AdminPanelProvider.php).
//
// Until installation completes, RedirectIfNotInstalled (web middleware group)
// sends all traffic to "/install" before the panel is reached.

// Named "dashboard" route kept for the Stage 2 auth controllers and their
// tests, which redirect here after login. Points at the panel home.
Route::get('/dashboard', function () {
    return redirect('/');
})->middleware('auth')->name('dashboard');

// An installed plugin's icon (magna.json "icon" field) — only ever loaded
// from within the admin panel UI, so gated the same way the rest of it is.
Route::get('/plugin-icons/{vendor}/{package}', [PluginIconController::class, 'show'])
    ->middleware('auth')
    ->name('plugins.icon');

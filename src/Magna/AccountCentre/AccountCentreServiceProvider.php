<?php

declare(strict_types=1);

namespace Magna\AccountCentre;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * A site's connection to a Magna Account (managemagna.jrstudios.dev) — not a
 * CMS admin login. Registers the connect-handshake routes and the client
 * used both by AccountCentreController and by the Settings page's Account
 * Centre section (see Magna\Admin\Pages\SettingsPage). Core, not a plugin,
 * same reasoning as Magna\Updater — a site should always be able to see and
 * manage its own account connection.
 */
class AccountCentreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AccountCentreClient::class);
    }

    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__.'/routes/web.php');
    }
}

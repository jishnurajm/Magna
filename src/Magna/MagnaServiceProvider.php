<?php

declare(strict_types=1);

namespace Magna;

use Illuminate\Support\ServiceProvider;
use Magna\Audit\AuditServiceProvider;
use Magna\Auth\AuthServiceProvider;
use Magna\Install\InstallServiceProvider;
use Magna\Plugins\PluginsServiceProvider;
use Magna\Settings\SettingsServiceProvider;

/**
 * Root service provider for the Magna kernel.
 *
 * Kernel subsystems (auth, RBAC, plugins, content engine) register their own
 * providers here as they are built, stage by stage — see docs/build-plan.md.
 */
class MagnaServiceProvider extends ServiceProvider
{
    public const VERSION = '1.0.0-dev';

    public function register(): void
    {
        $this->app->register(SettingsServiceProvider::class);
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(InstallServiceProvider::class);
        $this->app->register(AuditServiceProvider::class);
        $this->app->register(PluginsServiceProvider::class);
    }

    public function boot(): void
    {
        //
    }
}

<?php

declare(strict_types=1);

namespace Magna\Plugins;

use Illuminate\Support\ServiceProvider;
use Magna\Marketplace\ComposerRunner;
use Magna\Marketplace\ProcessComposerRunner;
use Magna\Plugins\Commands\PluginDisableCommand;
use Magna\Plugins\Commands\PluginEnableCommand;
use Magna\Plugins\Commands\PluginInstallCommand;
use Magna\Plugins\Commands\PluginListCommand;
use Magna\Plugins\Commands\PluginMakeCommand;
use Magna\Plugins\Commands\PluginUninstallCommand;

class PluginsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PluginDiscovery::class, function (): PluginDiscovery {
            return new PluginDiscovery($this->app->basePath());
        });

        $this->app->singleton(PluginManager::class, function (): PluginManager {
            return new PluginManager($this->app, $this->app->make(PluginDiscovery::class));
        });

        $this->app->bind(ComposerRunner::class, function (): ProcessComposerRunner {
            return new ProcessComposerRunner($this->app->basePath());
        });
    }

    public function boot(): void
    {
        /** @var PluginManager $manager */
        $manager = $this->app->make(PluginManager::class);
        $manager->bootEnabledPlugins();

        if ($this->app->runningInConsole()) {
            $this->commands([
                PluginMakeCommand::class,
                PluginInstallCommand::class,
                PluginEnableCommand::class,
                PluginDisableCommand::class,
                PluginUninstallCommand::class,
                PluginListCommand::class,
            ]);
        }
    }
}

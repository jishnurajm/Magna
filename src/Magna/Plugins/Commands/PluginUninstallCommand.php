<?php

declare(strict_types=1);

namespace Magna\Plugins\Commands;

use Illuminate\Console\Command;
use Magna\Plugins\Exceptions\PluginNotFoundException;
use Magna\Plugins\PluginManager;
use Throwable;

class PluginUninstallCommand extends Command
{
    protected $signature = 'magna:plugin:uninstall
                            {name : The vendor/package name of the plugin}
                            {--purge : Drop the plugin\'s declared tables and remove content types}';

    protected $description = 'Remove a plugin from Magna. Use --purge to also drop its data (irreversible).';

    public function handle(PluginManager $manager): int
    {
        $name = (string) $this->argument('name');
        $purge = (bool) $this->option('purge');

        if ($purge && ! $this->confirm("This will permanently delete all data for [{$name}]. Are you sure?")) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        $this->info("Uninstalling plugin [{$name}]".($purge ? ' (with --purge)' : '').'...');

        try {
            $manager->uninstall($name, $purge);
        } catch (PluginNotFoundException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Uninstall failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Plugin [{$name}] uninstalled.".($purge ? ' Data has been purged.' : ' Data has been preserved.'));

        return self::SUCCESS;
    }
}

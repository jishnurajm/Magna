<?php

declare(strict_types=1);

namespace Magna\Plugins\Commands;

use Illuminate\Console\Command;
use Magna\Plugins\Exceptions\PluginNotFoundException;
use Magna\Plugins\PluginManager;
use Throwable;

class PluginDisableCommand extends Command
{
    protected $signature = 'magna:plugin:disable {name : The vendor/package name of the plugin}';

    protected $description = 'Disable an enabled Magna plugin (data is preserved).';

    public function handle(PluginManager $manager): int
    {
        $name = (string) $this->argument('name');

        $this->info("Disabling plugin [{$name}]...");

        try {
            $manager->disable($name);
        } catch (PluginNotFoundException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error("Failed to disable [{$name}]: ".$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Plugin [{$name}] disabled. Its data has been preserved.");

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace Magna\Plugins\Commands;

use Illuminate\Console\Command;
use Magna\Plugins\Exceptions\PluginCompatibilityException;
use Magna\Plugins\Exceptions\PluginNotFoundException;
use Magna\Plugins\PluginManager;
use Throwable;

class PluginEnableCommand extends Command
{
    protected $signature = 'magna:plugin:enable {name : The vendor/package name of the plugin}';

    protected $description = 'Enable a discovered Magna plugin (validates manifest, runs migrations, registers permissions).';

    public function handle(PluginManager $manager): int
    {
        $name = (string) $this->argument('name');

        $this->info("Enabling plugin [{$name}]...");

        try {
            $manager->enable($name);
        } catch (PluginNotFoundException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (PluginCompatibilityException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error("Failed to enable [{$name}]: ".$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Plugin [{$name}] enabled successfully.");

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace Magna\Plugins\Commands;

use Illuminate\Console\Command;
use Magna\Plugins\Exceptions\PluginCompatibilityException;
use Magna\Plugins\Exceptions\PluginNotFoundException;
use Magna\Plugins\PluginManager;
use Throwable;

class PluginInstallCommand extends Command
{
    protected $signature = 'magna:plugin:install {name : The vendor/package name of the plugin}';

    protected $description = 'Install and enable a Magna plugin (assumes the Composer package is already required).';

    public function handle(PluginManager $manager): int
    {
        $name = (string) $this->argument('name');

        $this->info("Installing plugin [{$name}]...");
        $this->line("  (Assuming `composer require {$name}` has already been run.)");

        try {
            $manager->enable($name);
        } catch (PluginNotFoundException $e) {
            $this->error($e->getMessage());
            $this->line("  Hint: run `composer require {$name}` first, then retry.");

            return self::FAILURE;
        } catch (PluginCompatibilityException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Installation failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Plugin [{$name}] installed and enabled.");

        return self::SUCCESS;
    }
}

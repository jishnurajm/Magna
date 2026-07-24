<?php

declare(strict_types=1);

namespace Magna\Plugins\Commands;

use Illuminate\Console\Command;
use Magna\Plugins\PluginDiscovery;
use Magna\Plugins\PluginInfo;
use Magna\Plugins\PluginRecord;

class PluginListCommand extends Command
{
    protected $signature = 'magna:plugin:list';

    protected $description = 'List all discovered Magna plugins and their current state.';

    public function handle(PluginDiscovery $discovery): int
    {
        $discovered = $discovery->discover();
        /** @var array<string, PluginRecord> $installed */
        $installed = PluginRecord::all()->keyBy('name')->all();

        if ($discovered === []) {
            $this->info('No Magna plugins discovered.');

            return self::SUCCESS;
        }

        $rows = array_map(function (PluginInfo $info) use ($installed): array {
            $record = $installed[$info->manifest->name] ?? null;
            $status = $record === null ? 'discovered' : ($record->enabled ? '<info>enabled</info>' : 'disabled');

            return [
                $info->manifest->name,
                $info->manifest->version,
                $status,
                $info->manifest->displayName,
            ];
        }, $discovered);

        $this->table(['Package', 'Version', 'Status', 'Display name'], $rows);

        return self::SUCCESS;
    }
}

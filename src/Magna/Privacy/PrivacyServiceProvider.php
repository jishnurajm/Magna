<?php

declare(strict_types=1);

namespace Magna\Privacy;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Magna\Privacy\Commands\PrivacyEraseCommand;
use Magna\Privacy\Commands\PrivacyExportCommand;
use Magna\Privacy\Commands\PrivacyPurgeExportsCommand;

class PrivacyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PrivacyExportCommand::class,
                PrivacyEraseCommand::class,
                PrivacyPurgeExportsCommand::class,
            ]);
        }

        // Stage 13 (S5-02): this command existed (Audit 2's B-05 fix) but
        // was never actually scheduled — GDPR export archives only got
        // purged if an operator remembered to run it manually.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('magna:privacy:purge-exports')->weekly();
        });
    }
}

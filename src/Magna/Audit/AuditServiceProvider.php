<?php

declare(strict_types=1);

namespace Magna\Audit;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Magna\Audit\Console\AuditExportCommand;
use Magna\Audit\Console\AuditPruneCommand;
use Magna\Audit\Listeners\RecordLoginFailure;
use Magna\Audit\Listeners\RecordLoginSuccess;

class AuditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Stage 13 (S1-09): a plugin's boot() can call Event::forget(Login::class)
        // to silently disable this listener — PluginManager's security-integrity
        // check (bootEnabledPlugins()) re-registers it after every plugin-boot
        // pass if it detects that's happened, so this isn't the only line of
        // defense.
        Event::listen(Login::class, RecordLoginSuccess::class);
        Event::listen(Failed::class, RecordLoginFailure::class);

        $this->commands([AuditExportCommand::class, AuditPruneCommand::class]);

        // Stage 13 (S5-02): audit_logs is append-only with no other pruning
        // mechanism — was growing unbounded with no scheduled cleanup.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('magna:audit:prune')->daily();
        });
    }
}

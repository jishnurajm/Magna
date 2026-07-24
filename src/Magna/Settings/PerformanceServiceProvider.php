<?php

declare(strict_types=1);

namespace Magna\Settings;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

/**
 * Applies the DB-backed PerformanceSettings (cache/queue driver, Redis
 * connection, Octane server) to the runtime config, so changing them on the
 * admin Settings page takes effect without touching .env.
 *
 * Runs in boot() rather than register(): Cache/Queue/Redis managers resolve
 * their driver lazily from config the first time a facade call actually needs
 * one, which always happens after every provider's register() AND boot() has
 * run — so overriding config here is still in time, and DB/cache facades used
 * to read the setting itself are already fully available.
 *
 * Reading PerformanceSettings::get() below uses whatever cache driver is
 * currently configured (i.e. still the .env default at this point, since we
 * haven't applied the override yet) — that one lookup is cheap and cached for
 * an hour by SettingsRepository, so there's no circularity in practice: the
 * setting that picks the driver is fetched once via the old driver, then
 * everything afterwards uses the new one.
 */
class PerformanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! Schema::hasTable('settings')) {
            // Fresh install, before the first migration has run.
            return;
        }

        try {
            $settings = PerformanceSettings::get();
        } catch (\Throwable) {
            // Never let a misconfigured/unreachable settings store break boot.
            return;
        }

        config([
            'cache.default' => $settings->cache_driver,
            'queue.default' => $settings->queue_connection,
            'octane.server' => $settings->octane_server,
        ]);

        if (in_array('redis', [$settings->cache_driver, $settings->queue_connection], true)) {
            config([
                'database.redis.default.host' => $settings->redis_host,
                'database.redis.default.port' => $settings->redis_port,
                'database.redis.default.password' => $settings->redis_password,
                'database.redis.default.database' => $settings->redis_database,
            ]);
        }
    }
}

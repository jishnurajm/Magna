<?php

declare(strict_types=1);

namespace Magna\Install;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Probes a candidate database connection and, once confirmed, commits it:
 * writes .env, clears any cached config, and runs the initial migrate+seed.
 *
 * Extracted from InstallController::storeDatabase(), which mixed input
 * validation, connection probing, .env writes, cache clearing, and
 * migrate/seed all in one controller method.
 */
class DatabaseInstaller
{
    public function __construct(
        private readonly EnvWriter $env,
    ) {}

    /**
     * @param  array<string, mixed>  $connection
     *
     * @throws \Throwable if the connection cannot be established
     */
    public function probe(array $connection): void
    {
        config(['database.connections.magna' => $connection]);
        DB::purge('magna');
        DB::connection('magna')->getPdo();
    }

    /** @param  array<string, string>  $envValues */
    public function finalize(array $envValues): void
    {
        $this->env->set($envValues);

        if (! defined('PASSWORD_ARGON2ID')) {
            $this->env->set(['HASH_DRIVER' => 'bcrypt']);
        }

        ConfigCache::clearIfCached();

        config(['database.default' => 'magna']);

        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder', '--force' => true]);
    }
}

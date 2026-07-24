<?php

declare(strict_types=1);

namespace Magna\Install;

use Illuminate\Support\Facades\Artisan;

/**
 * A cached configuration would keep serving stale values and silently
 * ignore whatever the installer just wrote to .env.
 */
class ConfigCache
{
    public static function clearIfCached(): void
    {
        if (app()->configurationIsCached()) {
            Artisan::call('config:clear');
        }
    }
}

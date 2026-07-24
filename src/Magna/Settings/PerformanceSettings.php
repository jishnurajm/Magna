<?php

declare(strict_types=1);

namespace Magna\Settings;

use Magna\Settings\Attributes\Secret;

/**
 * Cache/queue/Octane configuration, editable from the admin Settings page
 * instead of the .env file. Applied to the runtime config by
 * PerformanceServiceProvider::boot() on every request/console invocation —
 * see that class for why these values are otherwise cosmetic.
 */
class PerformanceSettings extends Settings
{
    /** @var 'file'|'database'|'redis' */
    public string $cache_driver = 'database';

    /** @var 'sync'|'database'|'redis' */
    public string $queue_connection = 'database';

    public string $redis_host = '127.0.0.1';

    public int $redis_port = 6379;

    #[Secret]
    public ?string $redis_password = null;

    public int $redis_database = 0;

    /** @var 'frankenphp'|'swoole'|'roadrunner' */
    public string $octane_server = 'frankenphp';
}

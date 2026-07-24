<?php

use Magna\Blocks\BlockRegistry;
use Magna\Content\FieldTypeRegistry;
use Magna\Content\SchemaRegistry;
use Magna\Settings\ApiSettings;
use Magna\Settings\GeneralSettings;

return [

    /*
    |--------------------------------------------------------------------------
    | Octane Server
    |--------------------------------------------------------------------------
    | Reference deployment: FrankenPHP worker mode.
    | Install: composer require laravel/octane
    | Start:   php artisan octane:frankenphp
    */

    'server' => env('OCTANE_SERVER', 'frankenphp'),

    /*
    |--------------------------------------------------------------------------
    | HTTPS / TLS (FrankenPHP)
    |--------------------------------------------------------------------------
    | Set OCTANE_HTTPS=false in local dev; FrankenPHP manages TLS in production.
    */

    'https' => (bool) env('OCTANE_HTTPS', false),

    /*
    |--------------------------------------------------------------------------
    | Worker Count
    |--------------------------------------------------------------------------
    | 0 = auto (one worker per CPU core).
    */

    'workers' => (int) env('OCTANE_WORKERS', 0),

    'max_requests' => (int) env('OCTANE_MAX_REQUESTS', 1000),

    /*
    |--------------------------------------------------------------------------
    | Warm / Flush
    |--------------------------------------------------------------------------
    | Services to pre-resolve before the first request (boot-time) and
    | bindings to re-resolve between requests (flush-time).
    |
    | SchemaRegistry is safe to warm — content types are immutable at runtime.
    | AppSetting cache is cleared between requests to pick up admin changes.
    */

    'warm' => [
        SchemaRegistry::class,
        FieldTypeRegistry::class,
        BlockRegistry::class,
    ],

    'flush' => [
        ApiSettings::class,
        GeneralSettings::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Garbage Collection
    |--------------------------------------------------------------------------
    */

    'garbage' => 50,

    /*
    |--------------------------------------------------------------------------
    | Tables (Swoole only — ignored by FrankenPHP)
    |--------------------------------------------------------------------------
    */

    'tables' => [],

    /*
    |--------------------------------------------------------------------------
    | Request Listeners
    |--------------------------------------------------------------------------
    */

    'listeners' => [],

];

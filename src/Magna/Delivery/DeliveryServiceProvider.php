<?php

declare(strict_types=1);

namespace Magna\Delivery;

use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Magna\Content\Events\EntryDeleted;
use Magna\Content\Events\EntryPublished;
use Magna\Content\Events\EntryUnpublished;
use Magna\Content\Events\EntryUpdated;
use Magna\Delivery\Console\BenchSeedCommand;
use Magna\Delivery\Controllers\ContentListController;
use Magna\Delivery\Controllers\ContentSingleController;
use Magna\Delivery\Controllers\OpenApiController;
use Magna\Delivery\Controllers\PreviewTokenController;
use Magna\Delivery\EdgeCache\Contracts\PurgesEdgeCache;
use Magna\Delivery\EdgeCache\Drivers\CloudflareEdgeCacheDriver;
use Magna\Delivery\EdgeCache\Drivers\FastlyEdgeCacheDriver;
use Magna\Delivery\EdgeCache\Drivers\NullEdgeCacheDriver;
use Magna\Delivery\EdgeCache\Drivers\VarnishEdgeCacheDriver;
use Magna\Delivery\EdgeCache\EdgeCacheDispatcher;
use Magna\Delivery\Listeners\DeliveryCacheInvalidator;
use Magna\Media\Events\MediaCreated;
use Magna\Media\Events\MediaDeleted;

class DeliveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ETagService::class);
        $this->app->singleton(ResponseCacheService::class);
        $this->app->singleton(PreviewTokenService::class);
        $this->app->singleton(DeliveryQueryBuilder::class);
        $this->app->singleton(CursorPaginator::class);
        $this->app->singleton(RelationLoader::class);
        $this->app->singleton(EntryTransformer::class);
        $this->app->singleton(OpenApiGenerator::class);
        $this->app->singleton(EdgeCacheDispatcher::class);

        $this->app->singleton(PurgesEdgeCache::class, function () {
            $driver = $this->configString('magna.edge_cache.driver', 'null');

            return match ($driver) {
                'cloudflare' => new CloudflareEdgeCacheDriver(
                    $this->app->make(Http::class),
                    $this->configString('magna.edge_cache.cloudflare.zone_id'),
                    $this->configString('magna.edge_cache.cloudflare.api_token'),
                ),
                'fastly' => new FastlyEdgeCacheDriver(
                    $this->app->make(Http::class),
                    $this->configString('magna.edge_cache.fastly.service_id'),
                    $this->configString('magna.edge_cache.fastly.api_token'),
                ),
                'varnish' => new VarnishEdgeCacheDriver(
                    $this->app->make(Http::class),
                    $this->configString('magna.edge_cache.varnish.host'),
                    $this->configString('magna.edge_cache.varnish.secret'),
                ),
                default => new NullEdgeCacheDriver,
            };
        });

        // Bind controllers so the service container resolves their dependencies.
        $this->app->bind(ContentListController::class);
        $this->app->bind(ContentSingleController::class);
        $this->app->bind(PreviewTokenController::class);
        $this->app->bind(OpenApiController::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([BenchSeedCommand::class]);
        }

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/routes/api.php');

        $invalidator = DeliveryCacheInvalidator::class;
        Event::listen(EntryPublished::class, [$invalidator, 'handleEntryPublished']);
        Event::listen(EntryUpdated::class, [$invalidator, 'handleEntryUpdated']);
        Event::listen(EntryDeleted::class, [$invalidator, 'handleEntryDeleted']);
        Event::listen(EntryUnpublished::class, [$invalidator, 'handleEntryUnpublished']);
        Event::listen(MediaCreated::class, [$invalidator, 'handleMediaCreated']);
        Event::listen(MediaDeleted::class, [$invalidator, 'handleMediaDeleted']);
    }

    private function configString(string $key, string $default = ''): string
    {
        $value = config($key, $default);

        return is_string($value) ? $value : $default;
    }
}

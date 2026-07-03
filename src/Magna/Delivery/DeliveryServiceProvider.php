<?php

declare(strict_types=1);

namespace Magna\Delivery;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Magna\Delivery\Controllers\ContentListController;
use Magna\Delivery\Controllers\ContentSingleController;
use Magna\Delivery\Controllers\OpenApiController;
use Magna\Delivery\Controllers\PreviewTokenController;

class DeliveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ETagService::class);
        $this->app->singleton(PreviewTokenService::class);
        $this->app->singleton(DeliveryQueryBuilder::class);
        $this->app->singleton(CursorPaginator::class);
        $this->app->singleton(RelationLoader::class);
        $this->app->singleton(EntryTransformer::class);
        $this->app->singleton(OpenApiGenerator::class);

        // Bind controllers so the service container resolves their dependencies.
        $this->app->bind(ContentListController::class);
        $this->app->bind(ContentSingleController::class);
        $this->app->bind(PreviewTokenController::class);
        $this->app->bind(OpenApiController::class);
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/routes/api.php');
    }
}

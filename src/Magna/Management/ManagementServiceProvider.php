<?php

declare(strict_types=1);

namespace Magna\Management;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Magna\Auth\Http\Middleware\DenyManagementCrossOriginMiddleware;
use Magna\Auth\PermissionRegistry;
use Magna\Management\Controllers\ContentTypeController;
use Magna\Management\Controllers\EntryController;
use Magna\Management\Controllers\FolderController;
use Magna\Management\Controllers\MediaController;
use Magna\Management\Controllers\SettingController;
use Magna\Management\Controllers\UserController;
use Magna\Management\Controllers\UserRoleController;
use Magna\Management\Controllers\WebhookController;
use Magna\Management\Controllers\WebhookDeliveryController;

class ManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EntryController::class);
        $this->app->bind(MediaController::class);
        $this->app->bind(FolderController::class);
        $this->app->bind(ContentTypeController::class);
        $this->app->bind(SettingController::class);
        $this->app->bind(UserController::class);
        $this->app->bind(UserRoleController::class);
        $this->app->bind(WebhookController::class);
        $this->app->bind(WebhookDeliveryController::class);
    }

    public function boot(): void
    {
        $this->registerPermissions();
        $this->registerRoutes();
    }

    private function registerPermissions(): void
    {
        $registry = $this->app->make(PermissionRegistry::class);

        $registry->registerMany([
            'media.view' => 'View media library files and folders',
            'media.upload' => 'Upload new media files',
            'media.delete' => 'Delete media files and folders',
        ]);
    }

    private function registerRoutes(): void
    {
        Route::middleware(['api', DenyManagementCrossOriginMiddleware::class])
            ->prefix('api/v1')
            ->group(__DIR__.'/routes/api.php');
    }
}

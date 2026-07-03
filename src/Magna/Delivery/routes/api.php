<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Magna\Delivery\Controllers\ContentListController;
use Magna\Delivery\Controllers\ContentSingleController;
use Magna\Delivery\Controllers\OpenApiController;
use Magna\Delivery\Controllers\PreviewTokenController;

/*
|--------------------------------------------------------------------------
| Magna Delivery — REST API routes
|--------------------------------------------------------------------------
| Prefix: /api/v1  (set in DeliveryServiceProvider)
| Delivery endpoints: delivery or management tokens accepted.
| Management-only endpoints: management token required.
*/

Route::middleware('magna.api:delivery')->group(function (): void {
    Route::get('/content/{type}', ContentListController::class)->name('magna.delivery.list');
    Route::get('/content/{type}/{id}', ContentSingleController::class)->name('magna.delivery.single');
});

Route::middleware('magna.api:management')->group(function (): void {
    Route::post('/content/{type}/{id}/preview-token', PreviewTokenController::class)->name('magna.delivery.preview-token');
    Route::get('/openapi.json', OpenApiController::class)->name('magna.delivery.openapi');
});

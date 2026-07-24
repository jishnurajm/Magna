<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Magna\Management\Controllers\ContentTypeController;
use Magna\Management\Controllers\EntryController;
use Magna\Management\Controllers\FolderController;
use Magna\Management\Controllers\MediaController;
use Magna\Management\Controllers\SettingController;
use Magna\Management\Controllers\UserController;
use Magna\Management\Controllers\UserRoleController;
use Magna\Management\Controllers\WebhookController;
use Magna\Management\Controllers\WebhookDeliveryController;

Route::middleware('magna.api:management')->prefix('manage')->group(function (): void {
    // ── Entries ────────────────────────────────────────────────────────────────
    Route::get('/entries/{type}', [EntryController::class, 'index']);
    Route::post('/entries/{type}', [EntryController::class, 'store']);
    Route::get('/entries/{type}/{id}', [EntryController::class, 'show']);
    Route::put('/entries/{type}/{id}', [EntryController::class, 'update']);
    Route::delete('/entries/{type}/{id}', [EntryController::class, 'destroy']);
    Route::post('/entries/{type}/{id}/publish', [EntryController::class, 'publish']);
    Route::post('/entries/{type}/{id}/unpublish', [EntryController::class, 'unpublish']);
    Route::post('/entries/{type}/{id}/draft', [EntryController::class, 'draft']);
    Route::get('/entries/{type}/{id}/revisions', [EntryController::class, 'revisions']);
    Route::post('/entries/{type}/{id}/revisions/{revision}/restore', [EntryController::class, 'restore']);

    // ── Media — specific paths before parametric ───────────────────────────────
    Route::get('/media/folders', [FolderController::class, 'index']);
    Route::post('/media/folders', [FolderController::class, 'store']);
    Route::delete('/media/folders/{folder}', [FolderController::class, 'destroy']);

    Route::post('/media', [MediaController::class, 'store']);
    Route::get('/media/{media}', [MediaController::class, 'show']);
    Route::delete('/media/{media}', [MediaController::class, 'destroy']);

    // ── Content types ──────────────────────────────────────────────────────────
    Route::get('/content-types', [ContentTypeController::class, 'index']);
    Route::post('/content-types', [ContentTypeController::class, 'store']);
    Route::get('/content-types/{handle}', [ContentTypeController::class, 'show']);
    Route::put('/content-types/{handle}', [ContentTypeController::class, 'update']);

    // ── Settings ───────────────────────────────────────────────────────────────
    Route::get('/settings', [SettingController::class, 'index']);
    Route::put('/settings', [SettingController::class, 'update']);

    // ── Users ──────────────────────────────────────────────────────────────────
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::post('/users/{user}/roles', [UserRoleController::class, 'store']);

    // ── Webhooks ───────────────────────────────────────────────────────────────
    Route::get('/webhooks', [WebhookController::class, 'index']);
    Route::post('/webhooks', [WebhookController::class, 'store']);
    Route::get('/webhooks/{webhook}', [WebhookController::class, 'show']);
    Route::put('/webhooks/{webhook}', [WebhookController::class, 'update']);
    Route::delete('/webhooks/{webhook}', [WebhookController::class, 'destroy']);
    Route::get('/webhooks/{webhook}/deliveries', [WebhookDeliveryController::class, 'index']);
    Route::post('/webhooks/{webhook}/deliveries/{delivery}/retry', [WebhookDeliveryController::class, 'retry']);
});

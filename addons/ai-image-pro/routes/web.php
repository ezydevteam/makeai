<?php

declare(strict_types=1);

use Addons\AiImagePro\Http\Controllers\Admin\ImageProAdminController;
use Addons\AiImagePro\Http\Controllers\Admin\ImageProSettingsController;
use Addons\AiImagePro\Http\Controllers\Admin\LandingMediaController;
use Addons\AiImagePro\Http\Controllers\User\AssetController;
use Addons\AiImagePro\Http\Controllers\User\FolderController;
use Addons\AiImagePro\Http\Controllers\User\LandingController;
use Addons\AiImagePro\Http\Controllers\User\LibraryController;
use Addons\AiImagePro\Http\Controllers\User\StudioController;
use Addons\AiImagePro\Http\Controllers\User\ToolController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Studio — no `auth` middleware by design
|--------------------------------------------------------------------------
| The admin decides per operation (and for the Studio page itself) whether
| guests are allowed, so authentication is enforced inside ImageAccessService
| against the configured access level, not by a blanket route middleware.
| Every controller action here still gates before it does any work.
*/
Route::middleware(['web', 'addon.enabled:ai-image-pro'])
    ->prefix('ai-image')
    ->name('addon.aip.user.')
    ->group(function () {
        // The public marketing page. Signed-in users with Studio access are bounced
        // straight to the app; `?preview=1` lets an admin view the landing anyway.
        Route::get('/', [LandingController::class, 'index'])->name('landing');

        Route::get('/studio', [StudioController::class, 'index'])->name('studio');

        /*
         * `throttle` is aliased to the app's own ThrottleAiRequests, NOT Laravel's —
         * its signature is {category},{maxAttempts},{windowSeconds}. Laravel's
         * `throttle:30,1` form parses here as category "30" with a max of 1 attempt,
         * which 429s the user on their very first click. Always pass all three.
         */
        Route::post('/upload', [StudioController::class, 'upload'])
            ->name('upload')
            ->middleware('throttle:public,30,60');

        Route::post('/generate', [StudioController::class, 'generate'])
            ->name('generate')
            ->middleware('throttle:text_gen,30,60');

        // Queued provider operations (upscale, bg_remove, inpaint, …)
        Route::post('/ops/{operation}', [StudioController::class, 'runOperation'])
            ->name('ops')
            ->middleware('throttle:text_gen,30,60');

        // Synchronous local GD operations (resize, crop, compress, …). Free and cheap,
        // so a looser ceiling — this is the tier a visitor is expected to hammer.
        Route::post('/tools/{operation}', [ToolController::class, 'run'])
            ->name('tools')
            ->middleware('throttle:public,60,60');

        Route::get('/jobs/{job:ulid}/status', [StudioController::class, 'status'])->name('jobs.status');

        Route::get('/assets/{asset:ulid}', [AssetController::class, 'show'])->name('assets.show');
        Route::get('/assets/{asset:ulid}/download', [AssetController::class, 'download'])->name('assets.download');
        Route::post('/assets/{asset:ulid}/favorite', [AssetController::class, 'toggleFavorite'])->name('assets.favorite');
        Route::delete('/assets/{asset:ulid}', [AssetController::class, 'destroy'])->name('assets.destroy');
    });

/*
|--------------------------------------------------------------------------
| Library — always requires a real account
|--------------------------------------------------------------------------
*/
Route::middleware(['web', 'auth', 'addon.enabled:ai-image-pro'])
    ->prefix('ai-image')
    ->name('addon.aip.user.')
    ->group(function () {
        Route::get('/library', [LibraryController::class, 'index'])->name('library');
        Route::post('/assets/bulk', [AssetController::class, 'bulk'])->name('assets.bulk');

        Route::post('/folders', [FolderController::class, 'store'])->name('folders.store');
        Route::put('/folders/{folder}', [FolderController::class, 'update'])->name('folders.update');
        Route::delete('/folders/{folder}', [FolderController::class, 'destroy'])->name('folders.destroy');
    });

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::middleware(['web', 'auth:admin'])
    ->prefix('admin/ai-image-pro')
    ->name('addon.aip.admin.')
    ->group(function () {
        Route::middleware('admin.permission:addon.aip.manage')->group(function () {
            Route::get('/', [ImageProAdminController::class, 'overview'])->name('overview');
        });

        Route::middleware('admin.permission:addon.aip.settings')->group(function () {
            Route::get('/settings', [ImageProSettingsController::class, 'edit'])->name('settings');
            Route::put('/settings', [ImageProSettingsController::class, 'update'])->name('settings.update');

            // Landing-page image uploads for the settings repeaters.
            Route::post('/media', [LandingMediaController::class, 'store'])->name('media.store');
            Route::delete('/media', [LandingMediaController::class, 'destroy'])->name('media.destroy');

            // Watermark logo upload.
            Route::post('/watermark-logo', [LandingMediaController::class, 'storeWatermark'])->name('watermark.store');
            Route::delete('/watermark-logo', [LandingMediaController::class, 'destroyWatermark'])->name('watermark.destroy');

            Route::post('/presets', [ImageProSettingsController::class, 'storePreset'])->name('presets.store');
            Route::put('/presets/{preset}', [ImageProSettingsController::class, 'updatePreset'])->name('presets.update');
            Route::delete('/presets/{preset}', [ImageProSettingsController::class, 'destroyPreset'])->name('presets.destroy');
        });
    });

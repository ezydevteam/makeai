<?php

use Addons\AiVideoCreator\Http\Controllers\Admin\VideoAdminController;
use Addons\AiVideoCreator\Http\Controllers\Admin\VideoSettingsController;
use Addons\AiVideoCreator\Http\Controllers\Public\ShareController;
use Addons\AiVideoCreator\Http\Controllers\User\VideoCreatorController;
use Addons\AiVideoCreator\Http\Controllers\User\VideoLibraryController;
use Addons\AiVideoCreator\Http\Controllers\User\VideoViewerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])
    ->prefix('video-creator')
    ->name('addon.video.user.')
    ->group(function () {
        Route::get('/', [VideoLibraryController::class, 'index'])->name('library');
        Route::post('/folders', [VideoLibraryController::class, 'storeFolder'])->name('folders.store');
        Route::post('/projects', [VideoLibraryController::class, 'storeProject'])->name('projects.store');
        Route::delete('/renders/{render}', [VideoLibraryController::class, 'destroyRender'])->name('renders.destroy');

        Route::get('/create', [VideoCreatorController::class, 'create'])->name('create');
        Route::post('/create', [VideoCreatorController::class, 'store'])
            ->middleware('throttle:10,1')->name('store');

        Route::get('/renders/{render}', [VideoViewerController::class, 'show'])->name('renders.show');
        Route::get('/renders/{render}/status', [VideoViewerController::class, 'status'])->name('renders.status');
        Route::post('/renders/{render}/subtitles', [VideoViewerController::class, 'generateSubtitles'])
            ->middleware('throttle:5,1')->name('renders.subtitles');
        Route::post('/renders/{render}/trim', [VideoViewerController::class, 'trim'])->name('renders.trim');
        Route::post('/renders/{render}/share', [VideoViewerController::class, 'toggleShare'])->name('renders.share');
    });

Route::middleware(['web'])
    ->prefix('video')
    ->name('addon.video.public.')
    ->group(function () {
        Route::get('/share/{token}', [ShareController::class, 'show'])->name('share');
    });

Route::middleware(['web', 'admin.auth'])
    ->prefix('admin/video-creator')
    ->name('addon.video.admin.')
    ->group(function () {
        Route::get('/', [VideoAdminController::class, 'overview'])
            ->name('overview')
            ->middleware('admin.permission:addon.video.manage');

        Route::get('/settings', [VideoSettingsController::class, 'edit'])
            ->name('settings')
            ->middleware('admin.permission:addon.video.settings');
        Route::put('/settings', [VideoSettingsController::class, 'update'])
            ->middleware('admin.permission:addon.video.settings');
    });

<?php

declare(strict_types=1);

use Addons\AiVoiceover\Http\Controllers\Admin\VoAdminController;
use Addons\AiVoiceover\Http\Controllers\Admin\VoSettingsController;
use Addons\AiVoiceover\Http\Controllers\Public\PodcastController;
use Addons\AiVoiceover\Http\Controllers\User\StudioController;
use Illuminate\Support\Facades\Route;

// User routes
Route::middleware(['web', 'auth'])
    ->prefix('voiceover-studio')
    ->name('addon.vo.user.')
    ->group(function () {
        Route::get('/', [StudioController::class, 'index'])->name('studio');
        Route::post('/projects', [StudioController::class, 'storeProject'])->name('projects.store');
        Route::get('/projects/{project:ulid}', [StudioController::class, 'showProject'])->name('projects.show');
        Route::put('/projects/{project:ulid}', [StudioController::class, 'updateProject'])->name('projects.update');
        Route::delete('/projects/{project:ulid}', [StudioController::class, 'destroyProject'])->name('projects.destroy');

        Route::post('/projects/{project:ulid}/episodes', [StudioController::class, 'storeEpisode'])
            ->middleware('throttle:20,1')->name('episodes.store');
        Route::delete('/episodes/{episode:ulid}', [StudioController::class, 'destroyEpisode'])->name('episodes.destroy');
        Route::post('/episodes/{episode:ulid}/transcribe', [StudioController::class, 'transcribeEpisode'])->name('episodes.transcribe');
        Route::post('/episodes/{episode:ulid}/share', [StudioController::class, 'toggleShare'])->name('episodes.share');
        Route::post('/episodes/{episode:ulid}/publish', [StudioController::class, 'publishEpisode'])->name('episodes.publish');
        Route::post('/episodes/{episode:ulid}/unpublish', [StudioController::class, 'unpublishEpisode'])->name('episodes.unpublish');
        Route::get('/episodes/{episode:ulid}/download', [StudioController::class, 'download'])->name('episodes.download');

        Route::post('/music/upload', [StudioController::class, 'uploadMusic'])->name('music.upload');
        Route::post('/auto-split', [StudioController::class, 'autoSplitScript'])
            ->middleware('throttle:10,1')->name('auto-split');
    });

// Public routes
Route::middleware(['web'])
    ->prefix('podcast')
    ->name('addon.vo.public.')
    ->group(function () {
        Route::get('/rss/{token}', [PodcastController::class, 'rss'])->name('rss');
        Route::get('/player/{token}', [PodcastController::class, 'sharePlayer'])->name('player');
    });

// Admin overview
Route::middleware(['web', 'admin.auth', 'admin.permission:addon.vo.manage'])
    ->prefix('admin/voiceover')
    ->name('addon.vo.admin.')
    ->group(function () {
        Route::get('/', [VoAdminController::class, 'overview'])->name('overview');
    });

// Admin settings
Route::middleware(['web', 'admin.auth', 'admin.permission:addon.vo.settings'])
    ->prefix('admin/voiceover/settings')
    ->name('addon.vo.admin.')
    ->group(function () {
        Route::get('/', [VoSettingsController::class, 'edit'])->name('settings');
        Route::put('/', [VoSettingsController::class, 'update'])->name('settings.update');
        Route::post('/sync-voices', [VoSettingsController::class, 'syncVoices'])
            ->middleware('throttle:2,1')->name('sync-voices');
    });

<?php

declare(strict_types=1);

use Addons\AiImageEditor\Http\Controllers\Admin\ImageEditorSettingsController;
use Addons\AiImageEditor\Http\Controllers\User\ImageEditorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'addon.enabled:ai-image-editor'])
    ->prefix('image-editor')
    ->name('addon.ie.user.')
    ->group(function () {
        Route::get('/', [ImageEditorController::class, 'show'])->name('editor');
        Route::post('/upload', [ImageEditorController::class, 'show'])
            ->name('upload')
            ->middleware('throttle:10,1');

        Route::post('/apply', [ImageEditorController::class, 'apply'])
            ->name('apply')
            ->middleware('throttle:30,1');

        Route::get('/edits/{edit}/status', [ImageEditorController::class, 'status'])->name('edits.status');
        Route::post('/edits/{edit}/revert', [ImageEditorController::class, 'revert'])->name('edits.revert');
        Route::get('/edits/{edit}/download', [ImageEditorController::class, 'download'])->name('edits.download');
        Route::post('/edits/{edit}/save', [ImageEditorController::class, 'saveToLibrary'])->name('edits.save');
    });

Route::middleware(['web', 'auth:admin', 'admin.permission:addon.ie.settings'])
    ->prefix('admin/image-editor')
    ->name('addon.ie.admin.')
    ->group(function () {
        Route::get('settings', [ImageEditorSettingsController::class, 'edit'])->name('settings');
        Route::put('settings', [ImageEditorSettingsController::class, 'update']);
    });

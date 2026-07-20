<?php

use Addons\FakerAi\Http\Controllers\Admin\FakerAiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FakerAI Admin Routes
|--------------------------------------------------------------------------
|
| loadRoutesFrom() does not attach the 'web' group, so we add it here along
| with 'admin.auth' (the project's admin guard). Everything lives under the
| /admin prefix, which the CMS catch-all already excludes, so no change to
| the core route file is needed.
*/

Route::middleware(['web', 'admin.auth'])
    ->prefix('admin/faker')
    ->name('addon.faker-ai.admin.')
    ->group(function () {
        Route::get('/', [FakerAiController::class, 'index'])->name('index');
        Route::post('/generate', [FakerAiController::class, 'generate'])->name('generate');
        Route::get('/history', [FakerAiController::class, 'history'])->name('history');
        Route::delete('/batches/{batch}', [FakerAiController::class, 'destroyBatch'])->name('batches.destroy');
    });

<?php

use Addons\AiRepurposer\Http\Controllers\Admin\OverviewController;
use Addons\AiRepurposer\Http\Controllers\Admin\RepurposeSettingsController;
use Illuminate\Support\Facades\Route;

// ─── Admin Routes ─────────────────────────────────────
Route::middleware(['web', 'admin.auth'])
    ->prefix('admin/content-repurposer')
    ->name('addon.rp.admin.')
    ->group(function () {
        Route::get('/', [OverviewController::class, 'index'])->name('overview');
        Route::get('/settings', [RepurposeSettingsController::class, 'edit'])->name('settings')->middleware('admin.permission:addon.rp.settings');
        Route::put('/settings', [RepurposeSettingsController::class, 'update'])->middleware('admin.permission:addon.rp.settings');
    });

// NOTE: User routes live in routes/web.php above the CMS {slug} catch-all
// to prevent the catch-all from eating /content-repurposer URLs.

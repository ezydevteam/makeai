<?php

use App\Http\Controllers\Install\InstallController;
use Illuminate\Support\Facades\Route;

Route::prefix('install')->group(function () {
    Route::get('/', [InstallController::class, 'index'])->name('install');
    Route::post('/step/{step}', [InstallController::class, 'storeStep'])
        ->where('step', '[1-7]')
        ->name('install.step');
    Route::post('/goto-step/{step}', [InstallController::class, 'gotoStep'])
        ->where('step', '[1-7]')
        ->name('install.goto-step');
    Route::post('/finalize', [InstallController::class, 'finalize'])->name('install.finalize');
});

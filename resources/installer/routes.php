<?php

use Installer\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

Route::prefix('install')->group(function () {
    Route::get('/', [InstallController::class, 'index'])->name('install');
    Route::post('/step/{step}', [InstallController::class, 'storeStep'])
        ->where('step', '[1-7]')
        ->name('install.step');
    // Side-effect-free DB connectivity check for the Database step's "Test
    // Connection" button — validates and probes only, never touches wizard state.
    Route::post('/test-database', [InstallController::class, 'testDatabase'])
        ->name('install.test-database');
    Route::post('/goto-step/{step}', [InstallController::class, 'gotoStep'])
        ->where('step', '[1-7]')
        ->name('install.goto-step');
    Route::post('/finalize', [InstallController::class, 'finalize'])->name('install.finalize');
});

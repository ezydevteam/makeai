<?php

use Addons\PublicKnowledgeBase\Http\Controllers\Public\KbWidgetController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api'])
    ->prefix('api/kb-widget')
    ->name('addon.kb.widget.')
    ->group(function () {
        Route::post('/search', [KbWidgetController::class, 'search'])
            ->middleware('throttle:10,1')
            ->name('search');

        Route::options('/search', fn () => response('', 204, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
        ]));
    });

<?php

use Addons\AiKnowledgeBase\Http\Controllers\Public\KbWidgetController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api'])
    ->prefix('api/kb-widget')
    ->name('addon.kb.widget.')
    ->group(function () {
        // See the note in routes/web.php: `throttle` here is ThrottleAiRequests, whose
        // signature is {category},{max},{windowSeconds}. `throttle:10,1` meant category
        // "10", max ONE attempt — the embedded widget 429'd on the visitor's second
        // keystroke-debounced query.
        Route::post('/search', [KbWidgetController::class, 'search'])
            ->middleware('throttle:public,60,60')
            ->name('search');

        Route::options('/search', fn () => response('', 204, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
        ]));
    });

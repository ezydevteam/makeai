<?php

declare(strict_types=1);

use Addons\AiVoiceover\Http\Controllers\User\StudioController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth', \Addons\AiVoiceover\Http\Middleware\VoiceoverAccess::class])
    ->prefix('voiceover-studio/api')
    ->name('addon.vo.api.')
    ->group(function () {
        Route::get('/episodes/{episode:ulid}/status', [StudioController::class, 'episodeStatus'])
            ->name('episodes.status');
    });

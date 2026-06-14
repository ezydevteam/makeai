<?php

use Addons\AiAssistant\Controllers\AiAssistantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AI Assistant Routes
|--------------------------------------------------------------------------
|
| Routes loaded via loadRoutesFrom() do NOT automatically get the 'web'
| middleware group. We must wrap them explicitly so that sessions, cookies,
| and CSRF are available — otherwise auth guards return null.
|
*/

Route::middleware('web')->group(function () {

    // Frontend chat — requires regular auth
    Route::middleware(['auth', 'throttle:30,1'])->group(function () {
        Route::post('/api/assistant/chat', [AiAssistantController::class, 'chat'])
            ->name('addon.ai-assistant.chat');
        Route::post('/api/assistant/feedback', [AiAssistantController::class, 'feedback'])
            ->name('addon.ai-assistant.feedback');
        Route::post('/api/assistant/extract', [AiAssistantController::class, 'extractText'])
            ->name('addon.ai-assistant.extract');
    });

    // Admin chat — requires admin auth (uses project's AdminAuth middleware)
    Route::middleware(['admin.auth', 'throttle:30,1'])->group(function () {
        Route::post('/api/admin/assistant/chat', [AiAssistantController::class, 'adminChat'])
            ->name('addon.ai-assistant.admin.chat');
        Route::post('/api/admin/assistant/extract', [AiAssistantController::class, 'extractText'])
            ->name('addon.ai-assistant.admin.extract');

        // Automation rules management
        Route::post('/api/admin/assistant/rules', [AiAssistantController::class, 'storeRule'])
            ->name('addon.ai-assistant.admin.rules.store');
        Route::put('/api/admin/assistant/rules/{rule}', [AiAssistantController::class, 'updateRule'])
            ->name('addon.ai-assistant.admin.rules.update');
        Route::delete('/api/admin/assistant/rules/{rule}', [AiAssistantController::class, 'deleteRule'])
            ->name('addon.ai-assistant.admin.rules.delete');
    });

});

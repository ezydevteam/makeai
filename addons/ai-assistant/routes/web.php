<?php

use Addons\AiAssistant\Controllers\AiAssistantController;
use Illuminate\Support\Facades\Route;

// Frontend chat — requires regular auth
Route::middleware(['auth', 'throttle:30,1'])->group(function () {
    Route::post('/api/assistant/chat', [AiAssistantController::class, 'chat'])
        ->name('addon.ai-assistant.chat');
    Route::post('/api/assistant/feedback', [AiAssistantController::class, 'feedback'])
        ->name('addon.ai-assistant.feedback');
});

// Admin chat — requires admin auth
Route::middleware(['auth:admin', 'throttle:30,1'])->group(function () {
    Route::post('/api/admin/assistant/chat', [AiAssistantController::class, 'adminChat'])
        ->name('addon.ai-assistant.admin.chat');
});

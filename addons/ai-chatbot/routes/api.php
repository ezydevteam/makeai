<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Addons\AiChatbot\Http\Controllers\ChatController;
use Addons\AiChatbot\Http\Controllers\ChatAttachmentController;
use Addons\AiChatbot\Http\Controllers\ChatFeedbackController;
use Addons\AiChatbot\Http\Controllers\ChatModeController;
use Addons\AiChatbot\Http\Controllers\ChatProjectController;
use Addons\AiChatbot\Http\Controllers\ConversationTagController;

Route::middleware(['api'])->prefix('api/v1')->group(function () {
    Route::get('/chat/modes', [ChatModeController::class, 'index']);

    Route::middleware(['web', 'throttle:public,180,60', \Addons\AiChatbot\Http\Middleware\ChatGuestAccess::class])->prefix('chat')->group(function () {
        Route::post('/attachments', [ChatAttachmentController::class, 'store'])->middleware('throttle:public,20,60');
        Route::get('/attachments/{id}/preview', [ChatAttachmentController::class, 'preview']);
        Route::post('/feedback', [ChatFeedbackController::class, 'store']);
        Route::get('/{ulid}/feedback', [ChatFeedbackController::class, 'index']);
        Route::put('/settings', [ChatController::class, 'updateSettings']);
        Route::get('/', [ChatController::class, 'index']);
        Route::post('/', [ChatController::class, 'store']);
        Route::get('/projects', [ChatProjectController::class, 'index']);
        Route::post('/projects', [ChatProjectController::class, 'store']);
        Route::put('/projects/{project}', [ChatProjectController::class, 'update']);
        Route::delete('/projects/{project}', [ChatProjectController::class, 'destroy']);
        Route::get('/tags', [ConversationTagController::class, 'index']);
        Route::post('/tags', [ConversationTagController::class, 'store']);
        Route::put('/tags/{id}', [ConversationTagController::class, 'update']);
        Route::delete('/tags/{id}', [ConversationTagController::class, 'destroy']);
        Route::put('/{ulid}/tags', [ConversationTagController::class, 'tagConversation']);
        Route::get('/{ulid}', [ChatController::class, 'show']);
        Route::get('/{ulid}/export', [ChatController::class, 'export']);
        Route::post('/{ulid}/message', [ChatController::class, 'sendMessage'])->middleware('throttle:text_gen,30,60');
        Route::post('/{ulid}/branch', [ChatController::class, 'branch']);
        Route::put('/{ulid}/message/{messageId}', [ChatController::class, 'editMessage']);
        Route::put('/{ulid}/pin', [ChatController::class, 'togglePin']);
        Route::post('/{ulid}/share', [ChatController::class, 'share']);
        Route::delete('/{ulid}/share', [ChatController::class, 'unshare']);
        Route::put('/{ulid}', [ChatController::class, 'update']);
        Route::delete('/{ulid}', [ChatController::class, 'destroy']);
    });

    // Public share route (no auth required)
    Route::get('/share/{token}', [ChatController::class, 'sharedView']);
});

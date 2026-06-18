<?php

use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\AI\AiController;
use App\Http\Controllers\AI\DocumentController;
use App\Http\Controllers\AI\GenerateController;
use App\Http\Controllers\AI\ToolCatalogController;
use App\Http\Controllers\AI\ToolReviewController;
use App\Http\Controllers\Api\ChatAttachmentController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ChatFeedbackController;
use App\Http\Controllers\Api\ChatProductController;
use App\Http\Controllers\Api\ChatProjectController;
use App\Http\Controllers\Api\ConversationTagController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| MakeAI REST API v1 — authenticated via Sanctum.
|
*/

Route::prefix('v1')->group(function () {

    // ─── AI Endpoints (auth required) ───────
    Route::middleware('auth:sanctum')->prefix('ai')->group(function () {
        Route::post('complete', [AiController::class, 'complete']);
        Route::post('template/{template}', [AiController::class, 'runTemplate']);
        Route::get('templates', [AiController::class, 'listTemplates']);

        // Chat
        Route::post('chat', [AiController::class, 'createChat']);
        Route::get('chats', [AiController::class, 'listChats']);
        Route::post('chat/{chat}/message', [AiController::class, 'chatMessage']);
    });

    // ─── Generation Endpoints (credits check handles auth for non-public) ─────
    Route::middleware(['web', 'throttle:text_gen', 'check.credits'])->prefix('generate')->group(function () {
        Route::post('stream', [GenerateController::class, 'stream']);    // SSE streaming
        Route::post('text', [GenerateController::class, 'text']);       // Sync JSON
    });
    Route::middleware(['web', 'throttle:text_gen'])->prefix('generate')->group(function () {
        Route::get('estimate', [GenerateController::class, 'estimate']); // Cost estimate
    });

    // ─── Public Tool Catalog ─────
    Route::prefix('tools')->group(function () {
        Route::get('/', [ToolCatalogController::class, 'index']);
        Route::get('categories', [ToolCatalogController::class, 'categories']);
        Route::get('{slug}/reviews', [ToolReviewController::class, 'index']);
        Route::get('{slug}', [ToolCatalogController::class, 'show']);
    });

    // ─── Tool Review Mutations (auth required) ─────
    Route::middleware('auth:sanctum')->prefix('tools')->group(function () {
        Route::post('{slug}/reviews', [ToolReviewController::class, 'store']);
        Route::post('reviews/{review}/vote', [ToolReviewController::class, 'vote']);
    });

    Route::middleware(['web', 'auth'])->post('documents', [DocumentController::class, 'store']);

    Route::middleware(['web', 'auth'])->prefix('affiliate')->group(function () {
        Route::get('/', [AffiliateController::class, 'api']);
        Route::get('referrals', [AffiliateController::class, 'referralsApi']);
        Route::get('commissions', [AffiliateController::class, 'commissionsApi']);
        Route::get('payouts', [AffiliateController::class, 'payoutsApi']);
        Route::post('payouts', [AffiliateController::class, 'storePayout']);
    });

    // ─── Chat Endpoints ─────
    Route::get('/chat/products', [ChatProductController::class, 'index']);

    Route::middleware(['web', 'auth'])->prefix('chat')->group(function () {
        Route::post('/attachments', [ChatAttachmentController::class, 'store']);
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
        Route::post('/{ulid}/message', [ChatController::class, 'sendMessage']);
        Route::post('/{ulid}/branch', [ChatController::class, 'branch']);
        Route::put('/{ulid}/message/{messageId}', [ChatController::class, 'editMessage']);
        Route::put('/{ulid}/pin', [ChatController::class, 'togglePin']);
        Route::post('/{ulid}/share', [ChatController::class, 'share']);
        Route::delete('/{ulid}/share', [ChatController::class, 'unshare']);
        Route::put('/{ulid}', [ChatController::class, 'update']);
        Route::delete('/{ulid}', [ChatController::class, 'destroy']);
    });
});

// Public share route (no auth required)
Route::get('/share/{token}', [ChatController::class, 'sharedView']);

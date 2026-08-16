<?php

use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\GenerateController;
use App\Http\Controllers\Api\V1\ToolCatalogController;
use App\Http\Controllers\Api\V1\ToolReviewController;
use App\Http\Controllers\User\AffiliateController;
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
        // Generation endpoints — sliding-window rate limited per user/tier.
        // ThrottleAiRequests also emits X-RateLimit-Limit/Remaining/Reset headers.
        Route::middleware('throttle:text_gen')->group(function () {
            Route::post('complete', [AiController::class, 'complete']);
            Route::post('template/{template}', [AiController::class, 'runTemplate']);
            Route::post('chat/{chat}/message', [AiController::class, 'chatMessage']);
        });

        // Read / lightweight-mutation endpoints — a looser general limit so listing
        // chats or creating a chat session doesn't burn the generation budget.
        Route::middleware('throttle:public,60,60')->group(function () {
            Route::get('templates', [AiController::class, 'listTemplates']);
            Route::post('chat', [AiController::class, 'createChat']);
            Route::get('chats', [AiController::class, 'listChats']);
        });
    });

    // ─── Generation Endpoints (credits check handles auth for non-public) ─────
    Route::middleware(['web', 'throttle:text_gen', 'check.credits'])->prefix('generate')->group(function () {
        Route::post('stream', [GenerateController::class, 'stream']);    // SSE streaming
        Route::post('text', [GenerateController::class, 'text']);       // Sync JSON
    });
    // Deliberately NOT throttle:text_gen. This is a read-only cost calculation — no
    // provider call, nothing spent — but it fires on page load and again on every model or
    // length change, so sharing the generation counter meant browsing tool pages spent the
    // generation allowance. The seeded guest rule is 5 per HOUR, so a signed-out visitor
    // could exhaust it without generating anything once, and then watch Generate fail.
    Route::middleware(['web', 'throttle:public,60,60'])->prefix('generate')->group(function () {
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
    Route::middleware(['auth:sanctum', 'throttle:public,30,60'])->prefix('tools')->group(function () {
        Route::post('{slug}/reviews', [ToolReviewController::class, 'store']);
        Route::post('reviews/{review}/vote', [ToolReviewController::class, 'vote']);
    });

    Route::middleware(['web', 'auth'])->post('documents', [DocumentController::class, 'store']);

    Route::middleware(['web', 'auth', 'affiliate'])->prefix('affiliate')->group(function () {
        Route::get('/', [AffiliateController::class, 'api']);
        Route::get('referrals', [AffiliateController::class, 'referralsApi']);
        Route::get('commissions', [AffiliateController::class, 'commissionsApi']);
        Route::get('payouts', [AffiliateController::class, 'payoutsApi']);
        Route::post('payouts', [AffiliateController::class, 'storePayout']);
    });
});

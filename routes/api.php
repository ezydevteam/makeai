<?php

use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\AI\AiController;
use App\Http\Controllers\AI\DocumentController;
use App\Http\Controllers\AI\GenerateController;
use App\Http\Controllers\AI\ToolCatalogController;
use App\Http\Controllers\AI\ToolReviewController;
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

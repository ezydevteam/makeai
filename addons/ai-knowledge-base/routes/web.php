<?php

use Addons\AiKnowledgeBase\Http\Controllers\Admin\KbAnalyticsController;
use Addons\AiKnowledgeBase\Http\Controllers\Admin\KbArticleController;
use Addons\AiKnowledgeBase\Http\Controllers\Admin\KbCategoryController;
use Addons\AiKnowledgeBase\Http\Controllers\Admin\KbSettingsController;
use Addons\AiKnowledgeBase\Http\Controllers\Public\KbArticleController as PublicKbArticleController;
use Addons\AiKnowledgeBase\Http\Controllers\Public\KbHomeController;
use Addons\AiKnowledgeBase\Http\Controllers\Public\KbSearchController;
use Addons\AiKnowledgeBase\Http\Controllers\Public\KbSitemapController;
use Addons\AiKnowledgeBase\Http\Controllers\Public\KbVoteController;
use Illuminate\Support\Facades\Route;

$publicSlug = addon_setting('ai-knowledge-base', 'public_slug', 'help');

// SEO sitemap — anonymous/crawler accessible (NOT behind the guest-search gate),
// gated only by the master enable flag inside the controller.
Route::middleware(['web'])
    ->get($publicSlug . '/sitemap.xml', [KbSitemapController::class, 'index'])
    ->name('addon.kb.public.sitemap');

Route::middleware(['web', \Addons\AiKnowledgeBase\Http\Middleware\KbEnabledMiddleware::class])
    ->prefix($publicSlug)
    ->name('addon.kb.public.')
    ->group(function () {
        Route::get('/', [KbHomeController::class, 'index'])->name('home');
        Route::get('/articles', [PublicKbArticleController::class, 'index'])->name('articles');
        Route::get('/article/{slug}', [PublicKbArticleController::class, 'show'])->name('article');
        // `throttle` is aliased to App\Http\Middleware\ThrottleAiRequests, NOT Laravel's —
        // its signature is {category},{max},{windowSeconds}. The old `throttle:20,1` parsed
        // as category "20" with a max of ONE attempt, so a visitor's second search 429'd.
        // Search is a plain DB read (no AI, no credits), so it gets a generous ceiling.
        Route::post('/search', [KbSearchController::class, 'search'])
            ->middleware('throttle:public,30,60')->name('search');
        Route::post('/vote/{article}', [KbVoteController::class, 'store'])->name('vote');
    });

Route::middleware(['web', 'admin.auth'])
    ->prefix('admin/kb')
    ->name('addon.kb.admin.')
    ->group(function () {
        Route::resource('categories', KbCategoryController::class)
            ->except(['show', 'create', 'edit']);

        Route::resource('articles', KbArticleController::class);
        Route::post('articles/{article}/re-embed', [KbArticleController::class, 'reEmbed'])
            ->name('articles.re-embed');
        Route::get('analytics', [KbAnalyticsController::class, 'index'])->name('analytics');
        Route::get('settings', [KbSettingsController::class, 'edit'])->name('settings');
        Route::put('settings', [KbSettingsController::class, 'update']);
    });

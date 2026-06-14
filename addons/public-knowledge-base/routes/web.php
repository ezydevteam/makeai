<?php

use Addons\PublicKnowledgeBase\Http\Controllers\Admin\KbAnalyticsController;
use Addons\PublicKnowledgeBase\Http\Controllers\Admin\KbArticleController;
use Addons\PublicKnowledgeBase\Http\Controllers\Admin\KbCategoryController;
use Addons\PublicKnowledgeBase\Http\Controllers\Admin\KbSettingsController;
use Addons\PublicKnowledgeBase\Http\Controllers\Public\KbArticleController as PublicKbArticleController;
use Addons\PublicKnowledgeBase\Http\Controllers\Public\KbHomeController;
use Addons\PublicKnowledgeBase\Http\Controllers\Public\KbSearchController;
use Addons\PublicKnowledgeBase\Http\Controllers\Public\KbVoteController;
use Illuminate\Support\Facades\Route;

$publicSlug = addon_setting('public-knowledge-base', 'public_slug', 'help');

Route::middleware(['web', \Addons\PublicKnowledgeBase\Http\Middleware\KbEnabledMiddleware::class])
    ->prefix($publicSlug)
    ->name('addon.kb.public.')
    ->group(function () {
        Route::get('/', [KbHomeController::class, 'index'])->name('home');
        Route::get('/article/{slug}', [PublicKbArticleController::class, 'show'])->name('article');
        Route::post('/search', [KbSearchController::class, 'search'])
            ->middleware('throttle:20,1')->name('search');
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

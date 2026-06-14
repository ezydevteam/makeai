<?php

use Addons\SocialScheduler\Http\Controllers\Admin\SsAdminOverviewController;
use Addons\SocialScheduler\Http\Controllers\Admin\SsAdminSettingsController;
use Addons\SocialScheduler\Http\Controllers\Admin\SsApprovalController;
use Addons\SocialScheduler\Http\Controllers\User\BestTimeController;
use Addons\SocialScheduler\Http\Controllers\User\CaptionController;
use Addons\SocialScheduler\Http\Controllers\User\SsAccountController;
use Addons\SocialScheduler\Http\Controllers\User\SsAnalyticsController;
use Addons\SocialScheduler\Http\Controllers\User\SsCalendarController;
use Addons\SocialScheduler\Http\Controllers\User\SsPostController;
use Addons\SocialScheduler\Http\Controllers\User\SsRssFeedController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'admin.auth'])
    ->prefix('admin/social-scheduler')
    ->name('addon.social.admin.')
    ->group(function () {
        Route::get('/', [SsAdminOverviewController::class, 'index'])
            ->name('overview')
            ->middleware('admin.permission:addon.social.manage');

        Route::middleware('admin.permission:addon.social.approve')->group(function () {
            Route::get('approval', [SsApprovalController::class, 'index'])->name('approval.index');
            Route::post('approval/{post}/approve', [SsApprovalController::class, 'approve'])->name('approval.approve');
            Route::post('approval/{post}/reject', [SsApprovalController::class, 'reject'])->name('approval.reject');
        });

        Route::middleware('admin.permission:addon.social.settings')->group(function () {
            Route::get('settings', [SsAdminSettingsController::class, 'edit'])->name('settings');
            Route::put('settings', [SsAdminSettingsController::class, 'update']);
        });
    });

Route::middleware(['web', 'auth'])
    ->prefix('social')
    ->name('addon.social.user.')
    ->group(function () {
        Route::get('calendar', [SsCalendarController::class, 'index'])->name('calendar');
        Route::get('calendar/events', [SsCalendarController::class, 'events'])->name('calendar.events');
        Route::patch('posts/{post}/reschedule', [SsCalendarController::class, 'reschedule'])->name('posts.reschedule');

        Route::resource('posts', SsPostController::class)->except(['show']);

        Route::get('accounts', [SsAccountController::class, 'index'])->name('accounts');
        Route::get('accounts/{platform}/connect', [SsAccountController::class, 'redirect'])->name('accounts.redirect');
        Route::get('accounts/{platform}/callback', [SsAccountController::class, 'callback'])->name('accounts.callback');
        Route::delete('accounts/{account}', [SsAccountController::class, 'disconnect'])->name('accounts.disconnect');

        Route::get('analytics', [SsAnalyticsController::class, 'index'])->name('analytics');

        Route::resource('rss-feeds', SsRssFeedController::class)->except(['show', 'create', 'edit']);

        Route::post('caption/generate', [CaptionController::class, 'generate'])
            ->middleware('throttle:20,1')->name('caption.generate');

        Route::post('best-time', [BestTimeController::class, 'suggest'])
            ->middleware('throttle:10,1')->name('best-time');
    });

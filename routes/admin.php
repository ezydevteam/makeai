<?php

use App\Http\Controllers\Admin\AdController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\AdminNoteController;
use App\Http\Controllers\Admin\AdminTwoFactorController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\AffiliateController;
use App\Http\Controllers\Admin\AiAccessController;
use App\Http\Controllers\Admin\AiManagementController;
use App\Http\Controllers\Admin\AiToolCategoryController;
use App\Http\Controllers\Admin\AiToolController;
use App\Http\Controllers\Admin\AiUsageLogController;
use App\Http\Controllers\Admin\AppearanceController;
use App\Http\Controllers\Admin\BlogCategoryController;
use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\Admin\BlogSettingsController;
use App\Http\Controllers\Admin\BlogTagController;
use App\Http\Controllers\Admin\CMS\AnnouncementController;
use App\Http\Controllers\Admin\CommentModerationController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\ContactSettingsController;
use App\Http\Controllers\Admin\FeatureSettingsController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\ExportCenterController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\FooterBuilderController;
use App\Http\Controllers\Admin\GdprSettingsController;
use App\Http\Controllers\Admin\GeneralSettingsController;
use App\Http\Controllers\Admin\HeaderBuilderController;
use App\Http\Controllers\Admin\HomepageBuilderController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\LicenseSettingsController;
use App\Http\Controllers\Admin\MailController;
use App\Http\Controllers\Admin\MailLogController;
use App\Http\Controllers\Admin\MailTemplateController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\NewsletterController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\RateLimitController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SidebarBuilderController;
use App\Http\Controllers\Admin\SiteTemplateController;
use App\Http\Controllers\Admin\SocialSettingsController;
use App\Http\Controllers\Admin\Support\CannedResponseController as SupportCannedResponseController;
use App\Http\Controllers\Admin\Support\DepartmentController as SupportDepartmentController;
use App\Http\Controllers\Admin\Support\SettingsController as SupportSettingsController;
use App\Http\Controllers\Admin\Support\TicketController as SupportTicketController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\Admin\ThemeAddonController;
use App\Http\Controllers\Admin\TranslationController;
use App\Http\Controllers\Admin\UserManagementController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| All admin panel routes. Prefixed with /admin.
| Auth routes are public, dashboard routes require admin.auth middleware.
|
*/

// ─── Guest (unauthenticated) ────────────────────────
Route::middleware('guest:admin')->group(function () {
    Route::get('login', [AdminLoginController::class, 'showLoginForm'])->name('admin.login');
    Route::post('login', [AdminLoginController::class, 'login'])->middleware('throttle:auth')->name('admin.login.attempt');

    Route::get('forgot-password', [AdminLoginController::class, 'showForgotPasswordForm'])->name('admin.password.request');
    Route::post('forgot-password', [AdminLoginController::class, 'sendPasswordResetOtp'])->middleware('throttle:otp,3,3600')->name('admin.password.email');
    Route::get('reset-password', [AdminLoginController::class, 'showResetPasswordForm'])->name('admin.password.reset');
    Route::post('reset-password/verify', [AdminLoginController::class, 'verifyPasswordResetOtp'])->middleware('throttle:otp,5,900')->name('admin.password.verify');
    Route::post('reset-password', [AdminLoginController::class, 'resetPassword'])->middleware('throttle:otp,5,900')->name('admin.password.update');

    // 2FA
    Route::get('2fa', [AdminLoginController::class, 'show2fa'])->name('admin.2fa.show');
    Route::post('2fa', [AdminLoginController::class, 'verify2fa'])->middleware('throttle:otp')->name('admin.2fa.verify');
});

// ─── Authenticated ──────────────────────────────────
Route::middleware('admin.auth')->group(function () {
    Route::post('logout', [AdminLoginController::class, 'logout'])->name('admin.logout');

    // Dashboard
    Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

    // Admin Notes
    Route::get('notes', [AdminNoteController::class, 'index'])->name('admin.notes.index');
    Route::post('notes', [AdminNoteController::class, 'store'])->name('admin.notes.store');
    Route::post('notes/{note}', [AdminNoteController::class, 'update'])->name('admin.notes.update');
    Route::delete('notes/{note}', [AdminNoteController::class, 'destroy'])->name('admin.notes.delete');

    // Themes
    Route::middleware('admin.permission:addons.view')->group(function () {
        Route::get('appearance/themes', [ThemeAddonController::class, 'themes'])->name('admin.themes');
        Route::post('appearance/themes/{slug}/activate', [ThemeAddonController::class, 'activateTheme'])->name('admin.themes.activate');
        Route::get('appearance/themes/{slug}/settings', [ThemeAddonController::class, 'themeSettings'])->name('admin.themes.settings');
        Route::post('appearance/themes/{slug}/settings', [ThemeAddonController::class, 'saveThemeSettings'])->name('admin.themes.settings.save');

        // Addons
        Route::get('appearance/addons', [ThemeAddonController::class, 'addons'])->name('admin.addons');
        Route::get('appearance/addons/{slug}/logo', [ThemeAddonController::class, 'addonLogo'])->name('admin.addons.logo');
        Route::post('appearance/addons/{slug}/verify-license', [ThemeAddonController::class, 'verifyAddonLicense'])->name('admin.addons.verify-license')->middleware('throttle:public,5,60');
        Route::post('appearance/addons/{slug}/activate', [ThemeAddonController::class, 'activateAddon'])->name('admin.addons.activate');
        Route::post('appearance/addons/{slug}/deactivate', [ThemeAddonController::class, 'deactivateAddon'])->name('admin.addons.deactivate');
        Route::delete('appearance/addons/{slug}', [ThemeAddonController::class, 'deleteAddon'])->name('admin.addons.delete');
        Route::post('appearance/addons/upload', [ThemeAddonController::class, 'installAddon'])->name('admin.addons.upload');
        Route::get('appearance/addons/{slug}/settings', [ThemeAddonController::class, 'addonSettings'])->name('admin.addons.settings');
        Route::post('appearance/addons/{slug}/settings', [ThemeAddonController::class, 'saveAddonSettings'])->name('admin.addons.settings.save');
    });

    // Users Management
    Route::get('roles/users', [UserManagementController::class, 'index'])->name('admin.users.index');
    Route::get('roles/users/trash', [UserManagementController::class, 'trash'])->name('admin.users.trash');
    Route::get('roles/users/create', [UserManagementController::class, 'create'])->name('admin.users.create');
    Route::post('roles/users', [UserManagementController::class, 'store'])->name('admin.users.store');
    Route::get('roles/users/export', [UserManagementController::class, 'export'])->name('admin.users.export');
    Route::post('roles/users/bulk', [UserManagementController::class, 'bulkAction'])->name('admin.users.bulk');
    Route::post('roles/users/trash/bulk', [UserManagementController::class, 'bulkTrashAction'])->name('admin.users.trash.bulk');
    Route::post('roles/users/stop-impersonating', [UserManagementController::class, 'stopImpersonating'])->name('admin.users.stop_impersonating');
    Route::post('roles/users/{user}/restore', [UserManagementController::class, 'restore'])->withTrashed()->name('admin.users.restore');
    Route::delete('roles/users/{user}/force-delete', [UserManagementController::class, 'forceDelete'])->withTrashed()->name('admin.users.force-delete');
    Route::get('roles/users/{user}', [UserManagementController::class, 'show'])->name('admin.users.show');
    Route::post('roles/users/{user}', [UserManagementController::class, 'update'])->name('admin.users.update');
    Route::delete('roles/users/{user}', [UserManagementController::class, 'destroy'])->name('admin.users.delete');
    Route::post('roles/users/{user}/notification', [UserManagementController::class, 'sendNotification'])->name('admin.users.notification');
    Route::post('roles/users/{user}/two-factor/disable', [UserManagementController::class, 'disableTwoFactor'])->name('admin.users.2fa.disable');
    Route::post('roles/users/{user}/impersonate', [UserManagementController::class, 'impersonate'])->name('admin.users.impersonate');

    // Administrators & Roles
    Route::get('roles/admins', [AdminController::class, 'index'])->name('admin.admins.index');
    Route::get('roles/admins/trash', [AdminController::class, 'trash'])->name('admin.admins.trash');
    Route::post('roles/admins', [AdminController::class, 'store'])->name('admin.admins.store');
    Route::post('roles/admins/trash/bulk', [AdminController::class, 'bulkTrashAction'])->name('admin.admins.trash.bulk');
    Route::post('roles/admins/{admin}', [AdminController::class, 'update'])->name('admin.admins.update');
    Route::post('roles/admins/{admin}/restore', [AdminController::class, 'restore'])->withTrashed()->name('admin.admins.restore');
    Route::delete('roles/admins/{admin}', [AdminController::class, 'destroy'])->name('admin.admins.delete');
    Route::delete('roles/admins/{admin}/force-delete', [AdminController::class, 'forceDelete'])->withTrashed()->name('admin.admins.force-delete');

    Route::get('security/two-factor', [AdminTwoFactorController::class, 'show'])->name('admin.security.2fa.show');
    Route::post('security/two-factor', [AdminTwoFactorController::class, 'enable'])->middleware('throttle:otp')->name('admin.security.2fa.enable');
    Route::post('security/two-factor/disable', [AdminTwoFactorController::class, 'disable'])->middleware('throttle:otp')->name('admin.security.2fa.disable');
    Route::post('security/two-factor/recovery-codes', [AdminTwoFactorController::class, 'regenerateRecoveryCodes'])->middleware('throttle:otp')->name('admin.security.2fa.recovery-codes');

    // Rate Limits
    Route::prefix('system/rate-limits')->name('admin.system.rate-limits.')->middleware('admin.permission:settings.manage')->group(function () {
        Route::get('/', [RateLimitController::class, 'index'])->name('index');
        Route::post('/tiers', [RateLimitController::class, 'updateTiers'])->name('tiers.update');
        Route::post('/ban', [RateLimitController::class, 'banIp'])->name('ban');
        Route::delete('/ban/{bannedIp}', [RateLimitController::class, 'unbanIp'])->name('unban');
        Route::post('/overrides', [RateLimitController::class, 'storeOverride'])->name('overrides.store');
        Route::delete('/overrides/{override}', [RateLimitController::class, 'deleteOverride'])->name('overrides.delete');
    });

    Route::get('roles/admins/permissions', [RoleController::class, 'index'])->name('admin.roles.index');
    Route::get('roles/admins/permissions/create', [RoleController::class, 'create'])->name('admin.roles.create');
    Route::post('roles/admins/permissions', [RoleController::class, 'store'])->name('admin.roles.store');
    Route::get('roles/admins/permissions/{role}/edit', [RoleController::class, 'edit'])->name('admin.roles.edit');
    Route::post('roles/admins/permissions/{role}/restore-default', [RoleController::class, 'restoreDefault'])->name('admin.roles.restore-default');
    Route::post('roles/admins/permissions/{role}', [RoleController::class, 'update'])->name('admin.roles.update');
    Route::delete('roles/admins/permissions/{role}', [RoleController::class, 'destroy'])->name('admin.roles.delete');

    // Localization
    Route::middleware('admin.permission:translations.manage')->group(function () {
        Route::get('localization/languages', [LanguageController::class, 'index'])->name('admin.languages.index');
        Route::post('localization/languages', [LanguageController::class, 'store'])->name('admin.languages.store');
        Route::post('localization/languages/{language}', [LanguageController::class, 'update'])->name('admin.languages.update');
        Route::post('localization/languages/{language}/default', [LanguageController::class, 'setDefault'])->name('admin.languages.default');
        Route::delete('localization/languages/{language}', [LanguageController::class, 'destroy'])->name('admin.languages.delete');

        Route::get('localization/translations/{language}', [TranslationController::class, 'index'])->name('admin.translations.index');
        Route::post('localization/translations/{language}/bulk', [TranslationController::class, 'bulkUpdate'])->name('admin.translations.bulk_update');
        Route::post('localization/translations/{translation}', [TranslationController::class, 'update'])->name('admin.translations.update');
        Route::post('localization/translations/{translation}/ai', [TranslationController::class, 'aiTranslate'])->name('admin.translations.ai');
        Route::post('localization/translations/{language}/ai-all', [TranslationController::class, 'aiTranslateAll'])->name('admin.translations.ai_all');
    });

    // Plans (Pro only controller — uses isProAvailable() internally, guard with plans.view)
    Route::middleware('admin.permission:plans.view')->group(function () {
        Route::get('premium/plans', [PlanController::class, 'index'])->name('admin.plans.index');
        Route::get('premium/plans/pricing', [PlanController::class, 'pricing'])->name('admin.plans.pricing');
        Route::post('premium/plans/settings', [PlanController::class, 'updateSettings'])->name('admin.plans.settings');
        Route::put('premium/plans/{plan}', [PlanController::class, 'update'])->name('admin.plans.update');
    });

    Route::get('premium/gateways', [PaymentGatewayController::class, 'index'])->name('admin.payment-gateways.index');
    Route::post('premium/gateways/sort', [PaymentGatewayController::class, 'sort'])->name('admin.payment-gateways.sort');
    Route::post('premium/gateways/{gateway}', [PaymentGatewayController::class, 'update'])->name('admin.payment-gateways.update');
    Route::get('premium/coupons', [CouponController::class, 'index'])->name('admin.coupons.index');
    Route::post('premium/coupons', [CouponController::class, 'store'])->name('admin.coupons.store');
    Route::post('premium/coupons/{coupon}', [CouponController::class, 'update'])->name('admin.coupons.update');
    Route::post('premium/coupons/{coupon}/header', [CouponController::class, 'toggleHeader'])->name('admin.coupons.header');
    Route::delete('premium/coupons/{coupon}', [CouponController::class, 'destroy'])->name('admin.coupons.delete');

    Route::get('marketing/affiliate', [AffiliateController::class, 'index'])->name('admin.affiliate.index');
    Route::post('marketing/affiliate/settings', [AffiliateController::class, 'updateSettings'])->name('admin.affiliate.settings');
    Route::post('marketing/affiliate/commissions/{commission}/approve', [AffiliateController::class, 'approveCommission'])->name('admin.affiliate.commissions.approve');
    Route::post('marketing/affiliate/commissions/{commission}/reject', [AffiliateController::class, 'rejectCommission'])->name('admin.affiliate.commissions.reject');
    Route::post('marketing/affiliate/payouts/{payout}', [AffiliateController::class, 'processPayout'])->name('admin.affiliate.payouts.process');

    // Export Center
    Route::middleware('admin.permission:reports.export')->group(function () {
        Route::get('reports/export-center', [ExportCenterController::class, 'index'])->name('admin.reports.export-center');
        Route::post('reports/export', [ExportCenterController::class, 'export'])->name('admin.reports.export');
        Route::post('reports/export/estimate', [ExportCenterController::class, 'estimate'])->name('admin.reports.export.estimate');
        Route::get('reports/exports/{file}', [ExportCenterController::class, 'download'])->name('admin.reports.export.download');
        Route::delete('reports/exports/{file}', [ExportCenterController::class, 'deleteFile'])->name('admin.reports.export.delete');
    });

    // Community (Newsletter)
    Route::middleware('admin.permission:users.manage')->group(function () {
        Route::get('marketing/newsletter', [NewsletterController::class, 'index'])->name('admin.newsletter.index');
        Route::post('marketing/newsletter/campaign', [NewsletterController::class, 'storeCampaign'])->name('admin.newsletter.campaign.store');
        Route::post('marketing/newsletter/campaign/{campaign}', [NewsletterController::class, 'updateCampaign'])->name('admin.newsletter.campaign.update');
        Route::delete('marketing/newsletter/campaign/{campaign}', [NewsletterController::class, 'destroyCampaign'])->name('admin.newsletter.campaign.delete');
        Route::post('marketing/newsletter/campaign/{campaign}/send', [NewsletterController::class, 'sendCampaign'])->name('admin.newsletter.campaign.send');
        Route::post('marketing/newsletter/campaign/{campaign}/test', [NewsletterController::class, 'testCampaign'])->name('admin.newsletter.campaign.test');
        Route::post('marketing/newsletter/campaign/{campaign}/retry', [NewsletterController::class, 'retryFailed'])->name('admin.newsletter.campaign.retry');
        Route::delete('marketing/newsletter/subscriber/{subscriber}', [NewsletterController::class, 'destroySubscriber'])->name('admin.newsletter.subscriber.delete');
        Route::post('marketing/newsletter/settings', [NewsletterController::class, 'saveSettings'])->name('admin.newsletter.settings.save');
    });

    // CMS: Pages
    Route::middleware('admin.permission:content.pages')->group(function () {
        Route::get('content/pages', [PageController::class, 'index'])->name('admin.pages.index');
        Route::get('content/pages/create', [PageController::class, 'create'])->name('admin.pages.create');
        Route::post('content/pages/ai-assist', [PageController::class, 'aiAssist'])->name('admin.pages.ai-assist');
        Route::post('content/pages', [PageController::class, 'store'])->name('admin.pages.store');
        Route::get('content/pages/{page}/edit', [PageController::class, 'edit'])->name('admin.pages.edit');
        Route::post('content/pages/{page}', [PageController::class, 'update'])->name('admin.pages.update');
        Route::get('content/pages/{page}/preview', [PageController::class, 'preview'])->name('admin.pages.preview');
        Route::post('content/pages/{page}/restore', [PageController::class, 'restore'])->withTrashed()->name('admin.pages.restore');
        Route::delete('content/pages/{page}/force-delete', [PageController::class, 'forceDelete'])->withTrashed()->name('admin.pages.force-delete');
        Route::delete('content/pages/{page}', [PageController::class, 'destroy'])->name('admin.pages.delete');
    });

    Route::middleware('admin.permission:content.pages')->group(function () {
        Route::get('contact/messages/export', [ContactMessageController::class, 'export'])->name('admin.contact.messages.export');
        Route::get('contact/messages', [ContactMessageController::class, 'index'])->name('admin.contact.messages.index');
        Route::post('contact/messages/{message}/read', [ContactMessageController::class, 'markRead'])->name('admin.contact.messages.read');
        Route::post('contact/messages/{message}/reply', [ContactMessageController::class, 'reply'])->name('admin.contact.messages.reply');
        Route::delete('contact/messages/{message}', [ContactMessageController::class, 'destroy'])->name('admin.contact.messages.delete');
        Route::get('cms/contact/settings', [ContactSettingsController::class, 'edit'])->name('admin.contact.settings.edit');
        Route::post('cms/contact/settings', [ContactSettingsController::class, 'update'])->name('admin.contact.settings.update');
    });

    // Support Ticket System (PART 24)
    Route::prefix('support/tickets')->name('admin.support.tickets.')->middleware('admin.permission:support.tickets')->group(function () {
        Route::get('/', [SupportTicketController::class, 'index'])->name('index');
        Route::get('/export', [SupportTicketController::class, 'export'])->name('export');
        Route::post('/bulk', [SupportTicketController::class, 'bulkAction'])->name('bulk');
        Route::get('/{ticket}', [SupportTicketController::class, 'show'])->name('show');
        Route::post('/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('reply');
        Route::post('/{ticket}/state', [SupportTicketController::class, 'updateState'])->name('state');
        Route::post('/{ticket}/suggest-reply', [SupportTicketController::class, 'suggestReply'])->name('suggest-reply');
        Route::post('/{ticket}/merge', [SupportTicketController::class, 'merge'])->name('merge');
        Route::post('/{ticket}/toggle-user-ban', [SupportTicketController::class, 'toggleUserBan'])->name('toggle-user-ban');
        Route::delete('/{ticket}', [SupportTicketController::class, 'destroy'])->name('delete');
    });

    Route::prefix('support')->name('admin.support.')->middleware('admin.permission:support.tickets')->group(function () {

        Route::resource('departments', SupportDepartmentController::class)->except(['create', 'show', 'edit']);
        Route::resource('canned-responses', SupportCannedResponseController::class)->except(['create', 'show', 'edit'])->parameters([
            'canned-responses' => 'response',
        ]);
        Route::post('canned-responses/{response}/track', [SupportCannedResponseController::class, 'track'])->name('canned-responses.track');
        Route::get('settings', [SupportSettingsController::class, 'edit'])->name('settings.edit');
        Route::post('settings', [SupportSettingsController::class, 'update'])->name('settings.update');
    });

    // Appearance: Menus
    Route::middleware('admin.permission:settings.manage')->group(function () {
        Route::get('appearance/menus', [MenuController::class, 'index'])->name('admin.menus.index');
        Route::post('appearance/menus', [MenuController::class, 'store'])->name('admin.menus.store');
        Route::post('appearance/menus/{menu}', [MenuController::class, 'update'])->name('admin.menus.update');
        Route::post('appearance/menus/{menu}/items/reorder', [MenuController::class, 'reorderItems'])->name('admin.menus.items.reorder');
        Route::post('appearance/menus/{menu}/item', [MenuController::class, 'addItem'])->name('admin.menus.item.store');
        Route::post('appearance/menus/item/{item}', [MenuController::class, 'updateItem'])->name('admin.menus.item.update');
        Route::delete('appearance/menus/item/{item}', [MenuController::class, 'deleteItem'])->name('admin.menus.item.delete');
        Route::delete('appearance/menus/{menu}', [MenuController::class, 'destroy'])->name('admin.menus.delete');
        Route::post('appearance/menus/{menu}/import', [MenuController::class, 'import'])->name('admin.menus.import');
    });

    // Appearance: Settings
    Route::middleware('admin.permission:settings.manage')->group(function () {
        Route::get('system/style', [AppearanceController::class, 'index'])->name('admin.appearance.index');
        Route::post('system/style', [AppearanceController::class, 'update'])->name('admin.appearance.update');

        // Appearance: Homepage Builder
        Route::get('builder/homepage', [HomepageBuilderController::class, 'index'])->name('admin.homepage.index');
        Route::post('builder/homepage', [HomepageBuilderController::class, 'update'])->name('admin.homepage.update');
        Route::post('builder/homepage/set', [HomepageBuilderController::class, 'setHomepage'])->name('admin.homepage.set');
        Route::post('builder/homepage/upload-media', [HomepageBuilderController::class, 'upload'])->name('admin.homepage.upload');

        // Appearance: Header Builder
        Route::get('builder/header', [HeaderBuilderController::class, 'index'])->name('admin.header.index');
        Route::post('builder/header', [HeaderBuilderController::class, 'update'])->name('admin.header.update');
        Route::post('builder/header/reset/{section}', [HeaderBuilderController::class, 'resetSection'])->name('admin.header.reset');
        Route::get('builder/header/export', [HeaderBuilderController::class, 'export'])->name('admin.header.export');
        Route::post('builder/header/upload-logo', [HeaderBuilderController::class, 'upload'])->name('admin.header.upload');

        // Appearance: Footer Builder
        Route::get('builder/footer', [FooterBuilderController::class, 'index'])->name('admin.footer.index');
        Route::post('builder/footer', [FooterBuilderController::class, 'update'])->name('admin.footer.update');

        // Appearance: Sidebar Builder
        Route::get('appearance/sidebar', [SidebarBuilderController::class, 'index'])->name('admin.sidebar.index');
        Route::post('appearance/sidebar', [SidebarBuilderController::class, 'update'])->name('admin.sidebar.update');

        // AI: Templates
        Route::prefix('ai/templates')->name('admin.ai.templates.')->group(function () {
            Route::get('/', [SiteTemplateController::class, 'index'])->name('index');
            Route::get('{template}/edit', [SiteTemplateController::class, 'edit'])->name('edit');
            Route::post('{template}', [SiteTemplateController::class, 'update'])->name('update');
            Route::post('{template}/toggle', [SiteTemplateController::class, 'toggle'])->name('toggle');
            Route::post('{template}/reset', [SiteTemplateController::class, 'resetToDefaults'])->name('reset');
            Route::post('{template}/chatbot-settings', [SiteTemplateController::class, 'saveChatbotSettings'])->name('chatbot-settings');
            Route::post('{template}/platform-settings', [SiteTemplateController::class, 'savePlatformSettings'])->name('platform-settings');
            Route::post('{template}/stage-settings', [SiteTemplateController::class, 'saveStageSettings'])->name('stage-settings');
        });
    });

    // Ads System
    Route::middleware('admin.permission:settings.manage')->group(function () {
        Route::get('marketing/ads', [AdController::class, 'index'])->name('admin.ads.index');
        Route::get('marketing/ads/create', [AdController::class, 'create'])->name('admin.ads.create');
        Route::post('marketing/ads', [AdController::class, 'store'])->name('admin.ads.store');
        Route::post('marketing/ads/settings', [AdController::class, 'updateSettings'])->name('admin.ads.settings');
        Route::get('marketing/ads/{ad}/edit', [AdController::class, 'edit'])->name('admin.ads.edit');
        Route::post('marketing/ads/{ad}', [AdController::class, 'update'])->name('admin.ads.update');
        Route::delete('marketing/ads/{ad}', [AdController::class, 'destroy'])->name('admin.ads.delete');
        Route::post('marketing/ads/{ad}/toggle', [AdController::class, 'toggle'])->name('admin.ads.toggle');
    });

    // Social Media
    Route::middleware('admin.permission:settings.manage')->group(function () {
        Route::get('marketing/social', [SocialSettingsController::class, 'editFollow'])->name('admin.social.settings.edit');
        Route::post('marketing/social', [SocialSettingsController::class, 'updateFollow'])->name('admin.social.settings.update');
        Route::get('settings/oauth', [SocialSettingsController::class, 'editOAuth'])->name('admin.oauth.settings.edit');
        Route::post('settings/oauth', [SocialSettingsController::class, 'updateOAuth'])->name('admin.oauth.settings.update');
    });

    // General Settings
    Route::get('settings', [GeneralSettingsController::class, 'edit'])->name('admin.settings.index');
    Route::post('settings', [GeneralSettingsController::class, 'update'])->name('admin.settings.update');
    Route::get('settings/features', [FeatureSettingsController::class, 'edit'])->name('admin.features.settings');
    Route::post('settings/features', [FeatureSettingsController::class, 'update'])->name('admin.features.settings.update');

    // GDPR Settings
    Route::get('settings/gdpr', [GdprSettingsController::class, 'edit'])->name('admin.gdpr.settings');
    Route::post('settings/gdpr', [GdprSettingsController::class, 'update'])->name('admin.gdpr.settings.update');

    // License (PART 03)
    Route::middleware('admin.permission:settings.license')->group(function () {
        Route::get('license', [LicenseSettingsController::class, 'edit'])->name('admin.license.settings');
        Route::post('license/activate', [LicenseSettingsController::class, 'activate'])->name('admin.license.activate');
        Route::post('license/deactivate', [LicenseSettingsController::class, 'deactivate'])->name('admin.license.deactivate');
        Route::post('license/reverify', [LicenseSettingsController::class, 'reverify'])->name('admin.license.reverify');
    });

    // System Tools
    Route::middleware('admin.permission:settings.manage')->group(function () {
        Route::get('system', [SystemController::class, 'index'])->name('admin.system.index');
        Route::get('system/health', [SystemController::class, 'health'])->name('admin.system.health');
        Route::get('system/updates', [SystemController::class, 'updates'])->name('admin.system.updates');
        Route::get('system/cron-jobs', [SystemController::class, 'tools'])->name('admin.system.cron-jobs');
        Route::get('system/maintenance', [SystemController::class, 'maintenance'])->name('admin.system.maintenance');
        Route::post('system/cache', [SystemController::class, 'clearCache'])->name('admin.system.cache.clear');
        Route::post('system/cron/run', [SystemController::class, 'runCronTask'])->name('admin.system.cron.run');
        Route::post('system/check-updates', [SystemController::class, 'checkUpdates'])->name('admin.system.check-updates');
        Route::post('system/maintenance/settings', [SystemController::class, 'updateMaintenanceSettings'])->name('admin.system.maintenance.settings');
        Route::post('system/maintenance/toggle', [SystemController::class, 'toggleMaintenance'])->name('admin.system.maintenance.toggle');
    });

    // In-App Notifications (PART 23)
    Route::get('notifications', [AdminNotificationController::class, 'index'])->name('admin.notifications.index');
    Route::get('notifications/latest', [AdminNotificationController::class, 'latest'])->name('admin.notifications.latest');
    Route::post('notifications/read-all', [AdminNotificationController::class, 'markAllRead'])->name('admin.notifications.read-all');
    Route::post('notifications/{notification}/read', [AdminNotificationController::class, 'markRead'])->name('admin.notifications.read');
    Route::get('notifications/settings', [AdminNotificationController::class, 'settings'])->name('admin.notifications.settings');
    Route::post('notifications/settings', [AdminNotificationController::class, 'updateSettings'])->name('admin.notifications.settings.update');
    Route::post('notifications/test', [AdminNotificationController::class, 'testConnection'])->name('admin.notifications.test');

    // Mail System
    Route::middleware('admin.permission:settings.mail')->group(function () {
        Route::get('mail/settings', [MailController::class, 'index'])->name('admin.mail.index');
        Route::post('mail/settings', [MailController::class, 'update'])->name('admin.mail.update');
        Route::post('mail/test', [MailController::class, 'test'])->name('admin.mail.test');

        Route::get('mail/templates', [MailTemplateController::class, 'index'])->name('admin.mail.templates.index');
        Route::get('mail/templates/create', [MailTemplateController::class, 'create'])->name('admin.mail.templates.create');
        Route::post('mail/templates', [MailTemplateController::class, 'store'])->name('admin.mail.templates.store');
        Route::get('mail/templates/{template}/edit', [MailTemplateController::class, 'edit'])->name('admin.mail.templates.edit');
        Route::post('mail/templates/{template}', [MailTemplateController::class, 'update'])->name('admin.mail.templates.update');
        Route::delete('mail/templates/{template}', [MailTemplateController::class, 'destroy'])->name('admin.mail.templates.delete');

        Route::get('mail/logs', [MailLogController::class, 'index'])->name('admin.mail.logs.index');
        Route::post('mail/logs/{log}/resend', [MailLogController::class, 'resend'])->name('admin.mail.logs.resend');
    });

    // Content: Testimonials (PART 19)
    Route::middleware('admin.permission:content.testimonials')->group(function () {
        Route::get('content/testimonials', [TestimonialController::class, 'index'])->name('admin.testimonials.index');
        Route::post('content/testimonials', [TestimonialController::class, 'store'])->name('admin.testimonials.store');
        Route::post('content/testimonials/sort', [TestimonialController::class, 'bulkSort'])->name('admin.testimonials.sort');
        Route::post('content/testimonials/import', [TestimonialController::class, 'import'])->name('admin.testimonials.import');
        Route::post('content/testimonials/generate', [TestimonialController::class, 'generate'])->name('admin.testimonials.generate');
        Route::post('content/testimonials/{testimonial}', [TestimonialController::class, 'update'])->name('admin.testimonials.update');
        Route::post('content/testimonials/{testimonial}/featured', [TestimonialController::class, 'toggleFeatured'])->name('admin.testimonials.featured');
        Route::post('content/testimonials/{testimonial}/active', [TestimonialController::class, 'toggleActive'])->name('admin.testimonials.active');
        Route::delete('content/testimonials/{testimonial}', [TestimonialController::class, 'destroy'])->name('admin.testimonials.delete');
    });

    // Content: FAQs (PART 19)
    Route::middleware('admin.permission:content.faq')->group(function () {
        Route::get('content/faqs', [FaqController::class, 'index'])->name('admin.faqs.index');
        Route::post('content/faqs', [FaqController::class, 'store'])->name('admin.faqs.store');
        Route::post('content/faqs/sort', [FaqController::class, 'bulkSort'])->name('admin.faqs.sort');
        Route::post('content/faqs/import', [FaqController::class, 'import'])->name('admin.faqs.import');
        Route::post('content/faqs/generate', [FaqController::class, 'generate'])->name('admin.faqs.generate');
        Route::post('content/faqs/{faq}', [FaqController::class, 'update'])->name('admin.faqs.update');
        Route::post('content/faqs/{faq}/active', [FaqController::class, 'toggleActive'])->name('admin.faqs.active');
        Route::delete('content/faqs/{faq}', [FaqController::class, 'destroy'])->name('admin.faqs.delete');
        Route::post('content/faq-categories', [FaqController::class, 'storeCategory'])->name('admin.faq-categories.store');
        Route::post('content/faq-categories/{category}', [FaqController::class, 'updateCategory'])->name('admin.faq-categories.update');
        Route::delete('content/faq-categories/{category}', [FaqController::class, 'destroyCategory'])->name('admin.faq-categories.delete');
    });

    // Content: Comments
    Route::middleware('admin.permission:content.comments')->group(function () {
        Route::get('content/comments', [CommentModerationController::class, 'index'])->name('admin.comments.index');
        Route::post('content/comments/settings', [CommentModerationController::class, 'updateSettings'])->name('admin.comments.settings');
        Route::post('content/comments/{comment}/approve', [CommentModerationController::class, 'approve'])->name('admin.comments.approve');
        Route::post('content/comments/{comment}/spam', [CommentModerationController::class, 'spam'])->name('admin.comments.spam');
        Route::delete('content/comments/{comment}', [CommentModerationController::class, 'destroy'])->name('admin.comments.delete');
    });

    // Content: Announcements (PART 29)
    Route::middleware('admin.permission:content.pages')->group(function () {
        Route::get('marketing/announcements', [AnnouncementController::class, 'index'])->name('admin.announcements.index');
        Route::post('marketing/announcements', [AnnouncementController::class, 'store'])->name('admin.announcements.store');
        Route::post('marketing/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('admin.announcements.update');
        Route::post('marketing/announcements/{announcement}/active', [AnnouncementController::class, 'toggleActive'])->name('admin.announcements.active');
        Route::delete('marketing/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('admin.announcements.delete');
    });

    // Content: Blog (PART 16)
    Route::prefix('content/blog')->name('admin.blog.')->middleware('admin.permission:content.blog')->group(function () {
        Route::get('posts/export', [BlogPostController::class, 'export'])->name('posts.export');
        Route::post('posts/bulk', [BlogPostController::class, 'bulk'])->name('posts.bulk');
        Route::post('posts/ai-assist', [BlogPostController::class, 'aiAssist'])->name('posts.ai-assist');
        Route::post('posts/autosave', [BlogPostController::class, 'autosave'])->name('posts.autosave');
        Route::post('posts/{post}/autosave', [BlogPostController::class, 'autosave'])->name('posts.autosave.update');
        Route::post('posts/{post}/duplicate', [BlogPostController::class, 'duplicate'])->name('posts.duplicate');
        Route::get('posts/trash', [BlogPostController::class, 'trash'])->name('posts.trash');
        Route::post('posts/{post}/restore', [BlogPostController::class, 'restore'])->withTrashed()->name('posts.restore');
        Route::delete('posts/{post}/force-delete', [BlogPostController::class, 'forceDelete'])->withTrashed()->name('posts.force-delete');
        Route::get('posts/{post}/preview', [BlogPostController::class, 'preview'])->name('posts.preview');
        Route::resource('posts', BlogPostController::class)->except(['show']);

        Route::delete('tags/unused', [BlogTagController::class, 'deleteUnused'])->name('tags.unused.delete');
        Route::resource('categories', BlogCategoryController::class)->except(['create', 'show', 'edit']);
        Route::resource('tags', BlogTagController::class)->except(['create', 'show', 'edit']);

        Route::get('settings', [BlogSettingsController::class, 'edit'])->name('settings.edit');
        Route::post('settings', [BlogSettingsController::class, 'update'])->name('settings.update');
    });

    // AI Tools Management
    Route::prefix('ai')->name('admin.ai.')->middleware('admin.permission:ai.tools')->group(function () {
        Route::get('/providers', [AiManagementController::class, 'index'])->name('index');
        Route::get('/provider/{slug}', [AiManagementController::class, 'provider'])->name('provider');
        Route::get('/integrations', [AiManagementController::class, 'integrations'])->name('integrations.index');
        Route::post('/settings', [AiManagementController::class, 'updateSettings'])->name('settings.update');
        Route::post('/integrations', [AiManagementController::class, 'updateIntegrations'])->name('integrations.update');
        Route::post('/integrations/{integration}/test-connection', [AiManagementController::class, 'testIntegrationConnection'])->name('integrations.test-connection');
        Route::post('/provider/{provider}/key', [AiManagementController::class, 'storeKey'])->name('key.store');
        Route::delete('/key/{key}', [AiManagementController::class, 'deleteKey'])->name('key.delete');
        Route::post('/model/{model}', [AiManagementController::class, 'updateModel'])->name('model.update');
        Route::post('/provider/{slug}/test-connection', [AiManagementController::class, 'testConnection'])->name('provider.test-connection');

        // Access Settings
        Route::get('/access', [AiAccessController::class, 'index'])->name('access.index');
        Route::post('/access/bulk', [AiAccessController::class, 'bulkUpdate'])->name('access.bulk');
        Route::post('/access/category', [AiAccessController::class, 'categoryUpdate'])->name('access.category');
        Route::post('/access/preset', [AiAccessController::class, 'presetUpdate'])->name('access.preset');

        // RAG Settings
        Route::get('/rag', [AiManagementController::class, 'ragSettings'])->name('rag.index');
        Route::post('/rag', [AiManagementController::class, 'updateRagSettings'])->name('rag.update');

        // Usage Logs
        Route::get('/logs', [AiUsageLogController::class, 'index'])->name('logs.index');

        // Tools (Full CRUD)
        Route::post('/tools/{tool}/toggle', [AiToolController::class, 'toggle'])->name('tools.toggle');
        Route::post('/tools/reviews/{review}/action', [AiToolController::class, 'reviewAction'])->name('tools.reviews.action');
        Route::post('/categories/{category}/toggle-active', [AiToolCategoryController::class, 'toggleActive'])->name('categories.toggle-active');
        Route::post('/categories/{category}/toggle-pro', [AiToolCategoryController::class, 'togglePro'])->name('categories.toggle-pro');
        Route::post('/categories/{category}/toggle-login', [AiToolCategoryController::class, 'toggleLogin'])->name('categories.toggle-login');
        Route::resource('categories', AiToolCategoryController::class)->except(['create', 'show', 'edit']);
        Route::resource('tools', AiToolController::class);
    });
});

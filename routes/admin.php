<?php

use App\Http\Controllers\Admin\Marketing\Advertisements\AdController;
use App\Http\Controllers\Admin\AccountSettingsController;
use App\Http\Controllers\Admin\Activity\AdminLogController;
use App\Http\Controllers\Admin\Activity\UserLogController;
use App\Http\Controllers\Admin\Roles\Admins\AdminController;
use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\AdminNoteController;
use App\Http\Controllers\Admin\AdminTwoFactorController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\Marketing\AffiliateController;
use App\Http\Controllers\Admin\AI\AiAccessController;
use App\Http\Controllers\Admin\AI\AiManagementController;
use App\Http\Controllers\Admin\AI\AiToolCategoryController;
use App\Http\Controllers\Admin\AI\AiToolController;
use App\Http\Controllers\Admin\AI\AiUsageLogController;
use App\Http\Controllers\Admin\AI\ToolReviewController;
use App\Http\Controllers\Admin\CMS\Blog\BlogCategoryController;
use App\Http\Controllers\Admin\CMS\Blog\BlogPostController;
use App\Http\Controllers\Admin\CMS\Blog\BlogSettingsController;
use App\Http\Controllers\Admin\CMS\Blog\BlogTagController;
use App\Http\Controllers\Admin\Marketing\AnnouncementController;
use App\Http\Controllers\Admin\CMS\Blog\CommentModerationController;
use App\Http\Controllers\Admin\Contact\ContactMessageController;
use App\Http\Controllers\Admin\Contact\ContactSettingsController;
use App\Http\Controllers\Admin\Settings\FeatureSettingsController;
use App\Http\Controllers\Admin\ExportCenterController;
use App\Http\Controllers\Admin\CMS\FaqController;
use App\Http\Controllers\Admin\Settings\GdprSettingsController;
use App\Http\Controllers\Admin\Settings\GeneralSettingsController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\LicenseSettingsController;
use App\Http\Controllers\Admin\Mail\MailController;
use App\Http\Controllers\Admin\Mail\MailLogController;
use App\Http\Controllers\Admin\Mail\MailTemplateController;
use App\Http\Controllers\Admin\Appearance\MenuController;
use App\Http\Controllers\Admin\Marketing\NewsletterController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\CMS\PageController;
use App\Http\Controllers\Admin\Premium\CreditSettingsController;
use App\Http\Controllers\Admin\Premium\Payments\CouponController;
use App\Http\Controllers\Admin\Premium\Payments\PaymentGatewayController;
use App\Http\Controllers\Admin\Premium\Plans\PlanController;
use App\Http\Controllers\Admin\Premium\Subscriptions\SubscriptionManagementController;
use App\Http\Controllers\Admin\Roles\Admins\RoleController;
use App\Http\Controllers\Admin\Settings\NotificationSettingsController;
use App\Http\Controllers\Admin\Settings\OAuthSettingsController;
use App\Http\Controllers\Admin\Settings\SocialCountersController;
use App\Http\Controllers\Admin\Appearance\SidebarController;
use App\Http\Controllers\Admin\Support\CannedResponseController as SupportCannedResponseController;
use App\Http\Controllers\Admin\Support\DepartmentController as SupportDepartmentController;
use App\Http\Controllers\Admin\Support\SettingsController as SupportSettingsController;
use App\Http\Controllers\Admin\Support\TicketController as SupportTicketController;
use App\Http\Controllers\Admin\System\SystemController;
use App\Http\Controllers\Admin\System\CustomStyleController;
use App\Http\Controllers\Admin\System\RateLimitController;
use App\Http\Controllers\Admin\CMS\TestimonialController;
use App\Http\Controllers\Admin\Appearance\ThemeAddonController;
use App\Http\Controllers\Admin\TranslationController;
use App\Http\Controllers\Admin\Roles\Users\UserManagementController;
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
Route::middleware(['admin.auth', 'admin.audit'])->group(function () {
    Route::post('logout', [AdminLoginController::class, 'logout'])->name('admin.logout');
    Route::get('account/settings', [AccountSettingsController::class, 'edit'])->name('admin.account.settings');
    Route::post('account/settings/profile', [AccountSettingsController::class, 'updateProfile'])->name('admin.account.profile.update');
    Route::post('account/settings/password', [AccountSettingsController::class, 'updatePassword'])->name('admin.account.password.update');
    Route::post('account/settings/avatar', [AccountSettingsController::class, 'updateAvatar'])->name('admin.account.avatar.update');

    // Dashboard
    Route::middleware('admin.permission:dashboard.view')->group(function () {
        Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('activity/user-logs', [UserLogController::class, 'index'])->name('admin.activity.user-logs.index');
    });

    // Audit Logs (Super Admin only - enforced in controller)
    Route::get('activity/admin-logs', [AdminLogController::class, 'index'])->name('admin.activity.admin-logs.index');

    // Admin Notes
    Route::get('notes', [AdminNoteController::class, 'index'])->name('admin.notes.index');
    Route::post('notes', [AdminNoteController::class, 'store'])->name('admin.notes.store');
    Route::post('notes/{note}', [AdminNoteController::class, 'update'])->name('admin.notes.update');
    Route::post('notes/{note}/snooze', [AdminNoteController::class, 'snooze'])->name('admin.notes.snooze');
    Route::post('notes/{note}/dismiss', [AdminNoteController::class, 'dismiss'])->name('admin.notes.dismiss');
    Route::delete('notes/{note}', [AdminNoteController::class, 'destroy'])->name('admin.notes.delete');

    // Themes
    Route::middleware('admin.permission:addons.view')->group(function () {
        Route::get('appearance/themes', [ThemeAddonController::class, 'themes'])->name('admin.themes');
        Route::post('appearance/themes/{slug}/activate', [ThemeAddonController::class, 'activateTheme'])->name('admin.themes.activate');
        Route::get('appearance/themes/{slug}/settings', [ThemeAddonController::class, 'themeSettings'])->name('admin.themes.settings');
        Route::post('appearance/themes/{slug}/settings', [ThemeAddonController::class, 'saveThemeSettings'])->name('admin.themes.settings.save');
        Route::post('appearance/themes/{slug}/settings/save-simple', [ThemeAddonController::class, 'saveSimpleThemeSettings'])->name('admin.themes.settings.simple.save');
        Route::post('appearance/themes/{slug}/settings/save-homepage-config', [ThemeAddonController::class, 'saveHomepageConfig'])->name('admin.themes.settings.save-homepage-config');
        Route::post('appearance/themes/{slug}/settings/restore-defaults', [ThemeAddonController::class, 'restoreThemeDefaults'])->name('admin.themes.settings.restore-defaults');

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

        // Bulk actions
        Route::post('appearance/addons/bulk-activate', [ThemeAddonController::class, 'bulkActivate'])->name('admin.addons.bulk-activate');
        Route::post('appearance/addons/bulk-deactivate', [ThemeAddonController::class, 'bulkDeactivate'])->name('admin.addons.bulk-deactivate');
    });

    // Users Management — granular RBAC. Each action accepts its specific
    // permission OR the broad "users.manage"; super-admins bypass all checks.
    // Order preserved: static segments must stay ahead of the {user} wildcard.
    Route::get('roles/users', [UserManagementController::class, 'index'])->middleware('admin.permission:users.view,users.manage')->name('admin.users.index');
    Route::get('roles/users/trash', [UserManagementController::class, 'trash'])->middleware('admin.permission:users.view,users.manage')->name('admin.users.trash');
    Route::get('roles/users/create', [UserManagementController::class, 'create'])->middleware('admin.permission:users.create,users.manage')->name('admin.users.create');
    Route::post('roles/users', [UserManagementController::class, 'store'])->middleware('admin.permission:users.create,users.manage')->name('admin.users.store');
    Route::get('roles/users/export', [UserManagementController::class, 'export'])->middleware('admin.permission:users.manage')->name('admin.users.export');
    Route::post('roles/users/bulk', [UserManagementController::class, 'bulkAction'])->middleware('admin.permission:users.manage')->name('admin.users.bulk');
    Route::post('roles/users/trash/bulk', [UserManagementController::class, 'bulkTrashAction'])->middleware('admin.permission:users.delete,users.manage')->name('admin.users.trash.bulk');
    // No extra permission: an admin must always be able to end an impersonation session and return to their own account.
    Route::post('roles/users/stop-impersonating', [UserManagementController::class, 'stopImpersonating'])->name('admin.users.stop_impersonating');
    Route::post('roles/users/{user}/restore', [UserManagementController::class, 'restore'])->withTrashed()->middleware('admin.permission:users.delete,users.manage')->name('admin.users.restore');
    Route::delete('roles/users/{user}/force-delete', [UserManagementController::class, 'forceDelete'])->withTrashed()->middleware('admin.super')->name('admin.users.force-delete');
    Route::get('roles/users/{user}', [UserManagementController::class, 'show'])->middleware('admin.permission:users.view,users.manage')->name('admin.users.show');
    Route::post('roles/users/{user}', [UserManagementController::class, 'update'])->middleware('admin.permission:users.edit,users.manage')->name('admin.users.update');
    Route::post('roles/users/{user}/toggle-status', [UserManagementController::class, 'toggleStatus'])->middleware('admin.permission:users.edit,users.manage')->name('admin.users.toggle-status');
    Route::delete('roles/users/{user}', [UserManagementController::class, 'destroy'])->middleware('admin.permission:users.delete,users.manage')->name('admin.users.delete');
    Route::post('roles/users/{user}/notification', [UserManagementController::class, 'sendNotification'])->middleware('admin.permission:users.edit,users.manage')->name('admin.users.notification');
    Route::post('roles/users/{user}/two-factor/disable', [UserManagementController::class, 'disableTwoFactor'])->middleware('admin.permission:users.edit,users.manage')->name('admin.users.2fa.disable');
    Route::post('roles/users/{user}/impersonate', [UserManagementController::class, 'impersonate'])->middleware('admin.permission:users.impersonate,users.manage')->name('admin.users.impersonate');

    // Administrators & Roles
    Route::get('roles/admins', [AdminController::class, 'index'])->name('admin.admins.index');
    Route::get('roles/admins/trash', [AdminController::class, 'trash'])->name('admin.admins.trash');
    Route::post('roles/admins', [AdminController::class, 'store'])->name('admin.admins.store');
    Route::post('roles/admins/trash/bulk', [AdminController::class, 'bulkTrashAction'])->name('admin.admins.trash.bulk');
    Route::post('roles/admins/{admin}', [AdminController::class, 'update'])->name('admin.admins.update');
    Route::post('roles/admins/{admin}/restore', [AdminController::class, 'restore'])->withTrashed()->name('admin.admins.restore');
    Route::delete('roles/admins/{admin}', [AdminController::class, 'destroy'])->name('admin.admins.delete');
    Route::delete('roles/admins/{admin}/force-delete', [AdminController::class, 'forceDelete'])->withTrashed()->middleware('admin.super')->name('admin.admins.force-delete');

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

    Route::middleware('admin.permission:roles.view')->group(function () {
        Route::get('roles/admins/permissions', [RoleController::class, 'index'])->name('admin.roles.index');
        Route::get('roles/admins/permissions/create', [RoleController::class, 'create'])->name('admin.roles.create');
        Route::post('roles/admins/permissions', [RoleController::class, 'store'])->name('admin.roles.store');
        Route::get('roles/admins/permissions/{role}/edit', [RoleController::class, 'edit'])->name('admin.roles.edit');
        Route::post('roles/admins/permissions/{role}/restore-default', [RoleController::class, 'restoreDefault'])->name('admin.roles.restore-default');
        Route::post('roles/admins/permissions/{role}', [RoleController::class, 'update'])->name('admin.roles.update');
        Route::delete('roles/admins/permissions/{role}', [RoleController::class, 'destroy'])->name('admin.roles.delete');
    });

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

    // Premium routes (plans, gateways, subscriptions, coupons, credit settings, affiliate)
    Route::middleware(['premium', 'admin.permission:plans.view'])->group(function () {
        Route::get('premium/plans', [PlanController::class, 'index'])->name('admin.plans.index');
        Route::get('premium/plans/pricing', [PlanController::class, 'pricing'])->name('admin.plans.pricing');
        Route::post('premium/plans/settings', [PlanController::class, 'updateSettings'])->middleware('admin.permission:plans.edit')->name('admin.plans.settings');
        Route::put('premium/plans/{plan}', [PlanController::class, 'update'])->middleware('admin.permission:plans.edit')->name('admin.plans.update');
    });

    // Payments, gateways, subscriptions, coupons, credit settings.
    // These controllers hold NO internal permission checks, so the routes must
    // enforce them. Permissions are aligned with the sidebar links so an admin
    // never sees a link they cannot use (or vice-versa): read pages use the
    // "view" permission the sidebar checks; money-mutations require the
    // payments.gateways ("manage") permission. Super-admins bypass all checks.
    Route::middleware('premium')->group(function () {
        Route::get('premium/gateways', [PaymentGatewayController::class, 'index'])->middleware('admin.permission:payments.view,payments.gateways')->name('admin.payment-gateways.index');
        Route::post('premium/gateways/sort', [PaymentGatewayController::class, 'sort'])->middleware('admin.permission:payments.gateways')->name('admin.payment-gateways.sort');
        Route::post('premium/gateways/{gateway}', [PaymentGatewayController::class, 'update'])->middleware('admin.permission:payments.gateways')->name('admin.payment-gateways.update');
        Route::get('premium/subscriptions', [SubscriptionManagementController::class, 'index'])->middleware('admin.permission:payments.view,payments.gateways')->name('admin.subscriptions.index');
        Route::post('premium/subscriptions/{user}/deactivate', [SubscriptionManagementController::class, 'deactivate'])->middleware('admin.permission:payments.gateways')->name('admin.subscriptions.deactivate');
        Route::post('premium/subscriptions/grant', [SubscriptionManagementController::class, 'grant'])->middleware('admin.permission:payments.gateways')->name('admin.subscriptions.grant');

        // Transactions (payment review & bank transfer approval)
        Route::get('premium/transactions', [\App\Http\Controllers\Admin\Premium\Payments\TransactionController::class, 'index'])->middleware('admin.permission:payments.view,payments.gateways')->name('admin.transactions.index');
        Route::get('premium/transactions/{payment}/proof', [\App\Http\Controllers\Admin\Premium\Payments\TransactionController::class, 'proof'])->middleware('admin.permission:payments.view,payments.gateways')->name('admin.transactions.proof');
        Route::post('premium/transactions/{payment}/approve', [\App\Http\Controllers\Admin\Premium\Payments\TransactionController::class, 'approve'])->middleware('admin.permission:payments.gateways')->name('admin.transactions.approve');
        Route::post('premium/transactions/{payment}/reject', [\App\Http\Controllers\Admin\Premium\Payments\TransactionController::class, 'reject'])->middleware('admin.permission:payments.gateways')->name('admin.transactions.reject');
        Route::get('premium/coupons', [CouponController::class, 'index'])->middleware('admin.permission:payments.gateways')->name('admin.coupons.index');
        Route::post('premium/coupons', [CouponController::class, 'store'])->middleware('admin.permission:payments.gateways')->name('admin.coupons.store');
        Route::post('premium/coupons/{coupon}', [CouponController::class, 'update'])->middleware('admin.permission:payments.gateways')->name('admin.coupons.update');
        Route::post('premium/coupons/{coupon}/header', [CouponController::class, 'toggleHeader'])->middleware('admin.permission:payments.gateways')->name('admin.coupons.header');
        Route::delete('premium/coupons/{coupon}', [CouponController::class, 'destroy'])->middleware('admin.permission:payments.gateways')->name('admin.coupons.delete');

        // Credit Settings — shown in the sidebar under settings.manage.
        Route::get('premium/credit-settings', [CreditSettingsController::class, 'index'])->middleware('admin.permission:settings.manage,payments.gateways')->name('admin.credit-settings.index');
        Route::put('premium/credit-settings', [CreditSettingsController::class, 'update'])->middleware('admin.permission:settings.manage,payments.gateways')->name('admin.credit-settings.update');
    });

    // Affiliate (independent of premium)
    Route::middleware(['affiliate', 'admin.permission:payments.gateways'])->group(function () {
        Route::get('marketing/affiliate', [AffiliateController::class, 'index'])->name('admin.affiliate.index');
        Route::get('marketing/affiliate/commissions', [AffiliateController::class, 'commissionsIndex'])->name('admin.affiliate.commissions.index');
        Route::get('marketing/affiliate/payouts', [AffiliateController::class, 'payoutsIndex'])->name('admin.affiliate.payouts.index');
        Route::get('marketing/affiliate/affiliates', [AffiliateController::class, 'affiliatesIndex'])->name('admin.affiliate.affiliates.index');
        Route::get('marketing/affiliate/affiliates/{user}', [AffiliateController::class, 'show'])->name('admin.affiliate.affiliates.show');
        Route::get('marketing/affiliate/settings', [AffiliateController::class, 'settingsEdit'])->name('admin.affiliate.settings.edit');
        Route::post('marketing/affiliate/settings', [AffiliateController::class, 'updateSettings'])->name('admin.affiliate.settings');
        Route::post('marketing/affiliate/commissions/bulk-approve', [AffiliateController::class, 'bulkApproveCommissions'])->name('admin.affiliate.commissions.bulk-approve');
        Route::post('marketing/affiliate/commissions/{commission}/approve', [AffiliateController::class, 'approveCommission'])->name('admin.affiliate.commissions.approve');
        Route::post('marketing/affiliate/commissions/{commission}/reject', [AffiliateController::class, 'rejectCommission'])->name('admin.affiliate.commissions.reject');
        Route::post('marketing/affiliate/affiliates/{user}/ban', [AffiliateController::class, 'toggleAffiliateBan'])->name('admin.affiliate.affiliates.ban');
        Route::post('marketing/affiliate/payouts/{payout}', [AffiliateController::class, 'processPayout'])->name('admin.affiliate.payouts.process');
    });

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
        Route::delete('content/pages/{page}/force-delete', [PageController::class, 'forceDelete'])->withTrashed()->middleware('admin.super')->name('admin.pages.force-delete');
        Route::delete('content/pages/{page}', [PageController::class, 'destroy'])->name('admin.pages.delete');
    });

    Route::middleware(['contact', 'admin.permission:contact.messages'])->group(function () {
        Route::get('contact/messages/export', [ContactMessageController::class, 'export'])->name('admin.contact.messages.export');
        Route::get('contact/messages', [ContactMessageController::class, 'index'])->name('admin.contact.messages.index');
        Route::post('contact/messages/{message}/read', [ContactMessageController::class, 'markRead'])->name('admin.contact.messages.read');
        Route::post('contact/messages/{message}/reply', [ContactMessageController::class, 'reply'])->name('admin.contact.messages.reply');
        Route::delete('contact/messages/{message}', [ContactMessageController::class, 'destroy'])->name('admin.contact.messages.delete');
    });

    Route::middleware(['contact', 'admin.permission:content.pages'])->group(function () {
        Route::get('cms/contact/settings', [ContactSettingsController::class, 'edit'])->name('admin.contact.settings.edit');
        Route::post('cms/contact/settings', [ContactSettingsController::class, 'update'])->name('admin.contact.settings.update');
    });

    // Support Ticket System (PART 24)
    Route::prefix('support/tickets')->name('admin.support.tickets.')->middleware(['tickets', 'admin.permission:support.tickets'])->group(function () {
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

    Route::prefix('support')->name('admin.support.')->middleware(['tickets', 'admin.permission:support.tickets'])->group(function () {

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
        Route::get('system/style', [CustomStyleController::class, 'index'])->name('admin.appearance.index');
        Route::post('system/style', [CustomStyleController::class, 'update'])->name('admin.appearance.update');

        // Appearance: Sidebar Builder
        Route::get('appearance/sidebar', [SidebarController::class, 'index'])->name('admin.sidebar.index');
        Route::post('appearance/sidebar', [SidebarController::class, 'update'])->name('admin.sidebar.update');
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

    // Social Counters + OAuth
    Route::middleware('admin.permission:settings.manage')->group(function () {
        Route::get('settings/social-counters', [SocialCountersController::class, 'editFollow'])->name('admin.social-counters.settings.edit');
        Route::post('settings/social-counters', [SocialCountersController::class, 'updateFollow'])->name('admin.social-counters.settings.update');
        Route::get('settings/oauth', [OAuthSettingsController::class, 'edit'])->name('admin.oauth.settings.edit');
        Route::post('settings/oauth', [OAuthSettingsController::class, 'update'])->name('admin.oauth.settings.update');
        Route::get('settings/integrations', [AiManagementController::class, 'integrations'])->name('admin.settings.integrations.index');
        Route::post('settings/integrations', [AiManagementController::class, 'updateIntegrations'])->name('admin.settings.integrations.update');
        Route::post('settings/integrations/{integration}/test-connection', [AiManagementController::class, 'testIntegrationConnection'])->name('admin.settings.integrations.test-connection');
    });

    // General Settings
    Route::middleware('admin.permission:settings.general,settings.manage')->group(function () {
        Route::get('settings', [GeneralSettingsController::class, 'edit'])->name('admin.settings.index');
        Route::post('settings', [GeneralSettingsController::class, 'update'])->name('admin.settings.update');
        Route::get('settings/features', [FeatureSettingsController::class, 'edit'])->name('admin.features.settings');
        Route::post('settings/features', [FeatureSettingsController::class, 'update'])->name('admin.features.settings.update');
    });

    // GDPR Settings
    Route::middleware('admin.permission:settings.manage')->group(function () {
        Route::get('settings/gdpr', [GdprSettingsController::class, 'edit'])->name('admin.gdpr.settings');
        Route::post('settings/gdpr', [GdprSettingsController::class, 'update'])->name('admin.gdpr.settings.update');
    });

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
        Route::post('system/apply-update', [SystemController::class, 'applyUpdate'])->name('admin.system.apply-update');
        Route::post('system/rollback-update', [SystemController::class, 'rollbackUpdate'])->name('admin.system.rollback-update');
        Route::post('system/maintenance/settings', [SystemController::class, 'updateMaintenanceSettings'])->name('admin.system.maintenance.settings');
        Route::post('system/maintenance/toggle', [SystemController::class, 'toggleMaintenance'])->name('admin.system.maintenance.toggle');
    });

    // In-App Notifications (PART 23)
    Route::middleware('admin.permission:settings.general')->group(function () {
        Route::middleware('notifications')->group(function () {
            Route::get('notifications', [AdminNotificationController::class, 'index'])->name('admin.notifications.index');
            Route::get('notifications/latest', [AdminNotificationController::class, 'latest'])->name('admin.notifications.latest');
            Route::post('notifications/read-all', [AdminNotificationController::class, 'markAllRead'])->name('admin.notifications.read-all');
            Route::post('notifications/{notification}/read', [AdminNotificationController::class, 'markRead'])->name('admin.notifications.read');
            Route::get('settings/notifications', [NotificationSettingsController::class, 'edit'])->name('admin.notifications.settings');
            Route::post('settings/notifications', [NotificationSettingsController::class, 'update'])->name('admin.notifications.settings.update');
            Route::post('settings/notifications/test', [NotificationSettingsController::class, 'test'])->name('admin.notifications.test');
        });
    });

    // Mail System
    Route::middleware('admin.permission:settings.mail')->group(function () {
        Route::get('mail/settings', [MailController::class, 'index'])->name('admin.mail.index');
        Route::post('mail/settings', [MailController::class, 'update'])->name('admin.mail.update');
        Route::post('mail/test', [MailController::class, 'test'])->name('admin.mail.test');

        Route::get('mail/templates', [MailTemplateController::class, 'index'])->name('admin.mail.templates.index');
        Route::get('mail/templates/create', [MailTemplateController::class, 'create'])->name('admin.mail.templates.create');
        Route::post('mail/templates', [MailTemplateController::class, 'store'])->name('admin.mail.templates.store');
        Route::post('mail/templates/ai-assist', [MailTemplateController::class, 'aiAssist'])->name('admin.mail.templates.ai-assist');
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
        Route::post('content/comments/bulk', [CommentModerationController::class, 'bulk'])->name('admin.comments.bulk');
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
    Route::prefix('content/blog')->name('admin.blog.')->middleware(['admin.permission:content.blog', 'blog'])->group(function () {
        Route::get('posts/export', [BlogPostController::class, 'export'])->name('posts.export');
        Route::post('posts/bulk', [BlogPostController::class, 'bulk'])->name('posts.bulk');
        Route::post('posts/ai-assist', [BlogPostController::class, 'aiAssist'])->name('posts.ai-assist');
        Route::post('posts/autosave', [BlogPostController::class, 'autosave'])->name('posts.autosave');
        Route::post('posts/{post}/autosave', [BlogPostController::class, 'autosave'])->name('posts.autosave.update');
        Route::post('posts/{post}/duplicate', [BlogPostController::class, 'duplicate'])->name('posts.duplicate');
        Route::get('posts/trash', [BlogPostController::class, 'trash'])->name('posts.trash');
        Route::post('posts/{post}/restore', [BlogPostController::class, 'restore'])->withTrashed()->name('posts.restore');
        Route::delete('posts/{post}/force-delete', [BlogPostController::class, 'forceDelete'])->withTrashed()->middleware('admin.super')->name('posts.force-delete');
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
        Route::post('/settings', [AiManagementController::class, 'updateSettings'])->name('settings.update');
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
        Route::post('/tools/global-settings', [AiToolController::class, 'updateGlobalSettings'])->name('tools.global-settings');
        Route::post('/tools/bulk', [AiToolController::class, 'bulk'])->name('tools.bulk');
        Route::post('/tools/{tool}/toggle', [AiToolController::class, 'toggle'])->name('tools.toggle');
        Route::get('/tools/trash', [AiToolController::class, 'trash'])->name('tools.trash');
        Route::post('/tools/trash/bulk', [AiToolController::class, 'bulkTrash'])->name('tools.trash.bulk');
        Route::post('/tools/{tool}/restore', [AiToolController::class, 'restore'])->withTrashed()->name('tools.restore');
        Route::delete('/tools/{tool}/force-delete', [AiToolController::class, 'forceDelete'])->withTrashed()->middleware('admin.super')->name('tools.force-delete');
        Route::post('/tools/reviews/{review}/action', [AiToolController::class, 'reviewAction'])->name('tools.reviews.action');

        // Tool Reviews Management
        Route::get('/reviews', [ToolReviewController::class, 'index'])->name('reviews.index');
        Route::post('/reviews/{review}/approve', [ToolReviewController::class, 'approve'])->name('reviews.approve');
        Route::post('/reviews/{review}/disapprove', [ToolReviewController::class, 'disapprove'])->name('reviews.disapprove');
        Route::post('/reviews/{review}/reply', [ToolReviewController::class, 'reply'])->name('reviews.reply');
        Route::delete('/reviews/{review}', [ToolReviewController::class, 'destroy'])->name('reviews.destroy');
        Route::post('/categories/{category}/toggle-active', [AiToolCategoryController::class, 'toggleActive'])->name('categories.toggle-active');
        Route::resource('categories', AiToolCategoryController::class)->except(['create', 'show', 'edit']);
        Route::resource('tools', AiToolController::class)->except(['show']);
    });
});

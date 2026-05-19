<?php

use App\Http\Controllers\Admin\AdController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\AiAccessController;
use App\Http\Controllers\Admin\AiManagementController;
use App\Http\Controllers\Admin\AiTemplateController;
use App\Http\Controllers\Admin\AiToolCategoryController;
use App\Http\Controllers\Admin\AiUsageLogController;
use App\Http\Controllers\Admin\AppearanceController;
use App\Http\Controllers\Admin\BlogCategoryController;
use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\Admin\BlogSettingsController;
use App\Http\Controllers\Admin\BlogTagController;
use App\Http\Controllers\Admin\CMS\AnnouncementController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\ContactSettingsController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\FooterBuilderController;
use App\Http\Controllers\Admin\HeaderBuilderController;
use App\Http\Controllers\Admin\HomepageBuilderController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\MailController;
use App\Http\Controllers\Admin\MailLogController;
use App\Http\Controllers\Admin\MailTemplateController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\NewsletterController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SidebarBuilderController;
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
    Route::post('login', [AdminLoginController::class, 'login'])->name('admin.login.attempt');

    // 2FA
    Route::get('2fa', [AdminLoginController::class, 'show2fa'])->name('admin.2fa.show');
    Route::post('2fa', [AdminLoginController::class, 'verify2fa'])->name('admin.2fa.verify');
});

// ─── Authenticated ──────────────────────────────────
Route::middleware('admin.auth')->group(function () {
    Route::post('logout', [AdminLoginController::class, 'logout'])->name('admin.logout');

    // Dashboard
    Route::get('/', function () {
        return Inertia::render('Admin/Dashboard');
    })->name('admin.dashboard');

    // Themes
    Route::get('themes', [ThemeAddonController::class, 'themes'])->name('admin.themes');
    Route::post('themes/{slug}/activate', [ThemeAddonController::class, 'activateTheme'])->name('admin.themes.activate');
    Route::get('themes/{slug}/settings', [ThemeAddonController::class, 'themeSettings'])->name('admin.themes.settings');
    Route::post('themes/{slug}/settings', [ThemeAddonController::class, 'saveThemeSettings'])->name('admin.themes.settings.save');

    // Addons
    Route::get('addons', [ThemeAddonController::class, 'addons'])->name('admin.addons');
    Route::post('addons/{slug}/activate', [ThemeAddonController::class, 'activateAddon'])->name('admin.addons.activate');
    Route::post('addons/{slug}/deactivate', [ThemeAddonController::class, 'deactivateAddon'])->name('admin.addons.deactivate');
    Route::get('addons/{slug}/settings', [ThemeAddonController::class, 'addonSettings'])->name('admin.addons.settings');
    Route::post('addons/{slug}/settings', [ThemeAddonController::class, 'saveAddonSettings'])->name('admin.addons.settings.save');

    // Users Management
    Route::get('users', [UserManagementController::class, 'index'])->name('admin.users.index');
    Route::get('users/export', [UserManagementController::class, 'export'])->name('admin.users.export');
    Route::post('users/bulk', [UserManagementController::class, 'bulkAction'])->name('admin.users.bulk');
    Route::get('users/{user}', [UserManagementController::class, 'show'])->name('admin.users.show');
    Route::post('users/{user}', [UserManagementController::class, 'update'])->name('admin.users.update');
    Route::post('users/{user}/impersonate', [UserManagementController::class, 'impersonate'])->name('admin.users.impersonate');
    Route::post('users/stop-impersonating', [UserManagementController::class, 'stopImpersonating'])->name('admin.users.stop_impersonating');

    // Administrators & Roles
    Route::get('admins', [AdminController::class, 'index'])->name('admin.admins.index');
    Route::post('admins', [AdminController::class, 'store'])->name('admin.admins.store');
    Route::post('admins/{admin}', [AdminController::class, 'update'])->name('admin.admins.update');
    Route::delete('admins/{admin}', [AdminController::class, 'destroy'])->name('admin.admins.delete');

    Route::get('roles', [RoleController::class, 'index'])->name('admin.roles.index');
    Route::get('roles/create', [RoleController::class, 'create'])->name('admin.roles.create');
    Route::post('roles', [RoleController::class, 'store'])->name('admin.roles.store');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('admin.roles.edit');
    Route::post('roles/{role}', [RoleController::class, 'update'])->name('admin.roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('admin.roles.delete');

    // Localization
    Route::get('localization/languages', [LanguageController::class, 'index'])->name('admin.languages.index');
    Route::post('localization/languages', [LanguageController::class, 'store'])->name('admin.languages.store');
    Route::post('localization/languages/{language}', [LanguageController::class, 'update'])->name('admin.languages.update');
    Route::post('localization/languages/{language}/default', [LanguageController::class, 'setDefault'])->name('admin.languages.default');
    Route::delete('localization/languages/{language}', [LanguageController::class, 'destroy'])->name('admin.languages.delete');

    Route::get('localization/translations/{language}', [TranslationController::class, 'index'])->name('admin.translations.index');
    Route::post('localization/translations/{translation}', [TranslationController::class, 'update'])->name('admin.translations.update');
    Route::post('localization/translations/{translation}/ai', [TranslationController::class, 'aiTranslate'])->name('admin.translations.ai');
    Route::post('localization/translations/{language}/ai-all', [TranslationController::class, 'aiTranslateAll'])->name('admin.translations.ai_all');

    // Country-specific subscription pricing replaces exchange-rate currency management.
    Route::get('plans', [PlanController::class, 'index'])->name('admin.plans.index');
    Route::post('plans/settings', [PlanController::class, 'updateSettings'])->name('admin.plans.settings');
    Route::post('plans/{plan}', [PlanController::class, 'update'])->name('admin.plans.update');

    Route::get('payment-gateways', [PaymentGatewayController::class, 'index'])->name('admin.payment-gateways.index');
    Route::post('payment-gateways/sort', [PaymentGatewayController::class, 'sort'])->name('admin.payment-gateways.sort');
    Route::post('payment-gateways/{gateway}', [PaymentGatewayController::class, 'update'])->name('admin.payment-gateways.update');
    Route::get('coupons', [CouponController::class, 'index'])->name('admin.coupons.index');
    Route::post('coupons', [CouponController::class, 'store'])->name('admin.coupons.store');
    Route::post('coupons/{coupon}', [CouponController::class, 'update'])->name('admin.coupons.update');
    Route::post('coupons/{coupon}/header', [CouponController::class, 'toggleHeader'])->name('admin.coupons.header');
    Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->name('admin.coupons.delete');

    // Community
    Route::get('community/newsletter', [NewsletterController::class, 'index'])->name('admin.newsletter.index');
    Route::post('community/newsletter/campaign', [NewsletterController::class, 'storeCampaign'])->name('admin.newsletter.campaign.store');
    Route::post('community/newsletter/campaign/{campaign}/send', [NewsletterController::class, 'sendCampaign'])->name('admin.newsletter.campaign.send');
    Route::delete('community/newsletter/subscriber/{subscriber}', [NewsletterController::class, 'destroySubscriber'])->name('admin.newsletter.subscriber.delete');
    Route::post('community/newsletter/settings', [NewsletterController::class, 'saveSettings'])->name('admin.newsletter.settings.save');

    // CMS: Pages
    Route::get('cms/pages', [PageController::class, 'index'])->name('admin.pages.index');
    Route::get('cms/pages/create', [PageController::class, 'create'])->name('admin.pages.create');
    Route::post('cms/pages/ai-assist', [PageController::class, 'aiAssist'])->name('admin.pages.ai-assist');
    Route::post('cms/pages', [PageController::class, 'store'])->name('admin.pages.store');
    Route::get('cms/pages/{page}/edit', [PageController::class, 'edit'])->name('admin.pages.edit');
    Route::post('cms/pages/{page}', [PageController::class, 'update'])->name('admin.pages.update');
    Route::delete('cms/pages/{page}', [PageController::class, 'destroy'])->name('admin.pages.delete');

    Route::get('cms/contact/messages/export', [ContactMessageController::class, 'export'])->name('admin.contact.messages.export');
    Route::get('cms/contact/messages', [ContactMessageController::class, 'index'])->name('admin.contact.messages.index');
    Route::post('cms/contact/messages/{message}/read', [ContactMessageController::class, 'markRead'])->name('admin.contact.messages.read');
    Route::post('cms/contact/messages/{message}/reply', [ContactMessageController::class, 'reply'])->name('admin.contact.messages.reply');
    Route::delete('cms/contact/messages/{message}', [ContactMessageController::class, 'destroy'])->name('admin.contact.messages.delete');
    Route::get('cms/contact/settings', [ContactSettingsController::class, 'edit'])->name('admin.contact.settings.edit');
    Route::post('cms/contact/settings', [ContactSettingsController::class, 'update'])->name('admin.contact.settings.update');

    // Support Ticket System (PART 24)
    Route::prefix('support')->name('admin.support.')->group(function () {
        Route::get('tickets', [SupportTicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/{ticket}', [SupportTicketController::class, 'show'])->name('tickets.show');
        Route::post('tickets/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('tickets.reply');
        Route::post('tickets/{ticket}/state', [SupportTicketController::class, 'updateState'])->name('tickets.state');
        Route::post('tickets/{ticket}/suggest-reply', [SupportTicketController::class, 'suggestReply'])->name('tickets.suggest-reply');
        Route::delete('tickets/{ticket}', [SupportTicketController::class, 'destroy'])->name('tickets.delete');

        Route::resource('departments', SupportDepartmentController::class)->except(['create', 'show', 'edit']);
        Route::resource('canned-responses', SupportCannedResponseController::class)->except(['create', 'show', 'edit'])->parameters([
            'canned-responses' => 'response',
        ]);
        Route::get('settings', [SupportSettingsController::class, 'edit'])->name('settings.edit');
        Route::post('settings', [SupportSettingsController::class, 'update'])->name('settings.update');
    });

    // Appearance: Menus
    Route::get('appearance/menus', [MenuController::class, 'index'])->name('admin.menus.index');
    Route::post('appearance/menus', [MenuController::class, 'store'])->name('admin.menus.store');
    Route::post('appearance/menus/{menu}/item', [MenuController::class, 'addItem'])->name('admin.menus.item.store');
    Route::post('appearance/menus/item/{item}', [MenuController::class, 'updateItem'])->name('admin.menus.item.update');
    Route::delete('appearance/menus/item/{item}', [MenuController::class, 'deleteItem'])->name('admin.menus.item.delete');
    Route::delete('appearance/menus/{menu}', [MenuController::class, 'destroy'])->name('admin.menus.delete');

    // Appearance: Settings
    Route::get('appearance/settings', [AppearanceController::class, 'index'])->name('admin.appearance.index');
    Route::post('appearance/settings', [AppearanceController::class, 'update'])->name('admin.appearance.update');

    // Appearance: Homepage Builder
    Route::get('appearance/homepage', [HomepageBuilderController::class, 'index'])->name('admin.homepage.index');
    Route::post('appearance/homepage', [HomepageBuilderController::class, 'update'])->name('admin.homepage.update');

    // Appearance: Header Builder
    Route::get('appearance/header', [HeaderBuilderController::class, 'index'])->name('admin.header.index');
    Route::post('appearance/header', [HeaderBuilderController::class, 'update'])->name('admin.header.update');

    // Appearance: Footer Builder
    Route::get('appearance/footer', [FooterBuilderController::class, 'index'])->name('admin.footer.index');
    Route::post('appearance/footer', [FooterBuilderController::class, 'update'])->name('admin.footer.update');

    // Appearance: Sidebar Builder
    Route::get('appearance/sidebar', [SidebarBuilderController::class, 'index'])->name('admin.sidebar.index');
    Route::post('appearance/sidebar', [SidebarBuilderController::class, 'update'])->name('admin.sidebar.update');

    // Ads System
    Route::get('ads', [AdController::class, 'index'])->name('admin.ads.index');
    Route::get('ads/create', [AdController::class, 'create'])->name('admin.ads.create');
    Route::post('ads', [AdController::class, 'store'])->name('admin.ads.store');
    Route::get('ads/{ad}/edit', [AdController::class, 'edit'])->name('admin.ads.edit');
    Route::post('ads/{ad}', [AdController::class, 'update'])->name('admin.ads.update');
    Route::delete('ads/{ad}', [AdController::class, 'destroy'])->name('admin.ads.delete');
    Route::post('ads/{ad}/toggle', [AdController::class, 'toggle'])->name('admin.ads.toggle');

    // System Tools
    Route::get('system', [SystemController::class, 'index'])->name('admin.system.index');
    Route::post('system/cache', [SystemController::class, 'clearCache'])->name('admin.system.cache.clear');
    Route::post('system/maintenance', [SystemController::class, 'toggleMaintenance'])->name('admin.system.maintenance.toggle');

    // Mail System
    Route::get('mail', [MailController::class, 'index'])->name('admin.mail.index');
    Route::post('mail', [MailController::class, 'update'])->name('admin.mail.update');
    Route::post('mail/test', [MailController::class, 'test'])->name('admin.mail.test');

    Route::get('mail/templates', [MailTemplateController::class, 'index'])->name('admin.mail.templates.index');
    Route::get('mail/templates/create', [MailTemplateController::class, 'create'])->name('admin.mail.templates.create');
    Route::post('mail/templates', [MailTemplateController::class, 'store'])->name('admin.mail.templates.store');
    Route::get('mail/templates/{template}/edit', [MailTemplateController::class, 'edit'])->name('admin.mail.templates.edit');
    Route::post('mail/templates/{template}', [MailTemplateController::class, 'update'])->name('admin.mail.templates.update');
    Route::delete('mail/templates/{template}', [MailTemplateController::class, 'destroy'])->name('admin.mail.templates.delete');

    Route::get('mail/logs', [MailLogController::class, 'index'])->name('admin.mail.logs.index');
    Route::post('mail/logs/{log}/resend', [MailLogController::class, 'resend'])->name('admin.mail.logs.resend');

    // Content: Testimonials (PART 19)
    Route::get('content/testimonials', [TestimonialController::class, 'index'])->name('admin.testimonials.index');
    Route::post('content/testimonials', [TestimonialController::class, 'store'])->name('admin.testimonials.store');
    Route::post('content/testimonials/sort', [TestimonialController::class, 'bulkSort'])->name('admin.testimonials.sort');
    Route::post('content/testimonials/import', [TestimonialController::class, 'import'])->name('admin.testimonials.import');
    Route::post('content/testimonials/generate', [TestimonialController::class, 'generate'])->name('admin.testimonials.generate');
    Route::post('content/testimonials/{testimonial}', [TestimonialController::class, 'update'])->name('admin.testimonials.update');
    Route::post('content/testimonials/{testimonial}/featured', [TestimonialController::class, 'toggleFeatured'])->name('admin.testimonials.featured');
    Route::post('content/testimonials/{testimonial}/active', [TestimonialController::class, 'toggleActive'])->name('admin.testimonials.active');
    Route::delete('content/testimonials/{testimonial}', [TestimonialController::class, 'destroy'])->name('admin.testimonials.delete');

    // Content: FAQs (PART 19)
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

    // Content: Announcements (PART 29)
    Route::get('content/announcements', [AnnouncementController::class, 'index'])->name('admin.announcements.index');
    Route::post('content/announcements', [AnnouncementController::class, 'store'])->name('admin.announcements.store');
    Route::post('content/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('admin.announcements.update');
    Route::post('content/announcements/{announcement}/active', [AnnouncementController::class, 'toggleActive'])->name('admin.announcements.active');
    Route::delete('content/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('admin.announcements.delete');

    // Content: Blog (PART 16)
    Route::prefix('content/blog')->name('admin.blog.')->group(function () {
        Route::get('posts/export', [BlogPostController::class, 'export'])->name('posts.export');
        Route::post('posts/bulk', [BlogPostController::class, 'bulk'])->name('posts.bulk');
        Route::post('posts/ai-assist', [BlogPostController::class, 'aiAssist'])->name('posts.ai-assist');
        Route::post('posts/autosave', [BlogPostController::class, 'autosave'])->name('posts.autosave');
        Route::post('posts/{post}/autosave', [BlogPostController::class, 'autosave'])->name('posts.autosave.update');
        Route::post('posts/{post}/duplicate', [BlogPostController::class, 'duplicate'])->name('posts.duplicate');
        Route::resource('posts', BlogPostController::class)->except(['show']);

        Route::delete('tags/unused', [BlogTagController::class, 'deleteUnused'])->name('tags.unused.delete');
        Route::resource('categories', BlogCategoryController::class)->except(['create', 'show', 'edit']);
        Route::resource('tags', BlogTagController::class)->except(['create', 'show', 'edit']);

        Route::get('settings', [BlogSettingsController::class, 'edit'])->name('settings.edit');
        Route::post('settings', [BlogSettingsController::class, 'update'])->name('settings.update');
    });

    // AI Tools Management
    Route::prefix('ai')->name('admin.ai.')->group(function () {
        Route::get('/', [AiManagementController::class, 'index'])->name('index');
        Route::get('/provider/{slug}', [AiManagementController::class, 'provider'])->name('provider');
        Route::get('/external-apis', [AiManagementController::class, 'externalApis'])->name('external-apis.index');
        Route::post('/settings', [AiManagementController::class, 'updateSettings'])->name('settings.update');
        Route::post('/external-apis', [AiManagementController::class, 'updateExternalApis'])->name('external-apis.update');
        Route::post('/provider/{provider}/key', [AiManagementController::class, 'storeKey'])->name('key.store');
        Route::delete('/key/{key}', [AiManagementController::class, 'deleteKey'])->name('key.delete');
        Route::post('/model/{model}', [AiManagementController::class, 'updateModel'])->name('model.update');

        // Access Settings
        Route::get('/access', [AiAccessController::class, 'index'])->name('access.index');
        Route::post('/access/bulk', [AiAccessController::class, 'bulkUpdate'])->name('access.bulk');
        Route::post('/access/category', [AiAccessController::class, 'categoryUpdate'])->name('access.category');
        Route::post('/access/preset', [AiAccessController::class, 'presetUpdate'])->name('access.preset');

        // Usage Logs
        Route::get('/logs', [AiUsageLogController::class, 'index'])->name('logs.index');

        // Templates (Full CRUD)
        Route::post('/templates/{template}/toggle', [AiTemplateController::class, 'toggle'])->name('templates.toggle');
        Route::post('/templates/reviews/{review}/action', [AiTemplateController::class, 'reviewAction'])->name('templates.reviews.action');
        Route::resource('categories', AiToolCategoryController::class)->except(['create', 'show', 'edit']);
        Route::resource('templates', AiTemplateController::class);
    });
});

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
use App\Http\Controllers\Admin\CMS\AnnouncementController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\FooterBuilderController;
use App\Http\Controllers\Admin\HeaderBuilderController;
use App\Http\Controllers\Admin\HomepageBuilderController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\MailController;
use App\Http\Controllers\Admin\MailLayoutController;
use App\Http\Controllers\Admin\MailLogController;
use App\Http\Controllers\Admin\MailTemplateController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\NewsletterController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SidebarBuilderController;
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

    Route::get('localization/currencies', [CurrencyController::class, 'index'])->name('admin.currencies.index');
    Route::post('localization/currencies', [CurrencyController::class, 'store'])->name('admin.currencies.store');
    Route::post('localization/currencies/{currency}', [CurrencyController::class, 'update'])->name('admin.currencies.update');
    Route::post('localization/currencies/{currency}/default', [CurrencyController::class, 'setDefault'])->name('admin.currencies.default');
    Route::post('localization/currencies/sync', [CurrencyController::class, 'syncRates'])->name('admin.currencies.sync');
    Route::delete('localization/currencies/{currency}', [CurrencyController::class, 'destroy'])->name('admin.currencies.delete');

    // Community
    Route::get('community/newsletter', [NewsletterController::class, 'index'])->name('admin.newsletter.index');
    Route::post('community/newsletter/campaign', [NewsletterController::class, 'storeCampaign'])->name('admin.newsletter.campaign.store');
    Route::post('community/newsletter/campaign/{campaign}/send', [NewsletterController::class, 'sendCampaign'])->name('admin.newsletter.campaign.send');
    Route::delete('community/newsletter/subscriber/{subscriber}', [NewsletterController::class, 'destroySubscriber'])->name('admin.newsletter.subscriber.delete');
    Route::post('community/newsletter/settings', [NewsletterController::class, 'saveSettings'])->name('admin.newsletter.settings.save');

    // CMS: Pages
    Route::get('cms/pages', [PageController::class, 'index'])->name('admin.pages.index');
    Route::get('cms/pages/create', [PageController::class, 'create'])->name('admin.pages.create');
    Route::post('cms/pages', [PageController::class, 'store'])->name('admin.pages.store');
    Route::get('cms/pages/{page}/edit', [PageController::class, 'edit'])->name('admin.pages.edit');
    Route::post('cms/pages/{page}', [PageController::class, 'update'])->name('admin.pages.update');
    Route::delete('cms/pages/{page}', [PageController::class, 'destroy'])->name('admin.pages.delete');

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
    Route::get('mail/templates/{template}/edit', [MailTemplateController::class, 'edit'])->name('admin.mail.templates.edit');
    Route::post('mail/templates/{template}', [MailTemplateController::class, 'update'])->name('admin.mail.templates.update');

    Route::get('mail/layout', [MailLayoutController::class, 'index'])->name('admin.mail.layout.index');
    Route::post('mail/layout', [MailLayoutController::class, 'update'])->name('admin.mail.layout.update');

    Route::get('mail/logs', [MailLogController::class, 'index'])->name('admin.mail.logs.index');

    // Content: Testimonials (PART 28)
    Route::get('content/testimonials', [TestimonialController::class, 'index'])->name('admin.testimonials.index');
    Route::post('content/testimonials', [TestimonialController::class, 'store'])->name('admin.testimonials.store');
    Route::post('content/testimonials/sort', [TestimonialController::class, 'bulkSort'])->name('admin.testimonials.sort');
    Route::post('content/testimonials/import', [TestimonialController::class, 'import'])->name('admin.testimonials.import');
    Route::post('content/testimonials/{testimonial}', [TestimonialController::class, 'update'])->name('admin.testimonials.update');
    Route::post('content/testimonials/{testimonial}/featured', [TestimonialController::class, 'toggleFeatured'])->name('admin.testimonials.featured');
    Route::post('content/testimonials/{testimonial}/active', [TestimonialController::class, 'toggleActive'])->name('admin.testimonials.active');
    Route::delete('content/testimonials/{testimonial}', [TestimonialController::class, 'destroy'])->name('admin.testimonials.delete');

    // Content: FAQs (PART 28)
    Route::get('content/faqs', [FaqController::class, 'index'])->name('admin.faqs.index');
    Route::post('content/faqs', [FaqController::class, 'store'])->name('admin.faqs.store');
    Route::post('content/faqs/sort', [FaqController::class, 'bulkSort'])->name('admin.faqs.sort');
    Route::post('content/faqs/import', [FaqController::class, 'import'])->name('admin.faqs.import');
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

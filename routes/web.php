<?php

use App\Http\Controllers\AdController;
use App\Http\Controllers\AiToolController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\TwoFactorLoginController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Billing\PaymentWebhookController;
use App\Http\Controllers\Billing\StripeController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LiveSearchController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OutputRatingController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\User\ProfileController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\User\AffiliateController;
use App\Http\Controllers\User\ChainController;
use App\Http\Controllers\User\CollectionController;
use App\Http\Controllers\User\CreditTopupController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\EmbedController;
use App\Http\Controllers\User\FavoriteController;
use App\Http\Controllers\User\HistoryController;
use App\Http\Controllers\User\OnboardingController;
use App\Http\Controllers\User\PlaygroundController;
use App\Http\Controllers\User\PrivacyController;
use App\Http\Controllers\User\SettingsController as UserSettingsController;
use App\Http\Controllers\User\SupportTicketController;
use App\Http\Controllers\User\ToolEmbedController;
use App\Http\Controllers\User\ToolFavoriteController;
use App\Http\Controllers\User\UsageDashboardController;
use App\Http\Controllers\User\UserExportController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Middleware\EmbedCorsMiddleware;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| MakeAI web routes — organized by feature area.
|
*/

// ─── Installation Wizard ────────────────────
// require __DIR__.'/install.php';

// ─── Broadcasting Auth ──────────────────────
Broadcast::routes(['middleware' => ['web']]);

// ─── Admin Routes ───────────────────────────
Route::middleware('web')->prefix('admin')->group(base_path('routes/admin.php'));

// Support attachment download — serves the ticket owner OR an admin with the
// support.tickets permission (access is enforced inside the controller, since a
// single URL is shared between the user and admin ticket views).
Route::get('/support/attachments/{attachment}/download', [SupportTicketController::class, 'downloadAttachment'])
    ->name('support.attachments.download');

// ─── Public ─────────────────────────────────
Route::get('/', [HomeController::class, 'index'])->middleware('cache.public')->name('home');
Route::get('/pricing', [PricingController::class, 'index'])->middleware('premium')->name('pricing');
Route::get('/ref/{code}', [AffiliateController::class, 'capture'])->middleware('throttle:public,30,60')->name('affiliate.capture');
Route::post('/locale', [LocaleController::class, 'switch'])->name('locale.switch');
Route::get('/live-search', LiveSearchController::class)->middleware('throttle:public,60,60')->name('live-search');
Route::get('/css/theme-variables.css', [HomeController::class, 'themeCss'])->name('theme-variables.css');

// Demo bar preset/addon selector. GET only (demo mode blocks writes) and 404s when
// demo mode is off, so it never exists on a real install.
Route::get('/__demo/select', [\App\Http\Controllers\DemoController::class, 'select'])->name('demo.select');

// ─── Guest Auth ─────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->name('login.attempt');
    Route::middleware('register')->group(function () {
        Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
        Route::post('register', [RegisterController::class, 'register'])->name('register.attempt');
    });
    Route::get('auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
        ->whereIn('provider', ['google', 'github', 'facebook', 'reddit', 'twitter'])
        ->middleware('throttle:social_auth')
        ->name('social.redirect');
    Route::get('auth/{provider}/callback', [SocialAuthController::class, 'callback'])
        ->whereIn('provider', ['google', 'github', 'facebook', 'reddit', 'twitter'])
        ->middleware('throttle:social_auth')
        ->name('social.callback');

    // Password Reset
    Route::get('forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'sendResetOtp'])->name('password.email');
    Route::get('reset-password', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('reset-password/verify', [PasswordResetController::class, 'verifyResetOtp'])->name('password.verify');
    Route::post('reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.update');
    Route::get('two-factor', [TwoFactorLoginController::class, 'show'])->name('two-factor.show');
    Route::post('two-factor', [TwoFactorLoginController::class, 'verify'])->name('two-factor.verify');
    Route::post('two-factor/resend', [TwoFactorLoginController::class, 'resend'])->name('two-factor.resend');
});

// ─── Authenticated ──────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    // Email Verification
    Route::middleware('email.verify')->group(function () {
        Route::get('verify-email', [VerificationController::class, 'notice'])->name('verification.notice');
        Route::post('verify-email', [VerificationController::class, 'verify'])->name('verification.verify');
        Route::post('verify-email/resend', [VerificationController::class, 'resend'])->name('verification.resend');
    });

    // Theme Preference (Accessible even if not verified)
    Route::post('/profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme');

    // User Dashboard + AI Writer (verified only). The require-2FA gate is applied
    // globally to authenticated routes from bootstrap/app.php.
    Route::middleware('verified')->group(function () {
        Route::get('/user/dashboard', [DashboardController::class, 'index'])->name('user.dashboard');

        // New purchases: only when premium is fully available (extended license + toggle on).
        Route::middleware('premium')->group(function () {
            Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
            Route::post('/checkout/coupon-preview', [CheckoutController::class, 'previewCoupon'])->middleware('throttle:public,20,60')->name('checkout.coupon-preview');
            Route::post('/checkout/session', [CheckoutController::class, 'createSession'])->name('checkout.session');
        });

        // Post-purchase lifecycle: must keep working on an extended license even if the
        // admin switches the subscriptions toggle off, so existing subscribers can still
        // complete initiated payments, cancel, or manage billing.
        Route::middleware('extended')->group(function () {
            Route::get('/checkout/bank-transfer/{payment}', [CheckoutController::class, 'bankInstructions'])->name('checkout.bank.show');
            Route::post('/checkout/bank-transfer/{payment}/proof', [CheckoutController::class, 'uploadBankProof'])->name('checkout.bank.proof');
            Route::get('/checkout/paypal/return/{payment}', [CheckoutController::class, 'paypalReturn'])->name('checkout.paypal.return');
            Route::get('/checkout/pending/{payment}', [CheckoutController::class, 'pending'])->name('checkout.pending');
            Route::get('/billing-portal', [StripeController::class, 'billingPortal'])->name('billing.portal');
            Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');
            Route::post('/subscription/resume', [SubscriptionController::class, 'resume'])->name('subscription.resume');
            Route::post('/subscription/downgrade', [SubscriptionController::class, 'downgrade'])->name('subscription.downgrade');
            Route::post('/subscription/upgrade', [SubscriptionController::class, 'upgrade'])->name('subscription.upgrade');
            Route::post('/subscription/cancel-scheduled', [SubscriptionController::class, 'cancelScheduledChange'])->name('subscription.cancel-scheduled');
        });

        Route::get('/documents/{document}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
        Route::patch('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

        // ========= User Dashboard Panel Routes =========
        Route::prefix('user/dashboard')->name('user.dashboard.')->group(function () {

            // Settings → Profile & Security
            Route::get('/profile', [UserSettingsController::class, 'profile'])->name('profile');
            Route::get('/security', [UserSettingsController::class, 'security'])->name('security');
            Route::post('/security/two-factor/sms-code', [UserSettingsController::class, 'sendTwoFactorSmsCode'])->name('security.2fa.sms-code');
            Route::post('/security/two-factor', [UserSettingsController::class, 'enableTwoFactor'])->name('security.2fa.enable');
            Route::post('/security/two-factor/disable', [UserSettingsController::class, 'disableTwoFactor'])->name('security.2fa.disable');
            Route::post('/security/two-factor/recovery-codes', [UserSettingsController::class, 'regenerateRecoveryCodes'])->name('security.2fa.recovery-codes');

            // Privacy & GDPR
            Route::get('/privacy', [PrivacyController::class, 'index'])->name('privacy');
            Route::post('/privacy/export', [PrivacyController::class, 'requestExport'])->name('privacy.export');
            Route::get('/privacy/export/{dataExportRequest}/download', [PrivacyController::class, 'downloadExport'])->name('privacy.export.download');
            Route::post('/privacy/preferences', [PrivacyController::class, 'updatePreferences'])->name('privacy.preferences');
            // Scheduling a new deletion is gated by the feature flag; cancelling one is
            // deliberately left open so a user with a pending deletion is never trapped
            // if an admin disables the feature afterwards.
            Route::post('/privacy/schedule-deletion', [PrivacyController::class, 'scheduleDeletion'])->middleware('account.deletion')->name('privacy.schedule-deletion');
            Route::post('/privacy/cancel-deletion', [PrivacyController::class, 'cancelDeletion'])->name('privacy.cancel-deletion');
            Route::post('/privacy/sessions/revoke', [PrivacyController::class, 'revokeSession'])->name('privacy.sessions.revoke');
            Route::post('/privacy/sign-out-all', [PrivacyController::class, 'signOutAllDevices'])->name('privacy.sign-out-all');


            // Profile actions (PUT endpoints, no UI page)
            Route::put('/profile', [UserSettingsController::class, 'updateProfile'])->name('profile.update');
            Route::put('/profile/password', [UserSettingsController::class, 'updatePassword'])->name('password.update');
            Route::post('/profile/avatar', [UserSettingsController::class, 'updateAvatar'])->name('avatar.update');

            // Phone verification (SMS OTP). Rate limiting is handled inside the
            // controller (RateLimiterService) so throttling returns an Inertia
            // redirect-back, not the JSON that the throttle middleware emits.
            Route::post('/profile/phone/send-otp', [UserSettingsController::class, 'sendPhoneOtp'])->name('profile.phone.send-otp');
            Route::post('/profile/phone/verify-otp', [UserSettingsController::class, 'verifyPhoneOtp'])->name('profile.phone.verify-otp');

            // BYOK (bring-your-own-key)
            Route::middleware('byok')->group(function () {
                Route::get('/byok', [UserSettingsController::class, 'byok'])->name('byok');
                Route::post('/byok', [UserSettingsController::class, 'storeByok'])->name('byok.store');
                Route::delete('/byok/{key}', [UserSettingsController::class, 'destroyByok'])->name('byok.destroy');
            });

            // Billing
            Route::get('/billing', [\App\Http\Controllers\Billing\BillingController::class, 'index'])->name('billing');

            // Credit Top-Up
            Route::middleware('premium')->group(function () {
                Route::get('/credit-topup', [CreditTopupController::class, 'index'])->name('credit-topup');
                Route::post('/credit-topup/calculate', [CreditTopupController::class, 'calculate'])->name('credit-topup.calculate');
                Route::post('/credit-topup/checkout', [CreditTopupController::class, 'checkout'])->name('credit-topup.checkout');
            });


            // Account Deletion (cancel left open — see privacy.cancel-deletion above)
            Route::post('/account/delete', [UserSettingsController::class, 'requestAccountDeletion'])->middleware('account.deletion')->name('account.delete');
            Route::post('/account/delete/cancel', [UserSettingsController::class, 'cancelAccountDeletion'])->name('account.delete.cancel');

            // Affiliate
            Route::middleware('affiliate')->group(function () {
                Route::get('/affiliate', [AffiliateController::class, 'dashboard'])->name('affiliate');
                Route::get('/affiliate/chart', [AffiliateController::class, 'chartData'])->name('affiliate.chart');
                Route::post('/affiliate/alias', [AffiliateController::class, 'updateAlias'])->name('affiliate.alias.update');
                Route::post('/affiliate/payouts', [AffiliateController::class, 'storePayout'])->name('affiliate.payouts.store');
            });

            // Favorites
            Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');

            // Documents
            Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');

            // Support
            Route::middleware('tickets')->group(function () {
                Route::get('/support', [SupportTicketController::class, 'index'])->name('support.index');
                Route::post('/support/tickets', [SupportTicketController::class, 'store'])->middleware('throttle:public,5,3600')->name('support.tickets.store');
                Route::get('/support/tickets/{ticket}', [SupportTicketController::class, 'show'])->name('support.tickets.show');
                Route::post('/support/tickets/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('support.tickets.reply');
                Route::post('/support/tickets/{ticket}/resolve', [SupportTicketController::class, 'resolve'])->name('support.tickets.resolve');
                Route::post('/support/tickets/{ticket}/rate', [SupportTicketController::class, 'rate'])->name('support.tickets.rate');
            });

            // Generation History
            Route::prefix('history')->name('history.')->group(function () {
                Route::get('/', [HistoryController::class, 'index'])->name('index');
                Route::get('/tool/{toolSlug}', [HistoryController::class, 'byTool'])->name('by-tool');
                Route::post('/{history}/restore', [HistoryController::class, 'restore'])->name('restore');
                Route::post('/{history}/favorite', [HistoryController::class, 'favorite'])->name('favorite');
                Route::post('/{historyA}/diff/{historyB}', [HistoryController::class, 'diff'])->name('diff');
                Route::put('/{history}/label', [HistoryController::class, 'label'])->name('label');
                Route::delete('/{history}', [HistoryController::class, 'destroy'])->name('destroy');
            });

            // Usage Dashboard
            Route::get('/usage', [UsageDashboardController::class, 'index'])->name('usage.index');
            Route::get('/usage/chart', [UsageDashboardController::class, 'chart'])->name('usage.chart');
            Route::post('/usage/export', [UsageDashboardController::class, 'export'])->name('usage.export');

            // Dashboard Chart API
            Route::get('/chart', [DashboardController::class, 'chartData'])->name('chart.data');

            // Tool Collections
            Route::prefix('collections')->name('collections.')->group(function () {
                Route::get('/', [CollectionController::class, 'index'])->name('index');
                Route::post('/', [CollectionController::class, 'store'])->name('store');
                Route::get('/{collection}', [CollectionController::class, 'show'])->name('show');
                Route::put('/{collection}', [CollectionController::class, 'update'])->name('update');
                Route::delete('/{collection}', [CollectionController::class, 'destroy'])->name('destroy');
                Route::post('/{collection}/tools', [CollectionController::class, 'addTool'])->name('tools.add');
                Route::delete('/{collection}/tools/{slug}', [CollectionController::class, 'removeTool'])->name('tools.remove');
                Route::patch('/{collection}/reorder', [CollectionController::class, 'reorder'])->name('reorder');
            });

            // AI Playground
            Route::middleware('playground')->group(function () {
                Route::get('/playground', [PlaygroundController::class, 'index'])->name('playground.index');
                Route::post('/playground/run', [PlaygroundController::class, 'run'])->name('playground.run');
                Route::post('/playground/share', [PlaygroundController::class, 'share'])->name('playground.share');
            });

            // Quick Tool Chains
            Route::prefix('chains')->name('chains.')->middleware('chains')->group(function () {
                Route::get('/', [ChainController::class, 'index'])->name('index');
                Route::get('/create', [ChainController::class, 'create'])->name('create');
                Route::post('/', [ChainController::class, 'store'])->name('store');
                Route::get('/{chain}', [ChainController::class, 'show'])->name('show');
                Route::post('/{chain}/run', [ChainController::class, 'run'])->name('run');
                Route::put('/{chain}', [ChainController::class, 'update'])->name('update');
                Route::delete('/{chain}', [ChainController::class, 'destroy'])->name('destroy');
            });

            // Embed Management
            Route::prefix('tool-embeds')->name('embeds.')->middleware('tool.embeds')->group(function () {
                Route::get('/', [ToolEmbedController::class, 'index'])->name('index');
                Route::post('/', [ToolEmbedController::class, 'store'])->name('store');
                Route::put('/{embed}', [ToolEmbedController::class, 'update'])->name('update');
                Route::delete('/{embed}', [ToolEmbedController::class, 'destroy'])->name('destroy');
                Route::post('/{embed}/regenerate-token', [ToolEmbedController::class, 'regenerateToken'])->name('regen');
            });

            // User Export
            Route::post('/export', [UserExportController::class, 'export'])->name('export');

            // Onboarding
            Route::prefix('onboarding')->name('onboarding.')->group(function () {
                Route::post('/skip', [OnboardingController::class, 'skip'])->name('skip');
                Route::post('/complete', [OnboardingController::class, 'complete'])->name('complete');
                Route::get('/tools/{useCase}', [OnboardingController::class, 'recommendedTools'])->name('tools');
                Route::get('/checklist', [OnboardingController::class, 'checklistState'])->name('checklist');
                Route::post('/tooltip/dismiss', [OnboardingController::class, 'dismissTooltip'])->name('tooltip.dismiss');
            });

            // UI preferences bag (credit-low banner dismissal, etc.)
            Route::put('/ui-preferences', [OnboardingController::class, 'updatePreferences'])->name('preferences.patch');

            // Notifications
            Route::prefix('notifications')->name('notifications.')->middleware('notifications')->group(function () {
                Route::get('/', [NotificationController::class, 'index'])->name('index');
                Route::get('/latest', [NotificationController::class, 'latest'])->name('latest');
                Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
                Route::post('/{notification}/read', [NotificationController::class, 'markRead'])->name('read');
                Route::put('/preferences', [NotificationController::class, 'updatePreferences'])->name('preferences.update');
            });
        });

        // Tool Favorites & Ratings (small POST endpoints)
        Route::post('/favorites/toggle', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
        Route::post('/tools/{toolSlug}/favorite', [ToolFavoriteController::class, 'toggle'])->name('tools.favorite');
        Route::post('/ratings', [OutputRatingController::class, 'store'])->name('ratings.store');
    });
});

// ─── AI Tools ───────────────────────────────
// cache.public: guests get a private (browser-only) Cache-Control on these
// read-only pages — CSRF-safe, opt-out via the public_page_cache_ttl setting.
Route::middleware('cache.public')->group(function () {
    Route::get('/ai-tools', [AiToolController::class, 'index'])->name('ai.tools.index');
    Route::get('/ai-tools/category/{slug}', [AiToolController::class, 'category'])->name('ai.tools.category');
    Route::get('/ai-tools/{slug}', [AiToolController::class, 'show'])->name('ai.tools.show');
});

// ─── RAG Tools Suite ────────────────────────
Route::middleware(['auth', 'verified'])->prefix('tools/rag')->name('rag.')->group(function () {
    Route::get('/{slug}', [App\Http\Controllers\RagToolController::class, 'show'])->name('show');
    Route::post('/{slug}/sessions', [App\Http\Controllers\RagToolController::class, 'store'])->name('sessions.store');
    Route::get('/sessions/{ulid}', [App\Http\Controllers\RagToolController::class, 'session'])->name('sessions.show');
    Route::get('/sessions/{ulid}/status', [App\Http\Controllers\RagToolController::class, 'status'])->name('sessions.status');
    Route::get('/sessions/{ulid}/file', [App\Http\Controllers\RagToolController::class, 'file'])->name('sessions.file');
    Route::post('/sessions/{ulid}/chat', [App\Http\Controllers\RagToolController::class, 'chat'])->middleware('throttle:text_gen')->name('sessions.chat');
    Route::post('/sessions/{ulid}/save-to-kb', [App\Http\Controllers\RagToolController::class, 'saveToKb'])->name('sessions.save');
    Route::get('/sessions/{ulid}/share', [App\Http\Controllers\RagToolController::class, 'getShareStatus'])->name('sessions.share_status');
    Route::post('/sessions/{ulid}/share', [App\Http\Controllers\RagToolController::class, 'share'])->name('sessions.share');
    Route::delete('/sessions/{ulid}/share', [App\Http\Controllers\RagToolController::class, 'unshare'])->name('sessions.unshare');
    Route::delete('/sessions/{ulid}', [App\Http\Controllers\RagToolController::class, 'destroy'])->name('sessions.destroy');
    Route::put('/sessions/{ulid}', [App\Http\Controllers\RagToolController::class, 'update'])->name('sessions.update');
    Route::get('/sessions/{ulid}/export', [App\Http\Controllers\RagToolController::class, 'exportChat'])->name('sessions.export');
});

Route::get('/shared/rag/{token}', [App\Http\Controllers\RagToolController::class, 'sharedView'])->name('rag.shared');

// Public playground share
Route::get('/playground/s/{uuid}', [PlaygroundController::class, 'showShare'])->middleware('playground')->name('playground.share.show');

// Public embed routes
Route::middleware([EmbedCorsMiddleware::class, 'tool.embeds'])->group(function () {
    Route::get('/embed/{token}', [EmbedController::class, 'show'])->name('embed.show')->middleware('throttle:public_tool,60,3600');
    Route::options('/embed/{token}/run', [EmbedController::class, 'runOptions'])->name('embed.run.options');
    Route::post('/embed/{token}/run', [EmbedController::class, 'run'])->name('embed.run')->middleware('throttle:public_tool,30,3600');
    Route::options('/embed/{token}/unlock', [EmbedController::class, 'unlockOptions'])->name('embed.unlock.options');
    Route::post('/embed/{token}/unlock', [EmbedController::class, 'unlock'])->name('embed.unlock')->middleware('throttle:public,5,900');
});

// ─── Blog ───────────────────────────────────
Route::middleware('blog')->group(function () {
    Route::get('/blog/rss', [BlogController::class, 'rss'])->name('blog.rss');
    // Read-only archive/detail pages get guest browser caching (see cache.public).
    Route::middleware('cache.public')->group(function () {
        Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
        Route::get('/blog/category/{slug}', [BlogController::class, 'category'])->name('blog.category');
        Route::get('/blog/tag/{slug}', [BlogController::class, 'tag'])->name('blog.tag');
        Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
    });
});

// ─── Sitemap & Robots ───────────────────────
Route::get('/sitemap.xml', [App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [App\Http\Controllers\RobotsController::class, 'index'])->name('robots');

// ─── Public Community ───────────────────────
Route::post('/comments', [CommentController::class, 'store'])->middleware('throttle:comments')->name('comments.store');
Route::patch('/comments/{comment}', [CommentController::class, 'update'])->middleware('auth')->name('comments.update');
Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->middleware('auth')->name('comments.delete');
Route::post('/comments/{comment}/like', [CommentController::class, 'like'])->middleware('throttle:comments,20,60')->name('comments.like');
Route::post('/comments/{comment}/report', [CommentController::class, 'report'])->middleware('throttle:comments')->name('comments.report');
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->middleware('throttle:newsletter')->name('newsletter.subscribe');
Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');
Route::get('/newsletter/confirm/{token}', [NewsletterController::class, 'confirm'])->name('newsletter.confirm');
Route::get('/newsletter/open/{campaign}/{email}', [NewsletterController::class, 'trackOpen'])->name('newsletter.open');

// ─── Ads ────────────────────────────────────
// Public tracking endpoints are rate-limited per IP to stop click/impression
// fraud and queue-flooding (each hit dispatches a job).
Route::get('/api/ads/{zone}', [AdController::class, 'getActive'])->name('ads.active');
Route::post('/api/ads/{ad}/view', [AdController::class, 'trackView'])->middleware('throttle:public,120,60')->name('ads.trackView');
Route::post('/api/ads/{ad}/click', [AdController::class, 'trackClick'])->middleware('throttle:public,60,60')->name('ads.trackClick');
Route::get('/ads/click/{ad}', [AdController::class, 'click'])->middleware('throttle:public,60,60')->name('ads.click');

// ─── Contact ────────────────────────────────
Route::post('/contact', [ContactController::class, 'store'])->middleware(['throttle:contact', 'contact'])->name('contact.store');

// ─── Payment Webhooks ───────────────────────
Route::post('/webhooks/stripe', [App\Http\Controllers\Billing\StripeWebhookController::class, 'handleWebhook'])->name('webhooks.stripe');
Route::post('/webhooks/paypal', [PaymentWebhookController::class, 'handle'])->name('webhooks.paypal')->defaults('gateway', 'paypal');
Route::match(['get', 'post'], '/webhooks/paddle', [PaymentWebhookController::class, 'handle'])->name('webhooks.paddle')->defaults('gateway', 'paddle');
Route::post('/webhooks/razorpay', [PaymentWebhookController::class, 'handle'])->name('webhooks.razorpay')->defaults('gateway', 'razorpay');
Route::match(['get', 'post'], '/webhooks/sslcommerz', [PaymentWebhookController::class, 'handle'])->name('webhooks.sslcommerz')->defaults('gateway', 'sslcommerz');
Route::post('/webhooks/coingate', [PaymentWebhookController::class, 'handle'])->name('webhooks.coingate')->defaults('gateway', 'coingate');
Route::post('/webhooks/paystack', [PaymentWebhookController::class, 'handle'])->name('webhooks.paystack')->defaults('gateway', 'paystack');
Route::match(['get', 'post'], '/webhooks/2checkout', [PaymentWebhookController::class, 'handle'])->name('webhooks.2checkout')->defaults('gateway', '2checkout');


// ─── Dynamic CMS Pages ──────────────────────
Route::post('/{slug}/password', [PageController::class, 'unlock'])->middleware('throttle:public,10,60')->name('page.password');

// ─── Voiceover Studio (addon — must be before CMS catch-all) ──────────
Route::middleware(['web', 'auth'])->prefix('voiceover-studio')->name('addon.vo.user.')->group(function () {
    Route::get('/', [\Addons\AiVoiceover\Http\Controllers\User\StudioController::class, 'index'])->name('studio');
    Route::post('/projects', [\Addons\AiVoiceover\Http\Controllers\User\StudioController::class, 'storeProject'])->name('projects.store');
    Route::get('/projects/{project:ulid}', [\Addons\AiVoiceover\Http\Controllers\User\StudioController::class, 'showProject'])->name('projects.show');
    Route::put('/projects/{project:ulid}', [\Addons\AiVoiceover\Http\Controllers\User\StudioController::class, 'updateProject'])->name('projects.update');
    Route::delete('/projects/{project:ulid}', [\Addons\AiVoiceover\Http\Controllers\User\StudioController::class, 'destroyProject'])->name('projects.destroy');
    Route::post('/projects/{project:ulid}/episodes', [\Addons\AiVoiceover\Http\Controllers\User\StudioController::class, 'storeEpisode'])->middleware('throttle:20,1')->name('episodes.store');
    Route::delete('/episodes/{episode:ulid}', [\Addons\AiVoiceover\Http\Controllers\User\StudioController::class, 'destroyEpisode'])->name('episodes.destroy');
    Route::post('/episodes/{episode:ulid}/transcribe', [\Addons\AiVoiceover\Http\Controllers\User\StudioController::class, 'transcribeEpisode'])->name('episodes.transcribe');
    Route::post('/episodes/{episode:ulid}/share', [\Addons\AiVoiceover\Http\Controllers\User\StudioController::class, 'toggleShare'])->name('episodes.share');
    Route::post('/episodes/{episode:ulid}/publish', [\Addons\AiVoiceover\Http\Controllers\User\StudioController::class, 'publishEpisode'])->name('episodes.publish');
    Route::post('/episodes/{episode:ulid}/unpublish', [\Addons\AiVoiceover\Http\Controllers\User\StudioController::class, 'unpublishEpisode'])->name('episodes.unpublish');
    Route::get('/episodes/{episode:ulid}/download', [\Addons\AiVoiceover\Http\Controllers\User\StudioController::class, 'download'])->name('episodes.download');
    Route::post('/music/upload', [\Addons\AiVoiceover\Http\Controllers\User\StudioController::class, 'uploadMusic'])->name('music.upload');
    Route::post('/auto-split', [\Addons\AiVoiceover\Http\Controllers\User\StudioController::class, 'autoSplitScript'])->middleware('throttle:10,1')->name('auto-split');
});

// Podcast public routes (no auth)
Route::middleware(['web'])->prefix('podcast')->name('addon.vo.public.')->group(function () {
    Route::get('/rss/{token}', [\Addons\AiVoiceover\Http\Controllers\Public\PodcastController::class, 'rss'])->name('rss');
    Route::get('/player/{token}', [\Addons\AiVoiceover\Http\Controllers\Public\PodcastController::class, 'sharePlayer'])->name('player');
});

// ─── Content Repurposer (addon — must be before CMS catch-all) ──────────
Route::middleware(['web', 'auth'])->prefix('content-repurposer')->name('addon.rp.user.')->group(function () {
    Route::get('/', [\Addons\AiRepurposer\Http\Controllers\RepurposerController::class, 'index'])->name('index');
    Route::post('/', [\Addons\AiRepurposer\Http\Controllers\RepurposerController::class, 'store'])->middleware('throttle:10,1');
    Route::post('/bulk', [\Addons\AiRepurposer\Http\Controllers\RepurposerController::class, 'storeBulk'])->middleware('throttle:3,1')->name('bulk');
    Route::get('/{job}', [\Addons\AiRepurposer\Http\Controllers\RepurposerController::class, 'show'])->name('show');
    Route::get('/{job}/status', [\Addons\AiRepurposer\Http\Controllers\RepurposerController::class, 'status'])->name('status');
    Route::post('/{job}/regenerate/{format}', [\Addons\AiRepurposer\Http\Controllers\RepurposerController::class, 'regenerate'])->name('regenerate');
    Route::post('/outputs/{output}/save-blog', [\Addons\AiRepurposer\Http\Controllers\RepurposerController::class, 'saveToBlog'])->name('save-blog');
    Route::delete('/{job}', [\Addons\AiRepurposer\Http\Controllers\RepurposerController::class, 'destroy'])->name('destroy');
});

// The `where` below replaces Laravel's default `[^/]+` for a route parameter, and `.*`
// matches slashes — so this catch-all also swallows multi-segment paths. Addon routes
// register after core ones, so any addon path whose first segment is missing from this
// lookahead is shadowed by this route and 404s. That is why the list exists.
//
// `share/` carries its trailing slash deliberately: the chatbot's shared-conversation
// links (/share/{token}) were being shadowed, while a CMS page slugged "share" or
// "share-your-story" must still resolve here. The bare entries above are prefix matches,
// so each one also blocks same-prefixed page slugs (a page slugged "chatgpt-guide" is
// unreachable today) — prefer the trailing-slash form when adding to this list.
Route::get('/{slug}', [PageController::class, 'show'])->middleware('cache.public')->where('slug', '^(?!admin|api|install|chat|share/|ai-tools|blog|help|image-editor|ai-image|social|video|video-creator).*$')->name('page.show');

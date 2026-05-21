<?php

use App\Http\Controllers\AdController;
use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\AI\DocumentController;
use App\Http\Controllers\AI\TemplateController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\LiveSearchController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| MakeAI web routes — organized by feature area.
|
*/

// ─── Admin Routes ───────────────────────────
Route::middleware('web')->prefix('admin')->group(base_path('routes/admin.php'));

// ─── Public ─────────────────────────────────
Route::get('/', function () {
    $savedConfig = Setting::getValue('homepage_config');

    $testimonials = Testimonial::active()
        ->ordered()
        ->get(['id', 'name', 'role', 'company', 'avatar', 'content', 'rating', 'is_featured', 'source'])
        ->toArray();

    $faqs = Faq::active()
        ->ordered()
        ->with('category:id,name,sort_order')
        ->get(['id', 'question', 'answer', 'category_id', 'sort_order'])
        ->toArray();

    return Inertia::render('Welcome', [
        'homepage' => is_array($savedConfig) ? $savedConfig : null,
        'testimonials' => $testimonials,
        'faqs' => $faqs,
    ]);
})->name('home');
Route::get('/pricing', [PricingController::class, 'index'])->name('pricing');
Route::get('/ref/{code}', [AffiliateController::class, 'capture'])->name('affiliate.capture');
Route::post('/locale', [LocaleController::class, 'switch'])->name('locale.switch');
Route::get('/live-search', LiveSearchController::class)->middleware('throttle:60,1')->name('live-search');

// ─── Guest Auth ─────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->name('login.attempt');
    Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('register', [RegisterController::class, 'register'])->name('register.attempt');

    // Password Reset
    Route::get('forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.update');
});

// ─── Authenticated ──────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    // Email Verification
    Route::get('verify-email', [VerificationController::class, 'notice'])->name('verification.notice');
    Route::post('verify-email', [VerificationController::class, 'verify'])->name('verification.verify');
    Route::post('verify-email/resend', [VerificationController::class, 'resend'])->name('verification.resend');

    // Theme Preference (Accessible even if not verified)
    Route::post('/profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme');

    // User Dashboard + AI Writer (verified only)
    Route::middleware('verified')->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('User/Dashboard');
        })->name('user.dashboard');

        Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
        Route::post('/checkout/coupon-preview', [CheckoutController::class, 'previewCoupon'])->name('checkout.coupon-preview');
        Route::post('/checkout/session', [CheckoutController::class, 'createSession'])->name('checkout.session');
        Route::get('/checkout/bank-transfer/{payment}', [CheckoutController::class, 'bankInstructions'])->name('checkout.bank.show');
        Route::post('/checkout/bank-transfer/{payment}/proof', [CheckoutController::class, 'uploadBankProof'])->name('checkout.bank.proof');
        Route::get('/checkout/paypal/return/{payment}', [CheckoutController::class, 'paypalReturn'])->name('checkout.paypal.return');
        Route::get('/checkout/pending/{payment}', [CheckoutController::class, 'pending'])->name('checkout.pending');
        Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');

        Route::get('/dashboard/affiliate', [AffiliateController::class, 'dashboard'])->name('affiliate.dashboard');
        Route::post('/dashboard/affiliate/alias', [AffiliateController::class, 'updateAlias'])->name('affiliate.alias.update');
        Route::post('/dashboard/affiliate/payouts', [AffiliateController::class, 'storePayout'])->name('affiliate.payouts.store');

        Route::get('/documents/{document}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
        Route::patch('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');

        Route::get('/support', [SupportTicketController::class, 'index'])->name('support.index');
        Route::post('/support/tickets', [SupportTicketController::class, 'store'])->name('support.tickets.store');
        Route::get('/support/tickets/{ticket}', [SupportTicketController::class, 'show'])->name('support.tickets.show');
        Route::post('/support/tickets/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('support.tickets.reply');
        Route::post('/support/tickets/{ticket}/resolve', [SupportTicketController::class, 'resolve'])->name('support.tickets.resolve');
        Route::post('/support/tickets/{ticket}/rate', [SupportTicketController::class, 'rate'])->name('support.tickets.rate');

        Route::get('/dashboard/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
        Route::post('/favorites/toggle', [FavoriteController::class, 'toggle'])->name('favorites.toggle');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/latest', [NotificationController::class, 'latest'])->name('notifications.latest');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    });
});

// ─── AI Tools ───────────────────────────────
Route::get('/ai-tools', [TemplateController::class, 'index'])->name('ai.tools.index');
Route::get('/ai-tools/{slug}', [TemplateController::class, 'show'])->name('ai.tools.show');
Route::get('/ai-tools/category/{slug}', [TemplateController::class, 'category'])->name('ai.tools.category');

// ─── Blog ───────────────────────────────────
Route::get('/blog/rss', [BlogController::class, 'rss'])->name('blog.rss');
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/category/{slug}', [BlogController::class, 'category'])->name('blog.category');
Route::get('/blog/tag/{slug}', [BlogController::class, 'tag'])->name('blog.tag');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

// ─── Public Community ───────────────────────
Route::post('/comments', [CommentController::class, 'store'])->middleware('throttle:5,1')->name('comments.store');
Route::patch('/comments/{comment}', [CommentController::class, 'update'])->middleware('auth')->name('comments.update');
Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->middleware('auth')->name('comments.delete');
Route::post('/comments/{comment}/like', [CommentController::class, 'like'])->middleware('throttle:20,1')->name('comments.like');
Route::post('/comments/{comment}/report', [CommentController::class, 'report'])->middleware('throttle:5,1')->name('comments.report');
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->middleware('throttle:3,60')->name('newsletter.subscribe');
Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');

// ─── Ads ────────────────────────────────────
Route::get('/api/ads/{zone}', [AdController::class, 'getActive'])->name('ads.active');
Route::post('/api/ads/{ad}/view', [AdController::class, 'trackView'])->name('ads.trackView');
Route::post('/api/ads/{ad}/click', [AdController::class, 'trackClick'])->name('ads.trackClick');
Route::get('/ads/click/{ad}', [AdController::class, 'click'])->name('ads.click');

// ─── Contact ────────────────────────────────
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

// ─── Payment Webhooks ───────────────────────
Route::post('/webhooks/stripe', [PaymentWebhookController::class, 'stripe'])->name('webhooks.stripe');
Route::post('/webhooks/paypal', [PaymentWebhookController::class, 'paypal'])->name('webhooks.paypal');
Route::match(['get', 'post'], '/webhooks/paddle', [PaymentWebhookController::class, 'paddle'])->name('webhooks.paddle');
Route::post('/webhooks/razorpay', [PaymentWebhookController::class, 'razorpay'])->name('webhooks.razorpay');
Route::match(['get', 'post'], '/webhooks/sslcommerz', [PaymentWebhookController::class, 'sslcommerz'])->name('webhooks.sslcommerz');
Route::post('/webhooks/coingate', [PaymentWebhookController::class, 'coingate'])->name('webhooks.coingate');
Route::post('/webhooks/paystack', [PaymentWebhookController::class, 'paystack'])->name('webhooks.paystack');
Route::match(['get', 'post'], '/webhooks/2checkout', [PaymentWebhookController::class, 'twoCheckout'])->name('webhooks.2checkout');

// ─── Dynamic CMS Pages ──────────────────────
Route::post('/{slug}/password', function (Request $request, string $slug) {
    $page = Page::query()->where('slug', $slug)->published()->firstOrFail();

    $request->validate([
        'password' => ['required', 'string'],
    ]);

    $password = (string) $page->getAttribute('password');
    $validPassword = Hash::check($request->string('password')->toString(), $password)
        || hash_equals($password, $request->string('password')->toString());

    if (! $validPassword) {
        return back()->withErrors(['password' => translate('The password is incorrect.')]);
    }

    $request->session()->put("page_unlocked_{$page->id}", true);

    return redirect()->route('page.show', $page->slug);
})->name('page.password');

Route::get('/{slug}', function (string $slug) {
    $page = Page::query()->where('slug', $slug)->published()->firstOrFail();
    $isPasswordProtected = filled($page->getAttribute('password'));
    $isUnlocked = ! $isPasswordProtected || session()->has("page_unlocked_{$page->id}");

    if (! $isUnlocked) {
        return Inertia::render('PagePassword', [
            'page' => $page->only(['id', 'title', 'slug', 'meta_title', 'meta_description']),
        ]);
    }

    $canonical = route('page.show', $page->slug);
    $ogImage = $page->og_image ?: $page->featured_image;
    $description = $page->meta_description ?: $page->excerpt;

    return Inertia::render('Page', [
        'page' => $page,
        'seo' => [
            'title' => $page->meta_title ?: $page->title,
            'description' => $description,
            'keywords' => $page->meta_keywords,
            'canonical' => $canonical,
            'robots' => $isPasswordProtected ? 'noindex,nofollow' : 'index,follow',
            'og_image' => $ogImage ? asset('storage/'.$ogImage) : null,
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => $page->title,
                'description' => $description,
                'url' => $canonical,
                'breadcrumb' => [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => settings('app_name', 'Application'), 'item' => route('home')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => $page->title, 'item' => $canonical],
                    ],
                ],
            ],
        ],
        'contactSettings' => $page->slug === 'contact' ? [
            'enabled' => (bool) settings('contact_form_enabled', true),
            'subject_mode' => settings('contact_subject_mode', 'text'),
            'subject_options' => collect(explode("\n", (string) settings('contact_subject_options', '')))
                ->map(fn ($subject) => trim($subject))
                ->filter()
                ->values()
                ->all(),
            'success_message' => settings('contact_success_message', 'Your message has been sent successfully. We will get back to you soon!'),
        ] : null,
    ]);
})->name('page.show');

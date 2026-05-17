<?php

use App\Http\Controllers\AdController;
use App\Http\Controllers\AI\DocumentController;
use App\Http\Controllers\AI\TemplateController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ProfileController;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Testimonial;
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

        Route::get('/documents/{document}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
        Route::patch('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');

        // Community Features
        Route::post('/comments', [CommentController::class, 'store'])->name('comments.store');
        Route::post('/comments/{comment}/like', [CommentController::class, 'like'])->name('comments.like');
        Route::post('/favorites/toggle', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    });
});

// ─── AI Tools ───────────────────────────────
Route::get('/ai-tools', [TemplateController::class, 'index'])->name('ai.tools.index');
Route::get('/ai-tools/{slug}', [TemplateController::class, 'show'])->name('ai.tools.show');
Route::get('/ai-tools/category/{slug}', [TemplateController::class, 'category'])->name('ai.tools.category');

// ─── Public Community ───────────────────────
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->name('newsletter.subscribe');
Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');

// ─── Ads ────────────────────────────────────
Route::get('/api/ads/{placement}', [AdController::class, 'getActive'])->name('ads.active');
Route::post('/api/ads/{ad}/view', [AdController::class, 'trackView'])->name('ads.trackView');
Route::post('/api/ads/{ad}/click', [AdController::class, 'trackClick'])->name('ads.trackClick');

// ─── Contact ────────────────────────────────
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

// ─── Dynamic CMS Pages ──────────────────────
Route::get('/{slug}', function (string $slug) {
    $page = Page::where('slug', $slug)->published()->firstOrFail();

    return Inertia::render('Page', ['page' => $page]);
})->name('page.show');

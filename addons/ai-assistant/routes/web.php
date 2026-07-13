<?php

use Addons\AiAssistant\Controllers\AiAssistantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AI Assistant Routes
|--------------------------------------------------------------------------
|
| Routes loaded via loadRoutesFrom() do NOT automatically get the 'web'
| middleware group. We must wrap them explicitly so that sessions, cookies,
| and CSRF are available — otherwise auth guards return null.
|
| Throttling note: the `throttle` alias here is the app's custom ThrottleAiRequests,
| NOT Laravel's built-in limiter. Its signature is throttle:{category},{max},{window}
| — so `throttle:30,1` did NOT mean "30/min"; it meant category="30", max=1 request,
| which capped every one of these endpoints at ONE request per minute. Each route below
| now names a real category with a sensible per-minute budget:
|   - text_gen        : the app-wide, tier-aware AI generation limiter (chat).
|   - assistant_api   : cheap JSON reads/writes fired by the widget (transcript, commands,
|                       feedback, rules). Generous — the widget calls these on every open.
|   - assistant_upload: file text-extraction; heavier, so tighter.
| Custom category names get their own isolated counter (keyed by IP), so a burst of
| transcript reads can never exhaust the chat budget or vice-versa.
*/

Route::middleware('web')->group(function () {

    /*
     | Frontend chat is deliberately NOT behind 'auth'.
     |
     | Who may use the assistant is the admin buyer's decision, expressed through the
     | `enabled` + `show_to` settings (all / logged_in / pro_only). An `auth` middleware
     | here would hard-code "logged_in" and make `show_to: all` a lie — which is exactly
     | what it was before. AiAssistantService::isVisibleForUser() is the single gate, and
     | every one of these actions calls it.
     |
     | Guests are metered by TokenGuard's per-IP quota and by the assistant's own
     | IP-keyed guest_daily_message_limit, on top of the throttle.
     */
    Route::post('/api/assistant/chat', [AiAssistantController::class, 'chat'])
        ->middleware('throttle:text_gen')
        ->name('addon.ai-assistant.chat');

    Route::post('/api/assistant/feedback', [AiAssistantController::class, 'feedback'])
        ->middleware('throttle:assistant_api,60,60')
        ->name('addon.ai-assistant.feedback');

    Route::post('/api/assistant/extract', [AiAssistantController::class, 'extractText'])
        ->middleware('throttle:assistant_upload,20,60')
        ->name('addon.ai-assistant.extract');

    Route::get('/api/assistant/commands', [AiAssistantController::class, 'commands'])
        ->middleware('throttle:assistant_api,120,60')
        ->name('addon.ai-assistant.commands');

    Route::get('/api/assistant/transcript', [AiAssistantController::class, 'transcript'])
        ->middleware('throttle:assistant_api,120,60')
        ->name('addon.ai-assistant.transcript');

    // Chat history: list the signed-in user's past conversations, and delete one.
    Route::middleware('throttle:assistant_api,120,60')->group(function () {
        Route::get('/api/assistant/conversations', [AiAssistantController::class, 'conversations'])
            ->name('addon.ai-assistant.conversations');
        Route::delete('/api/assistant/conversations', [AiAssistantController::class, 'deleteConversation'])
            ->name('addon.ai-assistant.conversations.delete');
    });

    // Help Center panel (published KB articles), messaging, and CSAT.
    Route::middleware('throttle:assistant_api,120,60')->group(function () {
        Route::get('/api/assistant/help/articles', [AiAssistantController::class, 'helpArticles'])
            ->name('addon.ai-assistant.help.articles');
        Route::get('/api/assistant/help/search', [AiAssistantController::class, 'helpSearch'])
            ->name('addon.ai-assistant.help.search');
        Route::get('/api/assistant/help/articles/{slug}', [AiAssistantController::class, 'helpArticle'])
            ->name('addon.ai-assistant.help.article');
    });

    Route::post('/api/assistant/message', [AiAssistantController::class, 'submitMessage'])
        ->middleware('throttle:contact')
        ->name('addon.ai-assistant.message');

    Route::post('/api/assistant/csat', [AiAssistantController::class, 'csat'])
        ->middleware('throttle:assistant_api,30,60')
        ->name('addon.ai-assistant.csat');

    // Admin feedback review page — a full Inertia page, so it carries no AI throttle.
    Route::middleware('admin.auth')->group(function () {
        Route::get('/admin/ai-assistant/feedback', [AiAssistantController::class, 'feedbackIndex'])
            ->name('admin.addons.ai-assistant.feedback');
    });

    // Admin chat + management — requires admin auth (project's AdminAuth middleware).
    // Admins authenticate on the `admin` guard and are not App\Models\User instances, so
    // ThrottleAiRequests treats them as a guest tier; the explicit budgets below override
    // the tier default so admin chat isn't throttled down to the guest AI rate.
    Route::middleware('admin.auth')->group(function () {
        Route::post('/api/admin/assistant/chat', [AiAssistantController::class, 'adminChat'])
            ->middleware('throttle:text_gen,120,60')
            ->name('addon.ai-assistant.admin.chat');

        Route::post('/api/admin/assistant/extract', [AiAssistantController::class, 'extractText'])
            ->middleware('throttle:assistant_upload,40,60')
            ->name('addon.ai-assistant.admin.extract');

        Route::get('/api/admin/assistant/commands', [AiAssistantController::class, 'commands'])
            ->middleware('throttle:assistant_api,120,60')
            ->name('addon.ai-assistant.admin.commands');

        Route::get('/api/admin/assistant/transcript', [AiAssistantController::class, 'transcript'])
            ->middleware('throttle:assistant_api,120,60')
            ->name('addon.ai-assistant.admin.transcript');

        Route::get('/api/admin/assistant/conversations', [AiAssistantController::class, 'conversations'])
            ->middleware('throttle:assistant_api,120,60')
            ->name('addon.ai-assistant.admin.conversations');

        // Feedback posted from the admin widget. The frontend route sits behind the `web`
        // guard and returns a null user for an admin-guard session, so the admin widget
        // needs its own endpoint — previously it POSTed to the frontend one, 401'd, and
        // the failure was swallowed client-side.
        Route::post('/api/admin/assistant/feedback', [AiAssistantController::class, 'feedback'])
            ->middleware('throttle:assistant_api,60,60')
            ->name('addon.ai-assistant.admin.feedback');

        // Automation rules management.
        Route::middleware('throttle:assistant_api,60,60')->group(function () {
            Route::get('/api/admin/assistant/rules', [AiAssistantController::class, 'listRules'])
                ->name('addon.ai-assistant.admin.rules.index');
            Route::post('/api/admin/assistant/rules', [AiAssistantController::class, 'storeRule'])
                ->name('addon.ai-assistant.admin.rules.store');
            Route::put('/api/admin/assistant/rules/{rule}', [AiAssistantController::class, 'updateRule'])
                ->name('addon.ai-assistant.admin.rules.update');
            Route::delete('/api/admin/assistant/rules/{rule}', [AiAssistantController::class, 'deleteRule'])
                ->name('addon.ai-assistant.admin.rules.delete');
        });
    });

});

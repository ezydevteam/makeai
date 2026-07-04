<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['web'])->group(function () {
    Route::get('/chat/{ulid?}', function (?string $ulid = null) {
        return Inertia::render('Addons/ai-chatbot/Chat/Index', [
            'hide_header' => true,
            'hide_footer' => true,
            'default_chat_model' => addon_setting('ai-chatbot', 'default_chat_model', 'gpt-4o-mini'),
            'allow_model_select' => (bool) addon_setting('ai-chatbot', 'allow_model_select', true),
            'show_provider_models' => (bool) addon_setting('ai-chatbot', 'show_provider_models', true),
            'show_custom_models' => (bool) addon_setting('ai-chatbot', 'show_custom_models', false),
            'custom_models' => addon_setting('ai-chatbot', 'custom_models', []),
            'allow_guest_messages' => (bool) addon_setting('ai-chatbot', 'allow_guest_messages', false),
            'available_models' => app(\App\Services\AI\ProviderRegistry::class)->availableModels(),
            'chat_credits_low_threshold' => (int) settings('chat_credits_low_threshold', 100),
            'active_chat_ulid' => $ulid,
            'kb_available' => class_exists(\Addons\PublicKnowledgeBase\Services\KbSearchService::class),
        ]);
    })->name('chat.index');

    Route::get('/share/{token}', function (string $token) {
        return Inertia::render('Addons/ai-chatbot/Chat/SharedView', [
            'share_token' => $token,
        ]);
    })->name('chat.share');
});

Route::middleware(['web', 'admin.auth', 'admin.audit'])->prefix('admin/ai-chatbot')->group(function () {
    Route::get('/analytics', [\Addons\AiChatbot\Http\Controllers\Admin\AnalyticsController::class, 'index'])->name('admin.ai-chatbot.analytics');
});

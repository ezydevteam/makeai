<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['web'])->group(function () {
    Route::get('/chat/{ulid?}', function (?string $ulid = null) {
        return Inertia::render('Addons/ai-chatbot/Chat/Index', [
            'hide_header' => (bool) addon_setting('ai-chatbot', 'hide_site_header', false),
            'hide_footer' => (bool) addon_setting('ai-chatbot', 'hide_site_footer', false),
            'default_chat_model' => addon_setting('ai-chatbot', 'default_chat_model', 'gpt-4o-mini'),
            'allow_model_select' => (bool) addon_setting('ai-chatbot', 'allow_model_select', true),
            'show_friendly_model_names' => (bool) addon_setting('ai-chatbot', 'show_friendly_model_names', false),
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

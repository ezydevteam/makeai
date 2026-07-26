<?php

declare(strict_types=1);

namespace Addons\AiChatbot\Support;

/**
 * The chat's fallback model, in one place.
 *
 * Every surface that needs a model when nothing else resolves — the homepage provider, the
 * shared Inertia props, the streaming endpoint — used to spell its own literal, and all of
 * them spelled `gpt-4o-mini`, a slug the ai_models catalog no longer carries. ChatController
 * validates the resolved model against that catalog and refuses the message outright when it
 * is missing, so a retired default does not degrade the chat, it stops it.
 */
final class ChatDefaults
{
    /**
     * Last resort only. Must be a slug AiModelSeeder actually ships, and should stay a cheap
     * one: it is what an install that never opened the settings screen will chat with.
     */
    public const CHAT_MODEL = 'gpt-5.4-mini';

    /**
     * The admin's configured default, or the constant above when that setting is unset or
     * has been saved blank.
     */
    public static function chatModel(): string
    {
        $configured = addon_setting('ai-chatbot', 'default_chat_model', self::CHAT_MODEL);

        return is_string($configured) && $configured !== '' ? $configured : self::CHAT_MODEL;
    }
}

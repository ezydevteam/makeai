<?php

declare(strict_types=1);

namespace Addons\AiChatbot\Support;

use Addons\PublicKnowledgeBase\Services\KbSearchService;

/**
 * Gate for the chatbot's optional Knowledge Base (RAG) integration.
 *
 * The Knowledge Base lives in a separate, independently installable addon, so
 * the chatbot must treat it as absent unless it is genuinely usable.
 */
final class KnowledgeBase
{
    /**
     * Is the Knowledge Base addon actually there to be used?
     *
     * `class_exists()` on its own is NOT a sufficient check: `Addons\PublicKnowledgeBase\`
     * is a static PSR-4 mapping in composer.json, so the class autoloads whenever the
     * folder is merely present on disk — including for an addon that was never installed
     * (its kb_* tables don't exist) or one the admin has since deactivated. Activation
     * state lives in the `addons` table, which is what is_addon_active() reads.
     *
     * Deliberately does NOT consider the chatbot's own enable_knowledge_base toggle —
     * the admin settings screen uses this to decide whether to show that toggle, and
     * folding it in here would make the toggle disappear the moment it was switched off.
     */
    public static function installed(): bool
    {
        return class_exists(KbSearchService::class)
            && is_addon_active('public-knowledge-base');
    }

    /**
     * May the chatbot use RAG right now? The addon must be usable AND the admin must
     * have left the chatbot's Knowledge Base toggle on.
     */
    public static function available(): bool
    {
        return self::installed()
            && (bool) addon_setting('ai-chatbot', 'enable_knowledge_base', true);
    }

    /**
     * Base path for citation links. The KB's public prefix is admin-configurable
     * (defaults to "help"), so it must never be hardcoded on the chat side.
     */
    public static function articleBaseUrl(): string
    {
        $slug = trim((string) addon_setting('public-knowledge-base', 'public_slug', 'help'), '/');

        return '/'.($slug !== '' ? $slug : 'help').'/article';
    }
}

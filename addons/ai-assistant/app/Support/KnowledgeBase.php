<?php

declare(strict_types=1);

namespace Addons\AiAssistant\Support;

use Addons\AiKnowledgeBase\Services\KbSearchService;

/**
 * Gate for the assistant's optional Knowledge Base (RAG) integration.
 *
 * Mirrors Addons\AiChatbot\Support\KnowledgeBase — the Knowledge Base is a separate,
 * independently installable addon, so the assistant must treat it as absent unless it
 * is genuinely usable.
 */
final class KnowledgeBase
{
    /**
     * Is the Knowledge Base addon actually there to be used?
     *
     * class_exists() alone is NOT sufficient: Addons\AiKnowledgeBase\ is a static
     * PSR-4 mapping in composer.json, so the class autoloads whenever the folder is
     * merely present on disk — including for an addon that was never installed (its
     * kb_* tables don't exist) or one the admin has since deactivated. Activation state
     * lives in the `addons` table, which is what is_addon_active() reads.
     */
    public static function installed(): bool
    {
        return class_exists(KbSearchService::class)
            && is_addon_active('ai-knowledge-base');
    }

    /**
     * May the assistant use RAG right now? The addon must be usable AND the admin must
     * have left the assistant's own Knowledge Base toggle on.
     */
    public static function available(): bool
    {
        return self::installed()
            && (bool) addon_setting('ai-assistant', 'enable_knowledge_base', true);
    }

    /**
     * Base path for citation links. The KB's public prefix is admin-configurable
     * (defaults to "help"), so it must never be hardcoded on the assistant side.
     */
    public static function articleBaseUrl(): string
    {
        $slug = trim((string) addon_setting('ai-knowledge-base', 'public_slug', 'help'), '/');

        return '/' . ($slug !== '' ? $slug : 'help') . '/article';
    }

    /**
     * Retrieve ranked context for a query. Returns ['context' => string, 'sources' => array].
     * Never throws — a KB failure must degrade to an ungrounded answer, not a broken chat.
     */
    public static function context(string $query, int $topK = 5): array
    {
        if (! self::available()) {
            return ['context' => '', 'sources' => []];
        }

        try {
            return app(KbSearchService::class)->getRelevantContext($query, $topK);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('AI Assistant KB lookup failed: ' . $e->getMessage());

            return ['context' => '', 'sources' => []];
        }
    }
}

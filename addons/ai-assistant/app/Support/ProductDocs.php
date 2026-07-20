<?php

declare(strict_types=1);

namespace Addons\AiAssistant\Support;

use App\Services\Docs\ProductDocsService;
use Illuminate\Support\Facades\Log;

/**
 * Gate for grounding the ADMIN assistant on the MakeAI product documentation.
 *
 * Deliberately parallel to this addon's KnowledgeBase gate, but they ground on different
 * corpora for different audiences, and conflating them was the bug this fixes:
 *
 *  - KnowledgeBase  → the buyer's own help centre, written for THEIR customers. Right for
 *                     the public widget; noise for an admin asking how to configure MakeAI.
 *  - ProductDocs    → the MakeAI admin manual, shipped inside the release. Right for the
 *                     admin panel, and the only one of the two that is always there.
 *
 * Unlike the KB, this has no addon behind it and no setting to switch it off: the corpus
 * ships in the zip, so the only way it is unavailable is if someone stripped the folder.
 */
final class ProductDocs
{
    /**
     * Is there documentation to ground on?
     *
     * class_exists() guards the case where the addon is running against a core that
     * predates the docs service; hasCorpus() guards a stripped or empty docs folder. Either
     * way the assistant falls back to ungrounded chat rather than erroring.
     */
    public static function available(): bool
    {
        try {
            return class_exists(ProductDocsService::class)
                && app(ProductDocsService::class)->hasCorpus();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Ranked documentation context for a question, in the same shape as the KB's
     * getRelevantContext(), so the controller grounds both corpora with identical code.
     *
     * Never throws: a docs failure must degrade to an ungrounded answer, not a broken chat.
     *
     * @return array{context:string,sources:list<array{title:string,slug:string,url:?string}>}
     */
    public static function context(string $query, int $topK = 5): array
    {
        if (! self::available()) {
            return ['context' => '', 'sources' => []];
        }

        try {
            return app(ProductDocsService::class)->search($query, $topK);
        } catch (\Throwable $e) {
            Log::warning('AI Assistant product docs lookup failed: ' . $e->getMessage());

            return ['context' => '', 'sources' => []];
        }
    }
}

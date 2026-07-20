<?php

namespace App\Services;

class TiptapHtmlSanitizer
{
    /**
     * Standard allowlist matching ALL tags Tiptap's full editor can produce.
     */
    public const FULL_TAGS = '<p><br><strong><b><em><i><u><s><del><ins><mark><sub><sup><span><a><img><ul><ol><li><blockquote><h1><h2><h3><h4><h5><h6><table><thead><tbody><tr><th><td><pre><code><hr><details><summary><div>';

    /**
     * Safe subset — no images, tables, code blocks, details, or <h1>.
     * Used for contexts where complex layout isn't needed.
     */
    public const BASIC_TAGS = '<p><br><strong><b><em><i><u><s><sub><sup><a><ul><ol><li><blockquote><h2><h3><h4><h5><h6><hr>';

    /**
     * FAQ/answer-safe subset — text formatting + links only.
     * No images, tables, headings, or block-level media.
     */
    public const FAQ_TAGS = '<p><br><strong><b><em><i><u><a><ul><ol><li>';

    /**
     * Comment-safe subset — inline formatting only.
     */
    public const COMMENT_TAGS = '<p><br><strong><b><em><i><u><a>';

    /**
     * Strip unsafe content from HTML:
     * - Removes <script> blocks
     * - Removes on* event handlers
     * - Removes javascript: URIs in href/src attributes
     * - Strips disallowed HTML tags
     */
    public static function sanitize(string $html, string $allowedTags = self::FULL_TAGS): string
    {
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html) ?? '';
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        // Neutralize dangerous URI schemes in href/src. data:image/... is left
        // intact (legitimate inline images); only data:text/html and script-y
        // schemes are stripped.
        $html = preg_replace('/(href|src)\s*=\s*["\']\s*javascript:/i', '$1="#"', $html) ?? '';
        $html = preg_replace('/(href|src)\s*=\s*["\']\s*vbscript:/i', '$1="#"', $html) ?? '';
        $html = preg_replace('/(href|src)\s*=\s*["\']\s*data:\s*text\/html/i', '$1="#"', $html) ?? '';

        return strip_tags($html, $allowedTags);
    }
}

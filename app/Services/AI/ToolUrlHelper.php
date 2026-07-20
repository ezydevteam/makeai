<?php

namespace App\Services\AI;

/**
 * ToolUrlHelper — centralized canonical URL generation for tool pages.
 */
class ToolUrlHelper
{
    /**
     * Generate the full canonical URL for a tool page.
     */
    public function canonical(string $slug): string
    {
        return rtrim(config('app.url'), '/') . '/ai-tools' . ($slug ? '/' . $slug : '');
    }

    /**
     * Generate the full canonical URL for a tool category page.
     */
    public function categoryCanonical(string $slug): string
    {
        return rtrim(config('app.url'), '/') . '/ai-tools/category/' . $slug;
    }

    /**
     * Generate the relative path.
     */
    public function path(string $slug): string
    {
        return '/ai-tools/' . $slug;
    }

    /**
     * Generate the category relative path.
     */
    public function categoryPath(string $slug): string
    {
        return '/ai-tools/category/' . $slug;
    }
}

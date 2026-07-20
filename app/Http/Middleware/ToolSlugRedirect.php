<?php

namespace App\Http\Middleware;

use App\Services\AI\ToolSlugHistoryService;
use Closure;
use Illuminate\Http\Request;

/**
 * ToolSlugRedirect — issues 301 redirects for renamed tool/category slugs.
 *
 * Registered in bootstrap/app.php as 'tool.slug.redirect'.
 */
class ToolSlugRedirect
{
    public function __construct(private ToolSlugHistoryService $slugHistory) {}

    public function handle(Request $request, Closure $next)
    {
        $path = trim($request->path(), '/');

        // Match /ai-tools/{slug} or /ai-tools/category/{slug}
        if (preg_match('#^ai-tools/(?:category/)?([^/]+)$#', $path, $matches)) {
            $slug = $matches[1];
            $modelType = str_contains($path, 'category') ? 'category' : 'ai_tool';

            $target = $this->slugHistory->resolveChain($slug, $modelType);

            if ($target) {
                $newPath = str_replace("/{$slug}", "/{$target}", '/' . $path);
                // Prevent loops
                return redirect($newPath, 301);
            }
        }

        return $next($request);
    }
}

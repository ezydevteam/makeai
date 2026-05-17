<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\ToolCatalogCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public AI tools catalog API.
 *
 * These endpoints expose marketplace-safe tool metadata only. Prompt text stays
 * server-side for generation.
 */
class ToolCatalogController extends Controller
{
    public function __construct(
        private ToolCatalogCacheService $tools,
    ) {}

    /**
     * GET /api/v1/tools
     * GET /api/v1/tools?category=blog-content
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->tools->activeTools($request->query('category')),
        ]);
    }

    /**
     * GET /api/v1/tools/categories
     */
    public function categories(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->tools->activeCategories(),
        ]);
    }

    /**
     * GET /api/v1/tools/{slug}
     */
    public function show(string $slug): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->tools->toolBySlug($slug),
        ]);
    }
}

<?php

namespace App\Services\AI;

use App\Models\AiTool;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ToolCatalogCacheService
{
    public const CATEGORIES_KEY = 'makeai:categories:ai_tool';

    public const TOOL_LIST_KEY = 'makeai:tool:list';

    public const TOOL_KEY_PREFIX = 'makeai:tool:';

    public const TOOL_CATEGORY_LIST_PREFIX = 'makeai:tool:list:category:';

    private const TOOL_TTL_SECONDS = 3600;

    public function activeCategories(): Collection
    {
        return $this->cachedCollection(self::CATEGORIES_KEY, null, function () {
            return Category::query()
                ->aiTools()
                ->active()
                ->withCount(['activeTools as active_tools_count'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'slug',
                    'description',
                    'icon',
                    'color',
                    'requires_pro',
                    'requires_login',
                    'sort_order',
                    'tools_count',
                ])
                ->map(fn (Category $category) => $this->serializeCategory($category));
        });
    }

    public function activeTools(?string $categorySlug = null): Collection
    {
        $key = $categorySlug
            ? self::TOOL_CATEGORY_LIST_PREFIX.$categorySlug
            : self::TOOL_LIST_KEY;

        return $this->cachedCollection($key, self::TOOL_TTL_SECONDS, function () use ($categorySlug) {
            return AiTool::query()
                ->select($this->toolListColumns())
                ->active()
                ->whereHas('category', fn ($query) => $query->active()->ofType('ai_tool'))
                ->when($categorySlug, function ($query) use ($categorySlug) {
                    $query->whereHas('category', function ($q) use ($categorySlug) {
                        $q->active()->ofType('ai_tool')->where('slug', $categorySlug);
                    });
                })
                ->with(['category:id,name,slug,description,icon,color,requires_pro,requires_login,sort_order,tools_count'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (AiTool $tool) => $this->serializeTool($tool));
        });
    }

    public function toolBySlug(string $slug): array
    {
        return Cache::remember(self::TOOL_KEY_PREFIX.$slug, self::TOOL_TTL_SECONDS, function () use ($slug) {
            $tool = AiTool::query()
                ->select($this->toolDetailColumns())
                ->active()
                ->where('slug', $slug)
                ->whereHas('category', fn ($query) => $query->active()->ofType('ai_tool'))
                ->with(['category:id,name,slug,description,icon,color,requires_pro,requires_login,sort_order,tools_count'])
                ->firstOrFail();

            return array_merge($this->serializeTool($tool, true), [
                'related_tools' => $this->relatedTools($tool),
            ]);
        });
    }

    public static function forgetCategories(): void
    {
        Cache::forget(self::CATEGORIES_KEY);
    }

    public static function forgetToolLists(?string $categorySlug = null): void
    {
        Cache::forget(self::TOOL_LIST_KEY);

        if ($categorySlug) {
            Cache::forget(self::TOOL_CATEGORY_LIST_PREFIX.$categorySlug);
        }
    }

    public static function forgetTool(?string $slug): void
    {
        if ($slug) {
            Cache::forget(self::TOOL_KEY_PREFIX.$slug);
        }
    }

    public static function invalidateForTool(AiTool $tool): void
    {
        self::forgetTool($tool->slug);
        self::forgetTool($tool->getOriginal('slug'));
        self::forgetToolLists();

        foreach (self::categorySlugsForTool($tool) as $categorySlug) {
            self::forgetToolLists($categorySlug);
        }
    }

    public static function invalidateForCategory(Category $category): void
    {
        self::forgetCategories();
        self::forgetToolLists();
        self::forgetToolLists($category->slug);
        self::forgetToolLists($category->getOriginal('slug'));

        $category->tools()
            ->select(['slug'])
            ->get()
            ->each(fn (AiTool $tool) => self::forgetTool($tool->slug));
    }

    public static function invalidateAll(): void
    {
        self::forgetCategories();
        self::forgetToolLists();

        Category::query()->aiTools()
            ->pluck('slug')
            ->filter()
            ->each(fn (string $slug) => self::forgetToolLists($slug));

        AiTool::query()
            ->pluck('slug')
            ->filter()
            ->each(fn (string $slug) => self::forgetTool($slug));
    }

    private static function categorySlugsForTool(AiTool $tool): array
    {
        $categoryIds = collect([
            $tool->category_id,
            $tool->getOriginal('category_id'),
        ])
            ->filter()
            ->unique()
            ->values();

        if ($categoryIds->isEmpty()) {
            return [];
        }

        return Category::query()->aiTools()
            ->whereIn('id', $categoryIds)
            ->pluck('slug')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function cachedCollection(string $key, ?int $ttlSeconds, callable $callback): Collection
    {
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return collect($cached);
        }

        if ($cached instanceof Collection) {
            return $cached;
        }

        if ($cached !== null) {
            Cache::forget($key);
        }

        $items = $callback()->values()->all();

        if ($ttlSeconds === null) {
            Cache::forever($key, $items);
        } else {
            Cache::put($key, $items, $ttlSeconds);
        }

        return collect($items);
    }

    private function relatedTools(AiTool $tool): array
    {
        if (! $tool->show_related_tools) {
            return [];
        }

        return AiTool::query()
            ->select($this->toolListColumns())
            ->active()
            ->where('id', '!=', $tool->id)
            ->where('category_id', $tool->category_id)
            ->whereHas('category', fn ($query) => $query->active()->ofType('ai_tool'))
            ->with(['category:id,name,slug,description,icon,color,requires_pro,requires_login,sort_order,tools_count'])
            ->orderByDesc('usage_count')
            ->orderBy('name')
            ->limit(6)
            ->get()
            ->map(fn (AiTool $relatedTool) => $this->serializeTool($relatedTool))
            ->values()
            ->all();
    }

    private function serializeTool(AiTool $tool, bool $includeContent = false): array
    {
        $data = [
            'id' => $tool->id,
            'name' => $tool->name,
            'slug' => $tool->slug,
            'description' => $tool->description,
            'category' => $this->serializeCategory($tool->category),
            'icon' => $tool->icon,
            'color' => $tool->color,
            'fields' => $tool->fields ?? [],
            'output_type' => $tool->output_type ?? 'markdown',
            'access_level' => $tool->access_level,
            'is_featured' => (bool) $tool->is_featured,
            'requires_pro' => (bool) $tool->requires_pro,
            'requires_login' => (bool) ($tool->category?->requires_login ?? false),
            'supports_brand_voice' => (bool) $tool->supports_brand_voice,
            'max_variants' => (int) ($tool->max_variants ?? 1),
            'show_regenerate' => (bool) ($tool->show_regenerate ?? true),
            'show_improve' => (bool) ($tool->show_improve ?? true),
            'show_editor' => (bool) ($tool->show_editor ?? true),
            'sort_order' => $tool->sort_order,
            'usage_count' => (int) $tool->usage_count,
            'avg_output_tokens' => (int) $tool->avg_output_tokens,
            'avg_rating' => (float) $tool->avg_rating,
            'review_count' => (int) $tool->review_count,
            'meta_title' => $tool->meta_title,
            'meta_description' => $tool->meta_description,
            'og_image' => $tool->og_image,
            'show_reviews' => (bool) $tool->show_reviews,
            'show_related_tools' => (bool) $tool->show_related_tools,
            'show_header' => (bool) ($tool->show_header ?? true),
            'show_footer' => (bool) ($tool->show_footer ?? true),
        ];

        if ($includeContent) {
            $data += [
                'about_content' => $tool->about_content,
                'how_it_works' => $tool->how_it_works ?? [],
                'usage_examples' => $tool->usage_examples ?? [],
                'faq_items' => $tool->faq_items ?? [],
                'show_about' => (bool) $tool->show_about,
                'show_how_it_works' => (bool) $tool->show_how_it_works,
                'show_usage_examples' => (bool) $tool->show_usage_examples,
                'show_faqs' => (bool) $tool->show_faqs,
            ];
        }

        return $data;
    }

    private function serializeCategory(?Category $category): ?array
    {
        if (! $category) {
            return null;
        }

        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'icon' => $category->icon,
            'color' => $category->color,
            'requires_pro' => (bool) $category->requires_pro,
            'requires_login' => (bool) $category->requires_login,
            'sort_order' => $category->sort_order,
            'tools_count' => (int) $category->tools_count,
            'active_tools_count' => (int) ($category->active_tools_count ?? $category->tools_count),
        ];
    }

    private function toolListColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'description',
            'category_id',
            'icon',
            'color',
            'fields',
            'output_type',
            'access_level',
            'is_featured',
            'requires_pro',
            'supports_brand_voice',
            'max_variants',
            'show_regenerate',
            'show_improve',
            'show_editor',
            'sort_order',
            'usage_count',
            'avg_output_tokens',
            'avg_rating',
            'review_count',
            'meta_title',
            'meta_description',
            'og_image',
            'show_reviews',
            'show_related_tools',
            'show_header',
            'show_footer',
        ];
    }

    private function toolDetailColumns(): array
    {
        return array_merge($this->toolListColumns(), [
            'about_content',
            'how_it_works',
            'usage_examples',
            'faq_items',
            'show_about',
            'show_how_it_works',
            'show_usage_examples',
            'show_faqs',
        ]);
    }
}

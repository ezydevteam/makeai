<?php

namespace App\Models;

use App\Services\AI\ToolCatalogCacheService;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * AiTool — AI tool definition (single AI task generator).
 *
 * Ref: AI_SaaS_Master_Prompt Part 14
 */
class AiTool extends Model
{
    use SoftDeletes;

    protected $table = 'ai_tools';

    protected $fillable = [
        'ulid', 'name', 'slug', 'type', 'description',
        'prompt_system', 'prompt_user',
        'category_id',
        'icon', 'color',
        'fields', 'tags',

        // Generation config
        'output_type', 'model_override', 'max_tokens_override',
        'temperature', 'max_variants',

        // Generation source: llm | integration | integration_llm_fallback.
        // integration_slug names the backing integration (config/external-tools.php).
        'generation_mode', 'integration_slug',

        // Access control — access_level is the single source of truth; the pro/plan
        // requirement is derived from it via isProRequired(), not a stored flag.
        'access_level',
        'is_active', 'is_featured', 'is_system', 'is_embeddable', 'supports_brand_voice', 'show_header', 'show_footer',

        // Stats
        'sort_order', 'usage_count', 'views_count', 'avg_output_tokens',
        'avg_rating', 'review_count',

        // SEO
        'meta_title', 'meta_description', 'og_image',

        // Page content
        'about_content', 'how_it_works', 'usage_examples', 'faq_items',
        'show_about', 'show_how_it_works', 'show_usage_examples',
        'show_faqs', 'show_reviews', 'show_related_tools',
        'show_improve',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'tags' => 'array',
            'how_it_works' => 'array',
            'usage_examples' => 'array',
            'faq_items' => 'array',
            'temperature' => 'decimal:2',
            'avg_rating' => 'decimal:2',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_system' => 'boolean',
            'is_embeddable' => 'boolean',
            'show_header' => 'boolean',
            'show_footer' => 'boolean',
            'supports_brand_voice' => 'boolean',
            'show_about' => 'boolean',
            'show_how_it_works' => 'boolean',
            'show_usage_examples' => 'boolean',
            'show_faqs' => 'boolean',
            'show_reviews' => 'boolean',
            'show_related_tools' => 'boolean',
            'show_improve' => 'boolean',
            'views_count' => 'integer',
            'max_variants' => 'integer',
        ];
    }

    /**
     * Whether this tool should try an external integration before (or instead of)
     * the LLM.
     */
    public function usesIntegration(): bool
    {
        return in_array($this->generation_mode, ['integration', 'integration_llm_fallback'], true)
            && filled($this->integration_slug);
    }

    /**
     * Whether the tool may fall back to the LLM (pure LLM, or integration+fallback).
     * `integration` mode is strict: no fallback.
     */
    public function allowsLlmFallback(): bool
    {
        return $this->generation_mode !== 'integration';
    }

    /**
     * The model used for the LLM (fallback) path: the tool's own override when the
     * admin picked one on the edit page, otherwise the global default.
     */
    public function fallbackModel(): string
    {
        return $this->model_override ?: settings('default_ai_model', config('ai.fallback_model'));
    }

    /**
     * The most output tokens a generation from this tool can actually produce: the admin's
     * per-tool override when set, otherwise the global default, then clamped to the model's
     * own ceiling (admin-editable per model; 0 means uncapped).
     *
     * Lives here because two separate things need the same number and had each worked it out
     * for themselves. PromptBuilder used it as the real completion limit, while the credit
     * pre-flight and the price quoted to the user estimated from avg_output_tokens alone and
     * multiplied it by up to 4x for "very long" output — so a tool capped at 300 tokens still
     * quoted, and reserved credits for, four times what it was able to return.
     */
    public function effectiveMaxTokens(?string $model = null): int
    {
        $ceiling = $this->max_tokens_override ?? (int) settings('default_max_tokens', 2000);

        $dbModel = $model ? AiModel::resolveForPricing($model) : null;

        if ($dbModel && (int) $dbModel->max_tokens > 0) {
            return min($ceiling, (int) $dbModel->max_tokens);
        }

        return $ceiling;
    }

    public function getFieldsAttribute($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return is_array($value) ? $value : [];
    }

    public function getSupportsBrandVoiceAttribute($value): bool
    {
        if (request()->is('admin/*') || request()->is('api/v1/admin/*')) {
            return (bool) $value;
        }
        return settings('global_tools_brand_voice_enabled', true) && (bool) $value;
    }

    public function getMaxVariantsAttribute($value): int
    {
        $val = (int) ($value ?? 1);
        if (request()->is('admin/*') || request()->is('api/v1/admin/*')) {
            return $val;
        }
        return settings('global_tools_variations_enabled', true) ? $val : 1;
    }

    public function getShowRegenerateAttribute($value): bool
    {
        if (request()->is('admin/*') || request()->is('api/v1/admin/*')) {
            return (bool) ($value ?? true);
        }
        return settings('global_tools_regenerate_enabled', true) && (bool) ($value ?? true);
    }

    public function getShowImproveAttribute($value): bool
    {
        if (request()->is('admin/*') || request()->is('api/v1/admin/*')) {
            return (bool) ($value ?? true);
        }
        return settings('global_tools_improve_enabled', true) && (bool) ($value ?? true);
    }

    public function getShowEditorAttribute($value): bool
    {
        if (request()->is('admin/*') || request()->is('api/v1/admin/*')) {
            return (bool) ($value ?? true);
        }
        return settings('global_tools_editor_enabled', true) && (bool) ($value ?? true);
    }

    public function getShowAboutAttribute($value): bool
    {
        if (request()->is('admin/*') || request()->is('api/v1/admin/*')) {
            return (bool) ($value ?? true);
        }
        return settings('global_tools_show_about_enabled', true) && (bool) ($value ?? true);
    }

    public function getShowHowItWorksAttribute($value): bool
    {
        if (request()->is('admin/*') || request()->is('api/v1/admin/*')) {
            return (bool) ($value ?? true);
        }
        return settings('global_tools_show_how_it_works_enabled', true) && (bool) ($value ?? true);
    }

    public function getShowUsageExamplesAttribute($value): bool
    {
        if (request()->is('admin/*') || request()->is('api/v1/admin/*')) {
            return (bool) ($value ?? true);
        }
        return settings('global_tools_show_usage_examples_enabled', true) && (bool) ($value ?? true);
    }

    public function getShowFaqsAttribute($value): bool
    {
        if (request()->is('admin/*') || request()->is('api/v1/admin/*')) {
            return (bool) ($value ?? true);
        }
        return settings('global_tools_show_faqs_enabled', true) && (bool) ($value ?? true);
    }

    public function getShowReviewsAttribute($value): bool
    {
        if (request()->is('admin/*') || request()->is('api/v1/admin/*')) {
            return (bool) ($value ?? false);
        }
        return settings('global_tools_show_reviews_enabled', true) && (bool) ($value ?? false);
    }

    public function getIsEmbeddableAttribute($value): bool
    {
        if (request()->is('admin/*') || request()->is('api/v1/admin/*')) {
            return (bool) ($value ?? false);
        }
        return settings('global_tools_embeddable_enabled', true) && (bool) ($value ?? false);
    }

    public function getHowItWorksAttribute($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return is_array($value) ? $value : [];
    }

    public function getUsageExamplesAttribute($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return is_array($value) ? $value : [];
    }

    public function getFaqItemsAttribute($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return is_array($value) ? $value : [];
    }

    protected static function booted(): void
    {
        static::creating(function (AiTool $tool) {
            if (empty($tool->ulid)) {
                $tool->ulid = (string) Str::ulid();
            }
            if (empty($tool->slug)) {
                $tool->slug = Str::slug($tool->name);
            }
        });

        static::updating(function (AiTool $tool) {
            // Track slug changes for 301 redirects
            if ($tool->isDirty('slug')) {
                $oldSlug = $tool->getOriginal('slug');
                if ($oldSlug) {
                    app(\App\Services\AI\ToolSlugHistoryService::class)
                        ->record($oldSlug, $tool->slug, 'ai_tool');
                }
            }
        });

        static::saved(function (AiTool $tool) {
            if ($tool->wasRecentlyCreated || $tool->wasChanged([
                'name', 'slug', 'description', 'category_id',
                'icon', 'color', 'fields', 'tags', 'output_type', 'access_level',
                'is_active', 'is_featured', 'supports_brand_voice', 'show_header', 'show_footer',
                'sort_order', 'avg_rating', 'review_count',
                'meta_title', 'meta_description', 'og_image',
                'about_content', 'how_it_works', 'usage_examples', 'faq_items',
                'show_about', 'show_how_it_works', 'show_usage_examples',
                'show_faqs', 'show_reviews', 'show_related_tools',
            ])) {
                ToolCatalogCacheService::invalidateForTool($tool);
                app(\App\Services\SitemapService::class)->invalidate();
            }
        });

        static::deleted(function (AiTool $tool) {
            ToolCatalogCacheService::invalidateForTool($tool);
            app(\App\Services\SitemapService::class)->invalidate();
        });

        static::restored(function (AiTool $tool) {
            ToolCatalogCacheService::invalidateForTool($tool);
            app(\App\Services\SitemapService::class)->invalidate();
        });
    }

    // ─── Relationships ──────────────────────────

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function reviews()
    {
        return $this->hasMany(ToolReview::class, 'tool_slug', 'slug');
    }

    public function approvedReviews()
    {
        return $this->reviews()->where('is_approved', true);
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function favorites()
    {
        return $this->morphMany(Favorite::class, 'favoriteable');
    }

    // ─── Scopes ─────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    // ─── Access Control ─────────────────────────

    /**
     * The level this tool or its category chain defines, or 'inherit' when none of them do.
     *
     * The cacheable half of getEffectiveAccessLevel() — see Category::resolvedAccessLevel().
     */
    public function resolvedAccessLevel(): string
    {
        $level = $this->access_level ?? 'inherit';

        if ($level === 'inherit' && $this->category) {
            return $this->category->resolvedAccessLevel();
        }

        return $level;
    }

    public function getEffectiveAccessLevel(): string
    {
        $level = $this->resolvedAccessLevel();

        return $level === 'inherit'
            ? settings('default_tool_access_level', 'login')
            : $level;
    }

    public function isProRequired(): bool
    {
        $level = $this->getEffectiveAccessLevel();
        $service = app(\App\Services\AccessLevelService::class);

        return $service->requiresSubscription($level);
    }

    public function isSystem(): bool
    {
        return $this->is_system;
    }

    // ─── Prompt Building ────────────────────────

    public function buildPrompt(array $inputs): string
    {
        $prompt = $this->prompt_system ?? '';

        foreach ($inputs as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $prompt = str_replace('{{'.$key.'}}', (string) $value, $prompt);
            $prompt = str_replace('{'.$key.'}', (string) $value, $prompt);
        }

        return $prompt;
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');

        // Invalidate both individual tool cache and list caches (since usage_count is displayed in lists)
        ToolCatalogCacheService::invalidateForTool($this);
    }

    public function updateReviewStats(): void
    {
        $approved = $this->approvedReviews();
        $avgRating = round($approved->avg('rating') ?? 0, 2);
        $reviewCount = $approved->count();

        // Use direct DB update to avoid triggering model events and cache invalidation loops
        static::where('id', $this->id)->update([
            'avg_rating' => $avgRating,
            'review_count' => $reviewCount,
        ]);

        // Manually invalidate cache since we bypassed model events
        ToolCatalogCacheService::invalidateForTool($this);
    }

    // ─── Related Tools ──────────────────────────

    public function relatedTools(int $limit = 3)
    {
        if (! $this->category_id) {
            return AiTool::active()
                ->where('id', '!=', $this->id)
                ->inRandomOrder()
                ->limit($limit)
                ->get();
        }

        return AiTool::active()
            ->where('category_id', $this->category_id)
            ->where('id', '!=', $this->id)
            ->orderBy('usage_count', 'desc')
            ->limit($limit)
            ->get();
    }
}

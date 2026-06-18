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

        // Access control
        'access_level', 'requires_pro',
        'is_active', 'is_featured', 'is_system', 'is_embeddable', 'supports_brand_voice', 'show_header', 'show_footer',

        // Stats
        'sort_order', 'usage_count', 'views_count', 'avg_output_tokens', 'avg_latency_ms',
        'avg_rating', 'review_count',

        // SEO
        'meta_title', 'meta_description', 'og_image',

        // Page content
        'about_content', 'how_it_works', 'usage_examples', 'faq_items',
        'show_about', 'show_how_it_works', 'show_usage_examples',
        'show_faqs', 'show_reviews', 'show_related_tools',
        'show_regenerate', 'show_improve', 'show_editor',
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
            'requires_pro' => 'boolean',
            'supports_brand_voice' => 'boolean',
            'show_about' => 'boolean',
            'show_how_it_works' => 'boolean',
            'show_usage_examples' => 'boolean',
            'show_faqs' => 'boolean',
            'show_reviews' => 'boolean',
            'show_related_tools' => 'boolean',
            'show_regenerate' => 'boolean',
            'show_improve' => 'boolean',
            'show_editor' => 'boolean',
            'views_count' => 'integer',
            'max_variants' => 'integer',
        ];
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

    public function getEffectiveAccessLevel(): string
    {
        $level = $this->access_level ?? 'inherit';

        if ($level === 'inherit') {
            return settings('default_tool_access_level', 'login_required');
        }

        return $level;
    }

    public function isProRequired(): bool
    {
        return $this->access_level === 'pro_plan'
            || ($this->category?->requires_pro ?? false);
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

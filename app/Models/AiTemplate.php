<?php

namespace App\Models;

use App\Services\AI\ToolCatalogCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * AiTemplate — AI tool template definition.
 *
 * Aligned with AI_SaaS_Master_Prompt Parts P13–P15.
 */
class AiTemplate extends Model
{
    protected $fillable = [
        // Core
        'name', 'slug', 'description',
        'prompt', 'prompt_system', 'prompt_user',
        'category', 'category_id',
        'icon', 'color',
        'fields',

        // Generation config
        'output_type', 'model_override', 'max_tokens_override',
        'default_model', 'max_tokens', 'temperature',

        // Access control
        'access_level', 'is_premium', 'requires_pro',
        'is_active', 'is_featured', 'supports_brand_voice',

        // Stats
        'sort_order', 'usage_count', 'avg_output_tokens',
        'avg_rating', 'review_count',

        // SEO
        'meta_title', 'meta_description', 'og_image',

        // Page content
        'about_content', 'how_it_works', 'usage_examples', 'faq_items',
        'show_about', 'show_how_it_works', 'show_usage_examples',
        'show_faqs', 'show_reviews', 'show_related_tools',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'how_it_works' => 'array',
            'usage_examples' => 'array',
            'faq_items' => 'array',
            'temperature' => 'decimal:2',
            'avg_rating' => 'decimal:2',
            'is_premium' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'requires_pro' => 'boolean',
            'supports_brand_voice' => 'boolean',
            'show_about' => 'boolean',
            'show_how_it_works' => 'boolean',
            'show_usage_examples' => 'boolean',
            'show_faqs' => 'boolean',
            'show_reviews' => 'boolean',
            'show_related_tools' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AiTemplate $t) {
            if (empty($t->slug)) {
                $t->slug = Str::slug($t->name);
            }
        });

        static::saved(function (AiTemplate $tool) {
            if ($tool->wasRecentlyCreated || $tool->wasChanged([
                'name',
                'slug',
                'description',
                'category',
                'category_id',
                'icon',
                'color',
                'fields',
                'output_type',
                'access_level',
                'is_premium',
                'is_active',
                'is_featured',
                'requires_pro',
                'supports_brand_voice',
                'sort_order',
                'avg_rating',
                'review_count',
                'meta_title',
                'meta_description',
                'og_image',
                'about_content',
                'how_it_works',
                'usage_examples',
                'faq_items',
                'show_about',
                'show_how_it_works',
                'show_usage_examples',
                'show_faqs',
                'show_reviews',
                'show_related_tools',
            ])) {
                ToolCatalogCacheService::invalidateForTool($tool);
            }
        });

        static::deleted(function (AiTemplate $tool) {
            ToolCatalogCacheService::invalidateForTool($tool);
        });
    }

    // ─── Relationships ──────────────────────────

    /**
     * Tool category relationship.
     */
    public function toolCategory()
    {
        return $this->belongsTo(AiToolCategory::class, 'category_id');
    }

    /**
     * Reviews for this tool.
     */
    public function reviews()
    {
        return $this->hasMany(ToolReview::class, 'template_slug', 'slug');
    }

    /**
     * Approved reviews only.
     */
    public function approvedReviews()
    {
        return $this->reviews()->where('is_approved', true);
    }

    /**
     * Comments (polymorphic — legacy).
     */
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Favorites (polymorphic).
     */
    public function favorites()
    {
        return $this->morphMany(Favorite::class, 'favoriteable');
    }

    // ─── Prompt Building ────────────────────────

    /**
     * Build the final prompt by replacing placeholders with user inputs.
     * Legacy method — prefer PromptBuilder service for new code.
     */
    public function buildPrompt(array $inputs): string
    {
        $prompt = $this->prompt_system ?? $this->prompt ?? '';

        foreach ($inputs as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $prompt = str_replace('{{'.$key.'}}', (string) $value, $prompt);
            $prompt = str_replace('{'.$key.'}', (string) $value, $prompt);
        }

        return $prompt;
    }

    /**
     * Increment usage counter.
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Update cached review stats after review approval/deletion.
     */
    public function updateReviewStats(): void
    {
        $approved = $this->approvedReviews();
        $this->update([
            'avg_rating' => round($approved->avg('rating'), 2),
            'review_count' => $approved->count(),
        ]);
    }

    // ─── Access Control ─────────────────────────

    /**
     * Get effective access level (resolves 'inherit').
     */
    public function getEffectiveAccessLevel(): string
    {
        $level = $this->access_level ?? 'inherit';

        if ($level === 'inherit') {
            return settings('default_tool_access_level', 'login_required');
        }

        return $level;
    }

    /**
     * Check if this tool requires a pro subscription.
     */
    public function isProRequired(): bool
    {
        return $this->requires_pro
            || $this->access_level === 'pro_plan'
            || ($this->toolCategory?->requires_pro ?? false);
    }

    // ─── Scopes ─────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopePublicAccess($query)
    {
        return $query->where(function ($q) {
            $q->where('access_level', 'public')
                ->orWhere('access_level', 'inherit');
        });
    }

    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Get related tools (same category, excluding self).
     */
    public function relatedTools(int $limit = 3)
    {
        if (! $this->category_id) {
            return AiTemplate::active()
                ->where('id', '!=', $this->id)
                ->inRandomOrder()
                ->limit($limit)
                ->get();
        }

        return AiTemplate::active()
            ->where('category_id', $this->category_id)
            ->where('id', '!=', $this->id)
            ->orderBy('usage_count', 'desc')
            ->limit($limit)
            ->get();
    }
}

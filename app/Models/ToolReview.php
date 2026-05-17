<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ToolReview — user reviews for AI tool templates.
 *
 * Ref: AI_SaaS_Master_Prompt Part 15.14.5
 */
class ToolReview extends Model
{
    protected $fillable = [
        'template_slug', 'user_id',
        'rating', 'comment', 'admin_reply',
        'is_approved', 'is_featured', 'helpful_count',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_approved' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    // ─── Relationships ──────────────────────────

    public function template()
    {
        return $this->belongsTo(AiTemplate::class, 'template_slug', 'slug');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function votes()
    {
        return $this->hasMany(ToolReviewVote::class, 'review_id');
    }

    // ─── Scopes ─────────────────────────────────

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    // ─── Methods ────────────────────────────────

    /**
     * Auto-approve logic: returns true if auto-approval is enabled AND the reviewer
     * has an established history (>5 past approved reviews).
     */
    public function shouldAutoApprove(): bool
    {
        if (! settings('tool_reviews_auto_approve', false)) {
            return false;
        }

        $userApprovedCount = ToolReview::where('user_id', $this->user_id)
            ->where('is_approved', true)
            ->count();

        return $userApprovedCount >= (int) settings('tool_reviews_auto_approve_threshold', 5);
    }

    /**
     * Update the parent template's cached review stats after save/delete.
     */
    protected static function booted(): void
    {
        static::saved(function (ToolReview $review) {
            $review->template?->updateReviewStats();
        });

        static::deleted(function (ToolReview $review) {
            $review->template?->updateReviewStats();
        });
    }
}

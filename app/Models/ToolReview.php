<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ToolReview — user reviews for AI tools.
 *
 * Ref: AI_SaaS_Master_Prompt Part 15.14.5
 */
class ToolReview extends Model
{
    protected $fillable = [
        'tool_slug', 'user_id',
        'rating', 'comment', 'admin_reply',
        'is_approved', 'helpful_count',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_approved' => 'boolean',
        ];
    }

    public function tool()
    {
        return $this->belongsTo(AiTool::class, 'tool_slug', 'slug');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function votes()
    {
        return $this->hasMany(ToolReviewVote::class, 'review_id');
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

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

    protected static function booted(): void
    {
        static::saved(function (ToolReview $review) {
            $review->tool?->updateReviewStats();
        });

        static::deleted(function (ToolReview $review) {
            $review->tool?->updateReviewStats();
        });
    }
}

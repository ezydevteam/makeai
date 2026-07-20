<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ToolReviewVote — helpful/not-helpful votes on reviews.
 */
class ToolReviewVote extends Model
{
    protected $fillable = [
        'review_id', 'user_id', 'is_helpful',
    ];

    protected function casts(): array
    {
        return [
            'is_helpful' => 'boolean',
        ];
    }

    public function review()
    {
        return $this->belongsTo(ToolReview::class, 'review_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Update the parent review's helpful_count when a vote is saved/deleted.
     */
    protected static function booted(): void
    {
        static::saved(function (ToolReviewVote $vote) {
            $review = $vote->review;
            if ($review) {
                $review->update([
                    'helpful_count' => $review->votes()->where('is_helpful', true)->count(),
                ]);
            }
        });

        static::deleted(function (ToolReviewVote $vote) {
            $review = $vote->review;
            if ($review) {
                $review->update([
                    'helpful_count' => $review->votes()->where('is_helpful', true)->count(),
                ]);
            }
        });
    }
}

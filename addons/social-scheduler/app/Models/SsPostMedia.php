<?php

namespace Addons\SocialScheduler\Models;

use Illuminate\Database\Eloquent\Model;

class SsPostMedia extends Model
{
    protected $fillable = [
        'ss_scheduled_post_id', 'carousel_slide_id', 'type', 'path',
        'url', 'mime_type', 'file_size_bytes', 'width', 'height',
        'duration_seconds', 'alt_text', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'file_size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    public function scheduledPost()
    {
        return $this->belongsTo(SsScheduledPost::class, 'ss_scheduled_post_id');
    }

    public function carouselSlide()
    {
        return $this->belongsTo(SsCarouselSlide::class, 'carousel_slide_id');
    }
}

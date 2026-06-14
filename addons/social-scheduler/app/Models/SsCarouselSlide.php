<?php

namespace Addons\SocialScheduler\Models;

use Illuminate\Database\Eloquent\Model;

class SsCarouselSlide extends Model
{
    protected $fillable = ['ss_scheduled_post_id', 'slide_index', 'caption'];

    protected function casts(): array
    {
        return [
            'slide_index' => 'integer',
        ];
    }

    public function scheduledPost()
    {
        return $this->belongsTo(SsScheduledPost::class, 'ss_scheduled_post_id');
    }

    public function media()
    {
        return $this->hasMany(SsPostMedia::class, 'carousel_slide_id');
    }
}

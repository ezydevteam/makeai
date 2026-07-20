<?php

namespace Addons\AiRepurposer\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RpOutput extends Model
{
    protected $table = 'rp_outputs';

    public const FORMAT_LABELS = [
        'blog_post'          => 'Blog Post',
        'twitter_thread'     => 'X / Twitter Thread',
        'linkedin_article'   => 'LinkedIn Article',
        'email_newsletter'   => 'Email Newsletter',
        'tiktok_script'      => 'TikTok / Reels Script',
        'podcast_show_notes' => 'Podcast Show Notes',
        'key_quotes'         => 'Key Quotes',
        'chapter_markers'    => 'Chapter Markers',
    ];

    public const FORMAT_ICONS = [
        'blog_post'          => 'ti-file-text',
        'twitter_thread'     => 'ti-brand-twitter',
        'linkedin_article'   => 'ti-brand-linkedin',
        'email_newsletter'   => 'ti-mail',
        'tiktok_script'      => 'ti-brand-tiktok',
        'podcast_show_notes' => 'ti-microphone',
        'key_quotes'         => 'ti-quote',
        'chapter_markers'    => 'ti-list-numbers',
    ];

    protected $fillable = [
        'ulid',
        'rp_job_id',
        'user_id',
        'format',
        'content',
        'word_count',
        'metadata',
        'is_saved',
        'saved_post_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_saved' => 'boolean',
    ];

    protected $appends = ['format_label', 'format_icon'];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (! $model->ulid) {
                $model->ulid = Str::ulid()->toString();
            }
        });
    }

    public function job()
    {
        return $this->belongsTo(RpJob::class, 'rp_job_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getFormatLabelAttribute(): string
    {
        return self::FORMAT_LABELS[$this->format] ?? $this->format;
    }

    public function getFormatIconAttribute(): string
    {
        return self::FORMAT_ICONS[$this->format] ?? 'ti-file';
    }
}

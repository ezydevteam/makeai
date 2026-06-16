<?php

namespace Addons\PublicKnowledgeBase\Models;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class KbArticle extends Model
{
    protected $fillable = [
        'ulid',
        'kb_category_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'body_plain',
        'status',
        'sort_order',
        'embed_status',
        'embed_error',
        'embedded_at',
        'meta_title',
        'meta_desc',
        'created_by',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'embedded_at' => 'datetime',
        'helpful_count' => 'integer',
        'not_helpful_count' => 'integer',
        'sort_order' => 'integer',
    ];

    protected $appends = ['helpful_percent'];

    protected static function booted(): void
    {
        static::creating(function (KbArticle $article) {
            if (empty($article->ulid)) {
                $article->ulid = (string) Str::ulid();
            }
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->title);
            }
            $article->body_plain = strip_tags($article->body);
        });

        static::saving(function (KbArticle $article) {
            if ($article->isDirty('body')) {
                $article->body_plain = strip_tags($article->body);
                if (! $article->isDirty('embed_status')) {
                    $article->embed_status = 'pending';
                    $article->embed_error = null;
                    $article->embedded_at = null;
                }
            }
            if ($article->isDirty('status') && $article->status === 'published' && ! $article->published_at) {
                $article->published_at = now();
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(KbCategory::class, 'kb_category_id');
    }

    public function embeddings()
    {
        return $this->hasMany(KbEmbedding::class);
    }

    public function votes()
    {
        return $this->hasMany(KbArticleVote::class);
    }

    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where('published_at', '<=', now());
    }

    public function scopeInCategory($query, $categoryId)
    {
        return $query->where('kb_category_id', $categoryId);
    }

    public function scopePendingEmbed($query)
    {
        return $query->whereIn('embed_status', ['pending', 'failed']);
    }

    public function getHelpfulPercentAttribute()
    {
        $total = $this->helpful_count + $this->not_helpful_count;
        if ($total === 0) {
            return null;
        }
        return (int) round(($this->helpful_count / $total) * 100);
    }

    public function getRouteKeyName()
    {
        return 'ulid';
    }
}

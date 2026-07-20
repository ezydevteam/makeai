<?php

namespace Addons\AiRepurposer\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RpJob extends Model
{
    protected $table = 'rp_jobs';

    protected $fillable = [
        'ulid',
        'user_id',
        'source_type',
        'source_url',
        'source_path',
        'source_title',
        'transcript',
        'transcript_source',
        'word_count',
        'duration_seconds',
        'chapters',
        'status',
        'formats_requested',
        'formats_completed',
        'credits_deducted',
        'error_message',
        'is_bulk',
        'bulk_batch_id',
    ];

    protected $casts = [
        'chapters' => 'array',
        'formats_requested' => 'array',
        'formats_completed' => 'array',
        'is_bulk' => 'boolean',
    ];

    protected $appends = ['progress_percent', 'source_label', 'is_youtube'];

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (! $model->ulid) {
                $model->ulid = Str::ulid()->toString();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function outputs()
    {
        return $this->hasMany(RpOutput::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['completed', 'partial']);
    }

    public function scopeBulkBatch($query, string $batchId)
    {
        return $query->where('bulk_batch_id', $batchId);
    }

    public function getProgressPercentAttribute(): int
    {
        $completed = count($this->formats_completed ?? []);
        $requested = count($this->formats_requested ?? []);

        return $requested > 0 ? (int) round(($completed / $requested) * 100) : 0;
    }

    public function getSourceLabelAttribute(): string
    {
        if ($this->source_title) {
            return Str::limit($this->source_title, 80);
        }

        if ($this->source_url && $this->source_type === 'youtube_url') {
            return __('YouTube: :url', ['url' => Str::limit($this->source_url, 60)]);
        }

        return match ($this->source_type) {
            'file_upload' => __('Uploaded file'),
            'text_paste' => __('Pasted text'),
            default => $this->source_type,
        };
    }

    public function getIsYoutubeAttribute(): bool
    {
        return $this->source_type === 'youtube_url';
    }
}

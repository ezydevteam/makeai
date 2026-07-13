<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AipJob extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'ulid', 'user_id', 'guest_ip', 'operation', 'tier', 'status', 'engine', 'model',
        'input_asset_id', 'mask_path', 'reference_path', 'params', 'batch_size',
        'credits_charged', 'billing_mode', 'refunded', 'provider_job_id',
        'error_message', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'batch_size' => 'integer',
            'credits_charged' => 'float',
            'refunded' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $job) {
            $job->ulid ??= (string) Str::ulid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inputAsset(): BelongsTo
    {
        return $this->belongsTo(AipAsset::class, 'input_asset_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(AipAsset::class, 'job_id');
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }

    public function markProcessing(): void
    {
        $this->update(['status' => self::STATUS_PROCESSING, 'started_at' => now()]);
    }

    public function markCompleted(): void
    {
        $this->update(['status' => self::STATUS_COMPLETED, 'completed_at' => now()]);
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => Str::limit($message, 500),
            'completed_at' => now(),
        ]);
    }
}

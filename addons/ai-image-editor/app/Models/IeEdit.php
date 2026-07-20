<?php

declare(strict_types=1);

namespace Addons\AiImageEditor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IeEdit extends Model
{
    protected $fillable = [
        'ulid', 'ie_session_id', 'user_id', 'operation', 'status', 'provider',
        'input_path', 'output_path', 'output_url', 'mask_path', 'params',
        'credits_deducted', 'error_message', 'version_number', 'is_current',
        'completed_at',
    ];

    protected $casts = [
        'params' => 'array',
        'credits_deducted' => 'float',
        'is_current' => 'boolean',
        'completed_at' => 'datetime',
        'version_number' => 'integer',
    ];

    protected $appends = ['operation_label', 'can_revert_to'];

    private static array $operationLabels = [
        'inpaint'          => 'Inpainting',
        'outpaint'         => 'Outpainting',
        'bg_remove'        => 'Background Removal',
        'upscale'          => 'Upscaling',
        'style_transfer'   => 'Style Transfer',
        'object_remove'    => 'Object Removal',
        'color_correction' => 'Color Correction',
        'text_overlay'     => 'Text Overlay',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $edit) {
            if (empty($edit->ulid)) {
                $edit->ulid = (string) Str::ulid();
            }
        });
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(IeSession::class, 'ie_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function getOperationLabelAttribute(): string
    {
        return self::$operationLabels[$this->operation] ?? $this->operation;
    }

    public function getCanRevertToAttribute(): bool
    {
        return $this->status === 'completed' && ! $this->is_current;
    }

    public static function markAsCurrent(IeEdit $edit): void
    {
        DB::transaction(function () use ($edit) {
            self::where('ie_session_id', $edit->ie_session_id)->update(['is_current' => false]);
            $edit->update(['is_current' => true]);
            $edit->session->update([
                'current_path'   => $edit->output_path,
                'current_url'    => $edit->output_url,
                'last_operation' => $edit->operation,
            ]);
        });
    }
}

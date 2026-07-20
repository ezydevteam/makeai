<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportCannedResponse extends Model
{
    protected $fillable = [
        'title',
        'content',
        'department_id',
        'created_by',
        'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'usage_count' => 'integer',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(SupportDepartment::class, 'department_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}

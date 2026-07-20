<?php

namespace Addons\FakerAi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FakerRecord extends Model
{
    protected $table = 'faker_ai_records';

    protected $fillable = [
        'batch_id', 'action', 'subject_type', 'subject_id', 'column', 'amount',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(FakerBatch::class, 'batch_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved Export Center configuration (dataset + format + filters + columns)
 * owned by an admin, for one-click reuse in the export builder.
 */
class ExportPreset extends Model
{
    protected $fillable = [
        'admin_id',
        'name',
        'dataset',
        'format',
        'filters',
        'columns',
    ];

    protected $casts = [
        'filters' => 'array',
        'columns' => 'array',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}

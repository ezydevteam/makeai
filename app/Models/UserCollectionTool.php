<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCollectionTool extends Model
{
    protected $table = 'user_collection_tools';

    public $timestamps = false;

    protected $fillable = ['collection_id', 'tool_slug', 'sort_order', 'added_at'];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(UserCollection::class, 'collection_id');
    }
}

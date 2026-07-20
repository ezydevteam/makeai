<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AipFolder extends Model
{
    protected $fillable = ['user_id', 'name', 'color', 'sort'];

    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(AipAsset::class, 'folder_id');
    }
}

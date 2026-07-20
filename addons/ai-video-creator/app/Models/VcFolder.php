<?php

namespace Addons\AiVideoCreator\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class VcFolder extends Model
{
    protected $fillable = ['user_id', 'name', 'color', 'sort_order'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function projects()
    {
        return $this->hasMany(VcProject::class, 'folder_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}

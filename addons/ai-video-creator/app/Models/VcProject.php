<?php

namespace Addons\AiVideoCreator\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VcProject extends Model
{
    protected $fillable = [
        'ulid', 'user_id', 'name', 'description', 'folder_id',
        'color', 'thumbnail_path', 'render_count', 'total_duration',
    ];

    protected $appends = ['thumbnail_url'];

    protected function casts(): array
    {
        return [
            'render_count' => 'integer',
            'total_duration' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $project) {
            $project->ulid = (string) Str::ulid();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function folder()
    {
        return $this->belongsTo(VcFolder::class, 'folder_id');
    }

    public function renders()
    {
        return $this->hasMany(VcRender::class, 'vc_project_id');
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->thumbnail_path) {
            return Storage::url($this->thumbnail_path);
        }

        return null;
    }
}

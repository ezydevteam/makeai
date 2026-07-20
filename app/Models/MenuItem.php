<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;

class MenuItem extends Model
{
    protected $fillable = [
        'menu_id', 'parent_id', 'label', 'description', 'type', 'url', 'page_id', 'route_name', 'target', 'icon',
        'badge_text', 'badge_color', 'is_active', 'requires_auth', 'mega_menu', 'sort_order',
    ];

    protected $appends = ['final_url'];

    protected $casts = [
        'is_active' => 'boolean',
        'mega_menu' => 'boolean',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function getFinalUrlAttribute(): string
    {
        if ($this->type === 'page' && $this->page) {
            return route('page.show', $this->page->slug);
        }
        if ($this->type === 'route' && $this->route_name && Route::has($this->route_name)) {
            return route($this->route_name);
        }

        return $this->url ?? '#';
    }
}

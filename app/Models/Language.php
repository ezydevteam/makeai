<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Language extends Model
{
    protected $fillable = [
        'code', 'name', 'flag', 'is_rtl',
        'is_default', 'is_active',
    ];

    protected $casts = [
        'is_rtl' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get translations for this language.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class);
    }

    /**
     * Get the default language.
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first();
    }
}

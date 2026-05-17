<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditPack extends Model
{
    protected $fillable = ['name', 'credits', 'price', 'is_popular', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'credits' => 'decimal:2',
            'price' => 'decimal:2',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

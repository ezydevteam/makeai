<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppearanceSetting extends Model
{
    protected $fillable = ['scope', 'key', 'value'];

    public static function getForScope(string $scope): array
    {
        return static::where('scope', $scope)->pluck('value', 'key')->toArray();
    }
}

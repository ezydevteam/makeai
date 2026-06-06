<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatProject extends Model
{
    protected $table = 'chat_projects';

    protected $fillable = [
        'user_id', 'name', 'description', 'color_hex',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class, 'project_id');
    }
}

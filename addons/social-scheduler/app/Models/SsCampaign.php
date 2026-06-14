<?php

namespace Addons\SocialScheduler\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SsCampaign extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'name', 'description', 'status'];

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scheduledPosts()
    {
        return $this->hasMany(SsScheduledPost::class, 'ss_campaign_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsCampaign extends Model
{
    protected $fillable = [
        'message',
        'action_url',
        'action_label',
        'recipient_count',
        'sent_count',
        'failed_count',
        'status',
        'created_by_admin_id',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(SmsCampaignRecipient::class, 'campaign_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }
}

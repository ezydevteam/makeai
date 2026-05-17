<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterCampaign extends Model
{
    protected $fillable = ['subject', 'content', 'sent_at', 'recipient_count', 'opened_count', 'status'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}

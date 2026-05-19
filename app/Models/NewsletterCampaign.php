<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterCampaign extends Model
{
    protected $fillable = [
        'subject',
        'content',
        'audience',
        'sent_at',
        'recipient_count',
        'sent_count',
        'failed_count',
        'opened_count',
        'status',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}

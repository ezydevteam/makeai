<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketAttachment extends Model
{
    public $timestamps = false;

    protected $connection = 'mysql';

    protected $fillable = [
        'ticket_id',
        'reply_id',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'uploaded_by_type',
        'uploaded_by_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function reply(): BelongsTo
    {
        return $this->belongsTo(SupportTicketReply::class, 'reply_id');
    }
}

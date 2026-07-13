<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportTicketReply extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ticket_id',
        'author_type',
        'author_id',
        'content',
        'attachments',
        'is_internal_note',
        'is_ai_draft',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'is_internal_note' => 'boolean',
            'is_ai_draft' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function author(): BelongsTo
    {
        return $this->author_type === 'admin'
            ? $this->belongsTo(Admin::class, 'author_id')
            : $this->belongsTo(User::class, 'author_id');
    }
}

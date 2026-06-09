<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportTicket extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'ticket_number',
        'user_id',
        'department_id',
        'assigned_to',
        'subject',
        'status',
        'priority',
        'source',
        'first_response_at',
        'resolved_at',
        'closed_at',
        'last_reply_at',
        'last_reply_by',
        'satisfaction_rating',
        'satisfaction_comment',
        'user_last_read_at',
        'admin_last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_reply_at' => 'datetime',
            'user_last_read_at' => 'datetime',
            'admin_last_read_at' => 'datetime',
            'satisfaction_rating' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ticket_number';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(SupportDepartment::class, 'department_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupportTicketReply::class, 'ticket_id')->oldest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class, 'ticket_id');
    }
}

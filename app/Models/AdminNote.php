<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminNote extends Model
{
    protected $fillable = [
        'admin_id',
        'subject',
        'description',
        'reminder_date',
        'auto_delete_date',
        'reminder_sent',
    ];

    protected $casts = [
        'reminder_date' => 'datetime',
        'auto_delete_date' => 'datetime',
        'reminder_sent' => 'boolean',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function scopeRemindersDue($query)
    {
        return $query->where('reminder_sent', false)
            ->whereNotNull('reminder_date')
            ->where('reminder_date', '<=', now());
    }

    public function scopeUnexpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('auto_delete_date')->orWhere('auto_delete_date', '>=', now());
        });
    }
}

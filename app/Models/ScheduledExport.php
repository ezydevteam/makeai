<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A recurring Export Center job owned by an admin. On each due run the export is
 * generated into the admin's Recent Exports and an in-app notification fires
 * (no email). The date range is a rolling window matched to the frequency, so a
 * weekly export always covers "the last week", not a frozen range.
 */
class ScheduledExport extends Model
{
    public const FREQUENCIES = ['daily', 'weekly', 'monthly'];

    protected $fillable = [
        'admin_id',
        'name',
        'dataset',
        'format',
        'filters',
        'columns',
        'frequency',
        'is_active',
        'last_run_at',
        'next_run_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'columns' => 'array',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /** The next fire time one interval after $from. */
    public function computeNextRun(Carbon $from): Carbon
    {
        return match ($this->frequency) {
            'daily' => $from->copy()->addDay(),
            'monthly' => $from->copy()->addMonth(),
            default => $from->copy()->addWeek(),
        };
    }

    /**
     * Rolling [from, to] date strings for this run, based on the frequency.
     *
     * @return array{0:string,1:string}
     */
    public function rollingWindow(Carbon $now): array
    {
        $from = match ($this->frequency) {
            'daily' => $now->copy()->subDay(),
            'monthly' => $now->copy()->subMonth(),
            default => $now->copy()->subWeek(),
        };

        return [$from->toDateString(), $now->toDateString()];
    }
}

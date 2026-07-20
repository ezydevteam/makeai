<?php

namespace App\Console\Commands;

use App\Models\SupportTicket;
use Illuminate\Console\Command;

class AutoCloseResolvedTickets extends Command
{
    protected $signature = 'support:auto-close';

    protected $description = 'Close resolved tickets that have passed the auto-close threshold.';

    public function handle(): int
    {
        $days = (int) settings('auto_close_resolved_days', 7);

        if ($days <= 0) {
            $this->info('Auto-close is disabled (auto_close_resolved_days = 0).');

            return self::SUCCESS;
        }

        $threshold = now()->subDays($days);

        $tickets = SupportTicket::query()
            ->where('status', 'resolved')
            ->where('resolved_at', '<=', $threshold)
            ->get();

        if ($tickets->isEmpty()) {
            $this->info('No resolved tickets to auto-close.');

            return self::SUCCESS;
        }

        foreach ($tickets as $ticket) {
            $ticket->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            $this->line("Closed: <info>{$ticket->ticket_number}</info> — {$ticket->subject}");
        }

        $this->info($tickets->count().' ticket(s) auto-closed.');

        return self::SUCCESS;
    }
}

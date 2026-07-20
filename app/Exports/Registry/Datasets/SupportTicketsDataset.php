<?php

namespace App\Exports\Registry\Datasets;

use App\Exports\Registry\Column;
use App\Exports\Registry\Dataset;
use App\Models\SupportTicket;
use Illuminate\Database\Eloquent\Builder;

class SupportTicketsDataset extends Dataset
{
    public function key(): string { return 'support-tickets'; }
    public function label(): string { return translate('Support Tickets'); }
    public function isAvailable(): bool { return (bool) settings('tickets_enabled', true); }
    public function supportedFilters(): array { return ['status', 'date']; }

    public function query(array $filters): Builder
    {
        return SupportTicket::query()
            ->with('user:id,name,email')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest();
    }

    public function columns(): array
    {
        return [
            new Column('ticket_number', translate('Ticket #'), fn ($r) => $r->ticket_number),
            new Column('user', translate('User'), fn ($r) => $r->user?->name ?? translate('N/A')),
            new Column('subject', translate('Subject'), fn ($r) => $r->subject),
            new Column('status', translate('Status'), fn ($r) => ucfirst((string) $r->status)),
            new Column('priority', translate('Priority'), fn ($r) => ucfirst((string) $r->priority)),
            new Column('source', translate('Source'), fn ($r) => $r->source),
            new Column('created', translate('Created'), fn ($r) => $r->created_at?->format('Y-m-d')),
            new Column('resolved_at', translate('Resolved At'), fn ($r) => $r->resolved_at?->format('Y-m-d') ?? '—'),
        ];
    }

    public function stats(array $filters): array
    {
        $query = $this->query($filters);

        return [
            translate('Total') => number_format((clone $query)->count()),
            translate('Open') => number_format((clone $query)->where('status', 'open')->count()),
            translate('Resolved') => number_format((clone $query)->whereNotNull('resolved_at')->count()),
            translate('Avg Rating') => number_format((float) (clone $query)->avg('satisfaction_rating'), 2),
        ];
    }
}

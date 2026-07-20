<?php

namespace App\Exports\Registry\Datasets;

use App\Exports\Registry\Column;
use App\Exports\Registry\Dataset;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;

class RevenueDataset extends Dataset
{
    public function key(): string
    {
        return 'revenue';
    }

    public function label(): string
    {
        return translate('Revenue');
    }

    /** Payment data only exists when billing is available. */
    public function isAvailable(): bool
    {
        return isProAvailable();
    }

    public function supportedFilters(): array
    {
        return ['gateway', 'status', 'date'];
    }

    public function query(array $filters): Builder
    {
        return Payment::query()
            ->with('user:id,name,email', 'plan:id,name')
            ->when($filters['gateway'] ?? null, fn ($q, $gateways) => $q->whereIn('gateway', (array) $gateways))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest();
    }

    public function columns(): array
    {
        return [
            new Column('date', translate('Date'), fn ($p) => $p->created_at?->format('Y-m-d H:i')),
            new Column('transaction_id', translate('Transaction ID'), fn ($p) => $p->ulid),
            new Column('user', translate('User'), fn ($p) => $p->user?->name ?? translate('N/A')),
            new Column('plan', translate('Plan'), fn ($p) => $p->plan?->name ?? translate('N/A')),
            new Column('amount', translate('Amount'), fn ($p) => number_format((float) $p->amount, 2)),
            new Column('currency', translate('Currency'), fn ($p) => $p->currency),
            new Column('gateway', translate('Gateway'), fn ($p) => $p->gateway),
            new Column('status', translate('Status'), fn ($p) => ucfirst((string) $p->status)),
        ];
    }

    public function stats(array $filters): array
    {
        $query = $this->query($filters);
        $revenue = (float) (clone $query)->where('status', 'completed')->sum('amount');
        $count = (clone $query)->count();

        return [
            translate('Total Revenue') => number_format($revenue, 2),
            translate('Transactions') => number_format($count),
            translate('Avg Transaction') => number_format($count > 0 ? $revenue / $count : 0, 2),
            translate('Refunded') => number_format(
                (float) Payment::where('status', 'refunded')
                    ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
                    ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
                    ->sum('amount'),
                2
            ),
        ];
    }
}

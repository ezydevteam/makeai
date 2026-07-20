<?php

namespace App\Exports\Registry\Datasets;

use App\Exports\Registry\Column;
use App\Exports\Registry\Dataset;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;

class RefundsDataset extends Dataset
{
    public function key(): string
    {
        return 'refunds';
    }

    public function label(): string
    {
        return translate('Refunds');
    }

    /** Refunds only exist when billing is available. */
    public function isAvailable(): bool
    {
        return isProAvailable();
    }

    public function supportedFilters(): array
    {
        return ['gateway', 'date'];
    }

    public function query(array $filters): Builder
    {
        return Payment::query()
            ->where('status', 'refunded')
            ->with('user:id,name,email', 'plan:id,name')
            ->when($filters['gateway'] ?? null, fn ($q, $gateways) => $q->whereIn('gateway', (array) $gateways))
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
        ];
    }

    public function stats(array $filters): array
    {
        $query = $this->query($filters);

        return [
            translate('Total Refunds') => number_format((clone $query)->count()),
            translate('Refunded Amount') => number_format((float) (clone $query)->sum('amount'), 2),
            translate('Unique Users') => number_format((clone $query)->distinct('user_id')->count('user_id')),
        ];
    }
}

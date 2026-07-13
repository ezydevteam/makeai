<?php

namespace App\Exports\Registry\Datasets;

use App\Exports\Registry\Column;
use App\Exports\Registry\Dataset;
use App\Models\GatewaySubscription;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionsDataset extends Dataset
{
    public function key(): string
    {
        return 'subscriptions';
    }

    public function label(): string
    {
        return translate('Subscriptions');
    }

    /** Subscriptions only exist when billing is available. */
    public function isAvailable(): bool
    {
        return isProAvailable();
    }

    public function supportedFilters(): array
    {
        return ['status', 'date'];
    }

    public function query(array $filters): Builder
    {
        return GatewaySubscription::query()
            ->with('user:id,name,email', 'plan:id,name')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest();
    }

    public function columns(): array
    {
        return [
            new Column('date', translate('Date'), fn ($s) => $s->created_at?->format('Y-m-d')),
            new Column('user', translate('User'), fn ($s) => $s->user?->name ?? translate('N/A')),
            new Column('plan', translate('Plan'), fn ($s) => $s->plan?->name ?? translate('N/A')),
            new Column('status', translate('Status'), fn ($s) => ucfirst((string) $s->status)),
            new Column('gateway', translate('Gateway'), fn ($s) => $s->gateway),
            new Column('billing_cycle', translate('Billing Cycle'), fn ($s) => $s->billing_cycle),
            new Column('amount', translate('Amount'), fn ($s) => number_format((float) $s->amount, 2)),
            new Column('currency', translate('Currency'), fn ($s) => $s->currency),
            new Column('period_end', translate('Period End'), fn ($s) => $s->current_period_end?->format('Y-m-d') ?? '—'),
            new Column('cancelled_at', translate('Cancelled At'), fn ($s) => $s->cancelled_at?->format('Y-m-d') ?? '—'),
        ];
    }

    public function stats(array $filters): array
    {
        $query = $this->query($filters);

        return [
            translate('Total') => number_format((clone $query)->count()),
            translate('Active') => number_format((clone $query)->where('status', 'active')->count()),
            translate('Cancelled') => number_format((clone $query)->whereNotNull('cancelled_at')->count()),
            translate('Active MRR') => number_format(
                (float) (clone $query)->where('status', 'active')->sum('amount'),
                2
            ),
        ];
    }
}

<?php

namespace App\Exports\Registry\Datasets;

use App\Exports\Registry\Column;
use App\Exports\Registry\Dataset;
use App\Models\CreditTransaction;
use Illuminate\Database\Eloquent\Builder;

class CreditLedgerDataset extends Dataset
{
    public function key(): string { return 'credit-ledger'; }
    public function label(): string { return translate('Credit Ledger'); }
    public function supportedFilters(): array { return ['user_id', 'date']; }

    public function query(array $filters): Builder
    {
        return CreditTransaction::query()
            ->with('user:id,name,email')
            ->when($filters['user_id'] ?? null, fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest();
    }

    public function columns(): array
    {
        return [
            new Column('date', translate('Date'), fn ($r) => $r->created_at?->format('Y-m-d H:i')),
            new Column('user', translate('User'), fn ($r) => $r->user?->name ?? translate('N/A')),
            new Column('type', translate('Type'), fn ($r) => ucfirst((string) $r->type)),
            new Column('amount', translate('Amount'), fn ($r) => number_format((float) $r->amount, 2)),
            new Column('balance_after', translate('Balance After'), fn ($r) => number_format((float) $r->balance_after, 2)),
            new Column('description', translate('Description'), fn ($r) => $r->description),
        ];
    }

    public function stats(array $filters): array
    {
        $query = $this->query($filters);

        return [
            translate('Credited') => number_format((float) (clone $query)->where('amount', '>', 0)->sum('amount'), 2),
            translate('Debited') => number_format(abs((float) (clone $query)->where('amount', '<', 0)->sum('amount')), 2),
            translate('Net') => number_format((float) (clone $query)->sum('amount'), 2),
            translate('Records') => number_format((clone $query)->count()),
        ];
    }
}

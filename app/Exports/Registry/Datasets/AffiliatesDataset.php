<?php

namespace App\Exports\Registry\Datasets;

use App\Exports\Registry\Column;
use App\Exports\Registry\Dataset;
use App\Models\AffiliateCommission;
use App\Services\AffiliateService;
use Illuminate\Database\Eloquent\Builder;

class AffiliatesDataset extends Dataset
{
    public function key(): string
    {
        return 'affiliates';
    }

    public function label(): string
    {
        return translate('Affiliate Commissions');
    }

    /** Extended license + affiliate program enabled. */
    public function isAvailable(): bool
    {
        return app(AffiliateService::class)->isEnabled();
    }

    public function supportedFilters(): array
    {
        return ['status', 'date'];
    }

    public function query(array $filters): Builder
    {
        return AffiliateCommission::query()
            ->with('referrer:id,name,email', 'referred:id,name,email')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest();
    }

    public function columns(): array
    {
        return [
            new Column('date', translate('Date'), fn ($c) => $c->created_at?->format('Y-m-d')),
            new Column('referrer', translate('Referrer'), fn ($c) => trim($c->referrer?->name . ' (' . $c->referrer?->email . ')')),
            new Column('referred', translate('Referred User'), fn ($c) => $c->referred?->name),
            new Column('amount', translate('Amount'), fn ($c) => number_format((float) $c->amount, 2)),
            new Column('status', translate('Status'), fn ($c) => ucfirst((string) $c->status)),
            new Column('approved_at', translate('Approved At'), fn ($c) => $c->approved_at?->format('Y-m-d') ?? '—'),
            new Column('paid_at', translate('Paid At'), fn ($c) => $c->paid_at?->format('Y-m-d') ?? '—'),
        ];
    }

    public function stats(array $filters): array
    {
        $query = $this->query($filters);
        $total = (float) (clone $query)->sum('amount');
        $count = (clone $query)->count();

        return [
            translate('Total Commissions') => number_format($total, 2),
            translate('Records') => number_format($count),
            translate('Unique Referrers') => number_format((clone $query)->distinct('referrer_id')->count('referrer_id')),
            translate('Avg Commission') => number_format($count > 0 ? $total / $count : 0, 2),
        ];
    }
}

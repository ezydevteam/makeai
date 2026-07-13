<?php

namespace App\Exports\Registry\Datasets;

use App\Exports\Registry\Column;
use App\Exports\Registry\Dataset;
use App\Models\AffiliatePayout;
use App\Services\AffiliateService;
use Illuminate\Database\Eloquent\Builder;

class AffiliatePayoutsDataset extends Dataset
{
    public function key(): string
    {
        return 'affiliate-payouts';
    }

    public function label(): string
    {
        return translate('Affiliate Payouts');
    }

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
        return AffiliatePayout::query()
            ->with('user:id,name,email')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest();
    }

    public function columns(): array
    {
        return [
            new Column('date', translate('Date'), fn ($p) => $p->created_at?->format('Y-m-d')),
            new Column('user', translate('User'), fn ($p) => $p->user?->name ?? translate('N/A')),
            new Column('amount', translate('Amount'), fn ($p) => number_format((float) $p->amount, 2)),
            new Column('method', translate('Method'), fn ($p) => $p->method),
            new Column('status', translate('Status'), fn ($p) => ucfirst((string) $p->status)),
            new Column('processed_at', translate('Processed At'), fn ($p) => $p->processed_at?->format('Y-m-d') ?? '—'),
            new Column('admin_note', translate('Admin Note'), fn ($p) => $p->admin_note),
        ];
    }

    public function stats(array $filters): array
    {
        $base = fn () => AffiliatePayout::query()
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to));

        return [
            translate('Total Payouts') => number_format($base()->count()),
            translate('Paid Amount') => number_format((float) $base()->where('status', 'paid')->sum('amount'), 2),
            translate('Pending') => number_format($base()->where('status', 'pending')->count()),
        ];
    }
}

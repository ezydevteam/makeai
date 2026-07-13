<?php

namespace App\Exports\Registry\Datasets;

use App\Exports\Registry\Column;
use App\Exports\Registry\Dataset;
use App\Models\AffiliateReferral;
use App\Services\AffiliateService;
use Illuminate\Database\Eloquent\Builder;

class AffiliateReferralsDataset extends Dataset
{
    public function key(): string
    {
        return 'affiliate-referrals';
    }

    public function label(): string
    {
        return translate('Affiliate Referrals');
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
        return AffiliateReferral::query()
            ->with('referrer:id,name,email', 'referred:id,name,email')
            ->when($filters['status'] ?? null, function ($q, $status) {
                return $status === 'converted'
                    ? $q->whereNotNull('converted_at')
                    : $q->whereNull('converted_at');
            })
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest();
    }

    public function columns(): array
    {
        return [
            new Column('date', translate('Date'), fn ($r) => $r->created_at?->format('Y-m-d')),
            new Column('referrer', translate('Referrer'), fn ($r) => $r->referrer?->name ?? translate('N/A')),
            new Column('referred', translate('Referred User'), fn ($r) => $r->referred?->name ?? translate('N/A')),
            new Column('referral_code', translate('Referral Code'), fn ($r) => $r->referral_code),
            new Column('ip', translate('IP'), fn ($r) => $r->ip_address),
            new Column('landed_at', translate('Landed At'), fn ($r) => $r->landed_at?->format('Y-m-d H:i') ?? '—'),
            new Column('converted_at', translate('Converted At'), fn ($r) => $r->converted_at?->format('Y-m-d') ?? '—'),
        ];
    }

    public function stats(array $filters): array
    {
        // Stats show the full breakdown for the date range, independent of the
        // converted/pending status filter (else Converted vs Pending contradict).
        $base = fn () => AffiliateReferral::query()
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to));

        return [
            translate('Total') => number_format($base()->count()),
            translate('Converted') => number_format($base()->whereNotNull('converted_at')->count()),
            translate('Pending') => number_format($base()->whereNull('converted_at')->count()),
            translate('Unique Referrers') => number_format($base()->distinct('referrer_id')->count('referrer_id')),
        ];
    }
}

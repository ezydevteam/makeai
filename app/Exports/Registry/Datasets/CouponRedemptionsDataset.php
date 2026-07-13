<?php

namespace App\Exports\Registry\Datasets;

use App\Exports\Registry\Column;
use App\Exports\Registry\Dataset;
use App\Models\CouponRedemption;
use Illuminate\Database\Eloquent\Builder;

class CouponRedemptionsDataset extends Dataset
{
    public function key(): string
    {
        return 'coupon-redemptions';
    }

    public function label(): string
    {
        return translate('Coupon Redemptions');
    }

    /** Coupons apply to billing/checkout, which only exists with Pro. */
    public function isAvailable(): bool
    {
        return isProAvailable();
    }

    public function supportedFilters(): array
    {
        return ['date'];
    }

    public function query(array $filters): Builder
    {
        return CouponRedemption::query()
            ->with('user:id,name,email', 'coupon:id,code')
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest();
    }

    public function columns(): array
    {
        return [
            new Column('date', translate('Date'), fn ($r) => $r->created_at?->format('Y-m-d H:i')),
            new Column('coupon', translate('Coupon'), fn ($r) => $r->coupon?->code ?? '—'),
            new Column('user', translate('User'), fn ($r) => $r->user?->name ?? translate('N/A')),
            new Column('payment_id', translate('Payment ID'), fn ($r) => $r->payment_id),
        ];
    }

    public function stats(array $filters): array
    {
        $query = $this->query($filters);

        return [
            translate('Total Redemptions') => number_format((clone $query)->count()),
            translate('Unique Users') => number_format((clone $query)->distinct('user_id')->count('user_id')),
            translate('Unique Coupons') => number_format((clone $query)->distinct('coupon_id')->count('coupon_id')),
        ];
    }
}

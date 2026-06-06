<?php

namespace App\Exports\Admin;

use App\Models\AffiliateCommission;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AffiliateCommissionsExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    public function query(): Builder
    {
        return AffiliateCommission::query()
            ->with('referrer:id,name,email', 'referred:id,name,email')
            ->orderBy('created_at', 'desc');
    }

    public function headings(): array
    {
        return ['Date', 'Referrer', 'Referred User', 'Amount', 'Status', 'Approved At', 'Paid At'];
    }

    public function map($c): array
    {
        return [
            $c->created_at->format('Y-m-d'),
            $c->referrer?->name . ' (' . $c->referrer?->email . ')',
            $c->referred?->name,
            number_format($c->amount, 2),
            ucfirst($c->status),
            $c->approved_at?->format('Y-m-d') ?? '—',
            $c->paid_at?->format('Y-m-d') ?? '—',
        ];
    }
}

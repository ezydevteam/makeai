<?php

namespace App\Exports\Admin;

use App\Models\AffiliateCommission;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Events\AfterSheet;

class AffiliateCommissionsExport implements FromQuery, WithHeadings, WithMapping, WithChunkReading, WithStrictNullComparison, ShouldAutoSize, WithEvents
{
    use Exportable;

    public int $chunkSize = 500;

    public function query(): Builder
    {
        return AffiliateCommission::query()
            ->with('referrer:id,name,email', 'referred:id,name,email')
            ->orderBy('created_at', 'desc');
    }

    public function chunkSize(): int
    {
        return $this->chunkSize;
    }

    public function headings(): array
    {
        return [translate('Date'), translate('Referrer'), translate('Referred User'), translate('Amount'), translate('Status'), translate('Approved At'), translate('Paid At')];
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->getStyle('A1:' . $event->sheet->getHighestColumn() . '1')
                    ->getFont()->setBold(true);
            },
        ];
    }
}

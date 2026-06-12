<?php

namespace App\Exports\Admin;

use App\Models\Payment;
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

class RevenueExport implements FromQuery, WithHeadings, WithMapping, WithChunkReading, WithStrictNullComparison, ShouldAutoSize, WithEvents
{
    use Exportable;

    public int $chunkSize = 500;

    public function __construct(
        private string $dateFrom,
        private string $dateTo,
        private array|string|null $gateway = null,
        private ?string $status = null,
    ) {}

    public function query(): Builder
    {
        return Payment::query()
            ->with('user:id,name,email', 'plan:id,name')
            ->when($this->gateway, fn ($q) => $q->whereIn('gateway', (array) $this->gateway))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->orderBy('created_at', 'desc');
    }

    public function headings(): array
    {
        return [translate('Date'), translate('Transaction ID'), translate('User'), translate('Plan'), translate('Amount'), translate('Currency'), translate('Gateway'), translate('Status')];
    }

    public function map($payment): array
    {
        return [
            $payment->created_at->format('Y-m-d H:i'),
            $payment->ulid,
            $payment->user?->name . ' (' . $payment->user?->email . ')',
            $payment->plan?->name ?? '—',
            number_format($payment->amount, 2),
            strtoupper($payment->currency),
            ucfirst($payment->gateway),
            ucfirst($payment->status),
        ];
    }

    public function chunkSize(): int
    {
        return $this->chunkSize;
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

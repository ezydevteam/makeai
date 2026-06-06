<?php

namespace App\Exports\Admin;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RevenueExport implements FromQuery, WithHeadings, WithMapping, WithChunkReading
{
    use Exportable;

    public int $chunkSize = 500;

    public function __construct(
        private string $dateFrom,
        private string $dateTo,
        private ?string $gateway = null,
        private ?string $status = null,
    ) {}

    public function query(): Builder
    {
        return Payment::query()
            ->with('user:id,name,email', 'plan:id,name')
            ->when($this->gateway, fn ($q) => $q->where('gateway', $this->gateway))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->orderBy('created_at', 'desc');
    }

    public function headings(): array
    {
        return ['Date', 'Transaction ID', 'User', 'Plan', 'Amount', 'Currency', 'Gateway', 'Status'];
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
}

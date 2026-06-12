<?php

namespace App\Exports\Admin;

use App\Models\User;
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

class UsersExport implements FromQuery, WithHeadings, WithMapping, WithChunkReading, WithStrictNullComparison, ShouldAutoSize, WithEvents
{
    use Exportable;

    public int $chunkSize = 500;

    public function __construct(
        private ?string $status = null,
        private ?string $planId = null,
        private ?string $dateFrom = null,
        private ?string $dateTo = null,
    ) {}

    public function query(): Builder
    {
        return User::query()
            ->with('plan')
            ->when($this->status, fn ($q) => $q->where('is_active', $this->status === 'active'))
            ->when($this->planId, fn ($q) => $q->where('plan_id', $this->planId))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->select(['id', 'name', 'email', 'is_active', 'credits', 'plan_id', 'created_at']);
    }

    public function headings(): array
    {
        return [
            translate('Name'),
            translate('Email'),
            translate('Active'),
            translate('Plan'),
            translate('Credits'),
            translate('Joined'),
        ];
    }

    public function map($user): array
    {
        return [
            $user->name,
            $user->email,
            $user->is_active ? translate('Active') : translate('Inactive'),
            $user->plan?->name ?? translate('Free'),
            number_format($user->credits, 4),
            $user->created_at->format('Y-m-d'),
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

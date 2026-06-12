<?php

namespace App\Exports\Admin;

use App\Models\AiUsageLog;
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

class AiUsageExport implements FromQuery, WithHeadings, WithMapping, WithChunkReading, WithStrictNullComparison, ShouldAutoSize, WithEvents
{
    use Exportable;

    public int $chunkSize = 1000;

    public function __construct(
        private ?string $userId = null,
        private array|string|null $toolSlug = null,
        private array|string|null $provider = null,
        private string $dateFrom = '',
        private string $dateTo = '',
    ) {}

    public function query(): Builder
    {
        return AiUsageLog::query()
            ->with('user:id,name,email')
            ->when($this->userId, fn ($q) => $q->where('user_id', $this->userId))
            ->when($this->toolSlug, fn ($q) => $q->whereIn('tool_slug', (array) $this->toolSlug))
            ->when($this->provider, fn ($q) => $q->whereIn('provider', (array) $this->provider))
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->select(['id', 'user_id', 'tool_slug', 'model', 'provider', 'input_tokens', 'output_tokens', 'cost_usd', 'credits_used', 'status', 'response_time_ms', 'created_at'])
            ->orderBy('created_at', 'desc');
    }

    public function headings(): array
    {
        return [translate('Date'), translate('User'), translate('Tool'), translate('Model'), translate('Provider'), translate('Input Tokens'), translate('Output Tokens'), translate('Cost (USD)'), translate('Credits Used'), translate('Status'), translate('Response Time (ms)')];
    }

    public function map($log): array
    {
        return [
            $log->created_at->format('Y-m-d H:i'),
            $log->user ? $log->user->name . ' (' . $log->user->email . ')' : 'Deleted User',
            $log->tool_slug,
            $log->model,
            ucfirst($log->provider),
            $log->input_tokens,
            $log->output_tokens,
            number_format($log->cost_usd, 6),
            $log->credits_used,
            ucfirst($log->status),
            $log->response_time_ms,
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

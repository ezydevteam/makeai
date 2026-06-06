<?php

namespace App\Exports\Admin;

use App\Models\AiUsageLog;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AiUsageExport implements FromQuery, WithHeadings, WithMapping, WithChunkReading
{
    use Exportable;

    public int $chunkSize = 1000;

    public function __construct(
        private ?string $userId = null,
        private ?string $toolSlug = null,
        private ?string $provider = null,
        private string $dateFrom = '',
        private string $dateTo = '',
    ) {}

    public function query(): Builder
    {
        return AiUsageLog::query()
            ->with('user:id,name,email')
            ->when($this->userId, fn ($q) => $q->where('user_id', $this->userId))
            ->when($this->toolSlug, fn ($q) => $q->where('tool_slug', $this->toolSlug))
            ->when($this->provider, fn ($q) => $q->where('provider', $this->provider))
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->select(['id', 'user_id', 'tool_slug', 'model', 'provider', 'input_tokens', 'output_tokens', 'cost_usd', 'credits_used', 'status', 'response_time_ms', 'created_at'])
            ->orderBy('created_at', 'desc');
    }

    public function headings(): array
    {
        return ['Date', 'User', 'Tool', 'Model', 'Provider', 'Input Tokens', 'Output Tokens', 'Cost (USD)', 'Credits Used', 'Status', 'Response Time (ms)'];
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
}

<?php

namespace App\Exports\Registry\Datasets;

use App\Exports\Registry\Column;
use App\Exports\Registry\Dataset;
use App\Models\AiUsageLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AiUsageDataset extends Dataset
{
    public function key(): string
    {
        return 'ai-usage';
    }

    public function label(): string
    {
        return translate('AI Usage');
    }

    public function supportedFilters(): array
    {
        return ['tool_slug', 'provider', 'user_id', 'date'];
    }

    public function query(array $filters): Builder
    {
        return AiUsageLog::query()
            ->with('user:id,name,email')
            ->when($filters['tool_slug'] ?? null, fn ($q, $tools) => $q->whereIn('tool_slug', (array) $tools))
            ->when($filters['provider'] ?? null, fn ($q, $providers) => $q->whereIn('provider', (array) $providers))
            ->when($filters['user_id'] ?? null, fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest();
    }

    public function columns(): array
    {
        return [
            new Column('date', translate('Date'), fn ($l) => $l->created_at?->format('Y-m-d H:i')),
            new Column('user', translate('User'), fn ($l) => $l->user?->name ?? translate('Deleted')),
            new Column('tool', translate('Tool'), fn ($l) => $l->tool_slug),
            new Column('model', translate('Model'), fn ($l) => $l->model),
            new Column('provider', translate('Provider'), fn ($l) => $l->provider),
            new Column('input_tokens', translate('Input Tokens'), fn ($l) => $l->input_tokens),
            new Column('output_tokens', translate('Output Tokens'), fn ($l) => $l->output_tokens),
            new Column('cost_usd', translate('Cost (USD)'), fn ($l) => $l->cost_usd),
            new Column('credits_used', translate('Credits Used'), fn ($l) => $l->credits_used),
            new Column('status', translate('Status'), fn ($l) => $l->status),
        ];
    }

    public function stats(array $filters): array
    {
        $query = $this->query($filters);

        return [
            translate('Total Requests') => number_format((clone $query)->count()),
            translate('Total Tokens') => number_format(
                (float) (clone $query)->sum(DB::raw('COALESCE(input_tokens, 0) + COALESCE(output_tokens, 0)'))
            ),
            translate('Total Cost (USD)') => number_format((float) (clone $query)->sum('cost_usd'), 4),
            translate('Unique Users') => number_format((clone $query)->distinct('user_id')->count('user_id')),
        ];
    }
}

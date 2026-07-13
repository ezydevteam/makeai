<?php

namespace App\Exports\Registry\Datasets;

use App\Exports\Registry\Column;
use App\Exports\Registry\Dataset;
use App\Models\GenerationHistory;
use Illuminate\Database\Eloquent\Builder;

class GenerationHistoryDataset extends Dataset
{
    public function key(): string { return 'generation-history'; }
    public function label(): string { return translate('Generation History'); }
    public function supportedFilters(): array { return ['tool_slug', 'provider', 'user_id', 'date']; }

    public function query(array $filters): Builder
    {
        return GenerationHistory::query()
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
            new Column('date', translate('Date'), fn ($r) => $r->created_at?->format('Y-m-d H:i')),
            new Column('user', translate('User'), fn ($r) => $r->user?->name ?? translate('Deleted')),
            new Column('tool', translate('Tool'), fn ($r) => $r->tool_slug),
            new Column('model', translate('Model'), fn ($r) => $r->model),
            new Column('provider', translate('Provider'), fn ($r) => $r->provider),
            new Column('tokens_input', translate('Tokens In'), fn ($r) => $r->tokens_input),
            new Column('tokens_output', translate('Tokens Out'), fn ($r) => $r->tokens_output),
            new Column('favorited', translate('Favorited'), fn ($r) => $r->is_favorited ? translate('Yes') : translate('No')),
            new Column('label', translate('Label'), fn ($r) => $r->label),
        ];
    }

    public function stats(array $filters): array
    {
        $query = $this->query($filters);

        return [
            translate('Total') => number_format((clone $query)->count()),
            translate('Unique Users') => number_format((clone $query)->distinct('user_id')->count('user_id')),
            translate('Unique Tools') => number_format((clone $query)->distinct('tool_slug')->count('tool_slug')),
            translate('Favorited') => number_format((clone $query)->where('is_favorited', true)->count()),
        ];
    }
}

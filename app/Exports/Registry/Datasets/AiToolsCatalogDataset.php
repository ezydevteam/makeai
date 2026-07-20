<?php

namespace App\Exports\Registry\Datasets;

use App\Exports\Registry\Column;
use App\Exports\Registry\Dataset;
use App\Models\AiTool;
use Illuminate\Database\Eloquent\Builder;

class AiToolsCatalogDataset extends Dataset
{
    public function key(): string { return 'ai-tools-catalog'; }
    public function label(): string { return translate('AI Tools Catalog'); }
    public function supportedFilters(): array { return ['status']; }

    public function query(array $filters): Builder
    {
        return AiTool::query()
            ->with('category:id,name')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('is_active', $status === 'active'))
            ->orderBy('sort_order');
    }

    public function columns(): array
    {
        return [
            new Column('name', translate('Name'), fn ($r) => $r->name),
            new Column('slug', translate('Slug'), fn ($r) => $r->slug),
            new Column('category', translate('Category'), fn ($r) => $r->category?->name ?? '—'),
            new Column('type', translate('Type'), fn ($r) => $r->type),
            new Column('access_level', translate('Access Level'), fn ($r) => $r->access_level),
            new Column('active', translate('Active'), fn ($r) => $r->is_active ? translate('Yes') : translate('No')),
            new Column('featured', translate('Featured'), fn ($r) => $r->is_featured ? translate('Yes') : translate('No')),
            new Column('usage_count', translate('Usage Count'), fn ($r) => $r->usage_count),
            new Column('views_count', translate('Views'), fn ($r) => $r->views_count),
            new Column('avg_rating', translate('Avg Rating'), fn ($r) => number_format((float) $r->avg_rating, 2)),
            new Column('sort_order', translate('Sort'), fn ($r) => $r->sort_order),
        ];
    }

    public function stats(array $filters): array
    {
        return [
            translate('Total Tools') => number_format(AiTool::count()),
            translate('Active') => number_format(AiTool::where('is_active', true)->count()),
            translate('Featured') => number_format(AiTool::where('is_featured', true)->count()),
            translate('Categories') => number_format(AiTool::distinct('category_id')->count('category_id')),
        ];
    }
}

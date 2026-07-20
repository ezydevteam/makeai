<?php

namespace App\Exports\Registry;

use Illuminate\Database\Eloquent\Builder;

/**
 * One exportable dataset, defined exactly once.
 *
 * A Dataset declares its identity, its license/feature availability gate, a
 * filtered query, and its columns. Every output format (XLSX, CSV, PDF) and the
 * row-count estimate are derived from that single definition by the Export
 * Center — adding a new export is a matter of adding one Dataset subclass and
 * registering it in DatasetRegistry.
 *
 * Availability is DATA-driven: a dataset is hidden only when the data it exports
 * cannot exist under the current license/settings (e.g. revenue with billing
 * off). Capabilities are never gated by license.
 */
abstract class Dataset
{
    /** Stable machine key (kebab-case), used in requests, filenames and storage. */
    abstract public function key(): string;

    /** Human-readable label for the export-type picker. */
    abstract public function label(): string;

    /**
     * License / feature gate. When false the dataset is omitted from every entry
     * point (list, export, estimate) — the single source of truth for gating.
     */
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * The filtered Eloquent query. Datasets read only the filter keys they
     * support from $filters and ignore the rest.
     *
     * @param  array<string,mixed>  $filters
     */
    abstract public function query(array $filters): Builder;

    /** @return Column[] */
    abstract public function columns(): array;

    /**
     * Summary stats rendered in the PDF header as label => formatted-value.
     *
     * @param  array<string,mixed>  $filters
     * @return array<string,string>
     */
    public function stats(array $filters): array
    {
        return [];
    }

    /**
     * Filter keys this dataset honours, surfaced to the UI so it can show only
     * the relevant filter controls. e.g. ['status', 'plan_id'].
     *
     * @return string[]
     */
    public function supportedFilters(): array
    {
        return [];
    }

    /**
     * The subset of columns to render, honouring an optional column-selection
     * whitelist of column keys. Order and completeness fall back to all columns.
     *
     * @param  string[]|null  $only
     * @return Column[]
     */
    public function selectedColumns(?array $only = null): array
    {
        $columns = $this->columns();

        if (empty($only)) {
            return $columns;
        }

        $selected = array_values(array_filter($columns, fn (Column $c) => in_array($c->key, $only, true)));

        return $selected ?: $columns;
    }

    /**
     * @param  string[]|null  $only
     * @return string[]
     */
    public function headings(?array $only = null): array
    {
        return array_map(fn (Column $c) => $c->header, $this->selectedColumns($only));
    }

    /**
     * @param  string[]|null  $only
     * @return array<int,mixed>
     */
    public function row(mixed $model, ?array $only = null): array
    {
        return array_map(fn (Column $c) => $c->value($model), $this->selectedColumns($only));
    }

    /**
     * Metadata for the Export Center UI (picker option + available columns/filters).
     *
     * @return array{value:string,label:string,filters:string[],columns:array<int,array{key:string,label:string}>}
     */
    public function toMeta(): array
    {
        return [
            'value' => $this->key(),
            'label' => $this->label(),
            'filters' => $this->supportedFilters(),
            'columns' => array_map(
                fn (Column $c) => ['key' => $c->key, 'label' => $c->header],
                $this->columns()
            ),
        ];
    }
}

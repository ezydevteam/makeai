<?php

namespace App\Exports\Registry;

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

/**
 * Renders any registered Dataset to XLSX.
 *
 * Serialization-safe for queued exports: it stores ONLY scalars (the dataset
 * key, filter array, and selected column keys) and rehydrates the Dataset — and
 * therefore its column closures — from the registry inside each method. Nothing
 * with a closure is ever serialized onto the queue.
 */
class GenericExcelExport implements FromQuery, WithHeadings, WithMapping, WithChunkReading, WithStrictNullComparison, ShouldAutoSize, WithEvents
{
    use Exportable;

    public int $chunkSize = 500;

    /**
     * @param  array<string,mixed>  $filters
     * @param  string[]|null  $columns
     */
    public function __construct(
        public string $datasetKey,
        public array $filters = [],
        public ?array $columns = null,
    ) {}

    private function dataset(): Dataset
    {
        return app(DatasetRegistry::class)->resolve($this->datasetKey);
    }

    public function query(): Builder
    {
        return $this->dataset()->query($this->filters);
    }

    public function headings(): array
    {
        return $this->dataset()->headings($this->columns);
    }

    public function map($row): array
    {
        return $this->dataset()->row($row, $this->columns);
    }

    public function chunkSize(): int
    {
        return $this->chunkSize;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()
                    ->getStyle('A1:' . $event->sheet->getHighestColumn() . '1')
                    ->getFont()->setBold(true);
            },
        ];
    }
}

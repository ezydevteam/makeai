<?php

namespace App\Exports\Registry;

/**
 * A single exportable column: a stable key (for column selection), a
 * human-readable header, and a callback that maps a model row to a cell value.
 *
 * The value callback lives in the owning Dataset's columns() method — it is NEVER
 * serialized. Queued exports persist only the dataset key + column keys and
 * rebuild the Dataset (and therefore these closures) on the worker. See
 * GenericExcelExport.
 */
class Column
{
    /** @param callable(mixed):mixed $value */
    public function __construct(
        public readonly string $key,
        public readonly string $header,
        public $value,
    ) {}

    public function value(mixed $row): mixed
    {
        return ($this->value)($row);
    }
}

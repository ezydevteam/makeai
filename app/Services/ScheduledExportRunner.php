<?php

namespace App\Services;

use App\Exports\Registry\DatasetRegistry;
use App\Exports\Registry\GenericExcelExport;
use App\Models\ScheduledExport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates the file for a scheduled export, mirroring the Export Center's
 * per-format rendering but writing to disk instead of streaming a response.
 * Honours the dataset's current availability gate (skips if the license/setting
 * changed) and applies a rolling date window matched to the frequency.
 */
class ScheduledExportRunner
{
    private const PDF_ROW_LIMIT = 5000;

    public function __construct(
        private DatasetRegistry $registry,
        private ExportService $exportService,
    ) {}

    /**
     * Generate and store the export file.
     *
     * @return string|null The stored relative path, or null when the dataset is
     *                     no longer available (gate closed) so the caller can skip.
     */
    public function generate(ScheduledExport $schedule): ?string
    {
        $dataset = $this->registry->find($schedule->dataset);
        if ($dataset === null) {
            return null;
        }

        $filters = $this->resolveFilters($schedule);
        $columns = $schedule->columns ?: null;
        $path = $this->buildPath($schedule);

        switch ($schedule->format) {
            case 'csv':
                Storage::disk('local')->put($path, $this->exportService->csvString(
                    $dataset->headings($columns),
                    $dataset->query($filters)->lazy(),
                    fn ($row) => $dataset->row($row, $columns)
                ));
                break;

            case 'pdf':
                $rows = $dataset->query($filters)->limit(self::PDF_ROW_LIMIT)->get();
                Storage::disk('local')->put($path, $this->exportService->renderPdfString('admin.reports.pdf.dataset', [
                    'appName' => settings('app_name', translate('Application')),
                    'adminName' => $schedule->admin?->name ?? translate('Admin'),
                    'title' => $dataset->label(),
                    'dateFrom' => $filters['date_from'] ?? null,
                    'dateTo' => $filters['date_to'] ?? null,
                    'stats' => $dataset->stats($filters),
                    'headers' => $dataset->headings($columns),
                    'rows' => $rows->map(fn ($r) => $dataset->row($r, $columns))->all(),
                ]));
                break;

            default: // xlsx
                $this->exportService->storeExcel(
                    new GenericExcelExport($dataset->key(), $filters, $columns),
                    $path
                );
                break;
        }

        return $path;
    }

    /**
     * Saved non-date filters plus a rolling date window for this run.
     *
     * @return array<string,mixed>
     */
    private function resolveFilters(ScheduledExport $schedule): array
    {
        $filters = $schedule->filters ?? [];
        [$from, $to] = $schedule->rollingWindow(now());
        $filters['date_from'] = $from;
        $filters['date_to'] = $to;

        return $filters;
    }

    private function buildPath(ScheduledExport $schedule): string
    {
        $base = Str::slug($schedule->name) ?: $schedule->dataset;
        $stamp = now()->format('Y-m-d-His');

        return 'exports/' . $schedule->admin_id . '/' . $schedule->dataset . '-' . $base . '-' . $stamp . '.' . $schedule->format;
    }
}

<?php

namespace App\Services;

use Maatwebsite\Excel\Facades\Excel;
use Mpdf\Mpdf;
use Illuminate\Http\Response;
use Illuminate\Support\LazyCollection;

class ExportService
{
    public function downloadExcel(object $export, string $filename): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return Excel::download($export, $filename . '.xlsx');
    }

    public function queueExcel(object $export, string $filename, int $adminId): void
    {
        $path = 'exports/' . $adminId . '/' . $filename . '-' . now()->format('Y-m-d-His') . '.xlsx';
        Excel::queue($export, $path, 'local')
            ->chain([new \App\Jobs\NotifyExportReadyJob($adminId, $path, $filename)]);
    }

    public function streamCsv(string $filename, array $headers, LazyCollection $rows, callable $mapper): Response
    {
        return response()->stream(function () use ($headers, $rows, $mapper) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);
            $rows->each(fn ($row) => fputcsv($handle, $mapper($row)));
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function downloadPdf(string $view, array $data, string $filename, array $options = []): Response
    {
        $mpdf = new Mpdf(array_merge([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_left' => 15,
            'margin_right' => 15,
        ], $options));
        $mpdf->SetTitle($filename);
        $mpdf->WriteHTML(view($view, $data)->render());

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.pdf"',
        ]);
    }
}

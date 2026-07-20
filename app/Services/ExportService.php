<?php

namespace App\Services;

use Maatwebsite\Excel\Facades\Excel;
use Mpdf\Mpdf;
use Illuminate\Http\Response;
use Illuminate\Support\LazyCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    /**
     * Defaults applied to every PDF this service renders.
     *
     * backupSIPFont exists to keep a packaging decision safe. mPDF defaults it to sun-extb,
     * whose 16.8 MB font file the release build prunes (PRUNE_FONTS in build-release.php).
     *
     * With mPDF's stock settings that prune is already harmless — useSubstitutions is false,
     * so a Supplementary-Ideographic-Plane character (a rare historic CJK ideograph, say)
     * renders as a blank glyph and no font is loaded. Verified, not assumed.
     *
     * Turn useSubstitutions on, however, and mPDF reaches for backupSIPFont by name and
     * throws `Cannot find TTF TrueType font file "Sun-ExtB.ttf"` — a fatal error mid-invoice.
     * Nothing enables it today, but it is one option away in any caller's $options array, and
     * the failure would surface far from the cause. Pinning this to a font that always ships
     * removes the trap: sun-exta is the natural target, already the last resort in mPDF's
     * backupSubsFont chain and the only bundled font covering common CJK.
     */
    private const PDF_DEFAULTS = [
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_top' => 20,
        'margin_bottom' => 20,
        'margin_left' => 15,
        'margin_right' => 15,
        'backupSIPFont' => 'sun-exta',
    ];

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

    /** Write an Excel export synchronously to a disk path (for scheduled runs). */
    public function storeExcel(object $export, string $path, string $disk = 'local'): void
    {
        Excel::store($export, $path, $disk);
    }

    /** Build a CSV file as a string (BOM + header + rows) for storage. */
    public function csvString(array $headers, LazyCollection $rows, callable $mapper): string
    {
        $handle = fopen('php://temp', 'r+');
        fprintf($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers);
        $rows->each(fn ($row) => fputcsv($handle, $mapper($row)));
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    /** Render a PDF view to raw bytes for storage. */
    public function renderPdfString(string $view, array $data, array $options = []): string
    {
        $mpdf = new Mpdf(array_merge(self::PDF_DEFAULTS, $options));
        $mpdf->WriteHTML(view($view, $data)->render());

        return $mpdf->Output('', 'S');
    }

    public function streamCsv(string $filename, array $headers, LazyCollection $rows, callable $mapper): StreamedResponse
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
        $mpdf = new Mpdf(array_merge(self::PDF_DEFAULTS, $options));
        $mpdf->SetTitle($filename);
        $mpdf->WriteHTML(view($view, $data)->render());

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.pdf"',
        ]);
    }

    public function downloadPdfFromHtml(string $html, string $filename, array $options = []): Response
    {
        $mpdf = new Mpdf(array_merge(self::PDF_DEFAULTS, $options));
        $mpdf->SetTitle($filename);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.pdf"',
        ]);
    }
}

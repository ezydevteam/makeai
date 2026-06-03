<?php

namespace App\Services\AI\Rag;

use RuntimeException;

/**
 * TextExtractionService — extracts clean text from files.
 *
 * Supports: PDF, DOCX, TXT, CSV, XLSX, Markdown, HTML, and web URLs.
 */
class TextExtractionService
{
    private static array $supportedExtensions = [
        'txt', 'md', 'markdown', 'html', 'htm', 'csv', 'json',
        'pdf', 'docx', 'xlsx', 'pptx',
    ];

    /**
     * Extract text from a file path.
     */
    public function extract(string $filePath): string
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeType = mime_content_type($filePath);

        return match (true) {
            in_array($extension, ['txt', 'md', 'markdown', 'csv', 'json'])
                || str_starts_with($mimeType, 'text/')
                || $mimeType === 'application/json' => $this->extractPlainText($filePath),

            $extension === 'html' || $extension === 'htm'
                || str_contains($mimeType, 'html') => $this->extractHtml($filePath),

            $extension === 'pdf'
                || $mimeType === 'application/pdf' => $this->extractPdf($filePath),

            $extension === 'docx'
                || $mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                => $this->extractDocx($filePath),

            $extension === 'xlsx'
                || $mimeType === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                => $this->extractXlsx($filePath),

            $extension === 'pptx'
                || $mimeType === 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
                => $this->extractPptx($filePath),

            default => throw new RuntimeException("Unsupported file type: {$extension} ({$mimeType})"),
        };
    }

    /**
     * Extract text from a web URL.
     */
    public function extractFromUrl(string $url): string
    {
        $html = @file_get_contents($url);

        if ($html === false) {
            throw new RuntimeException("Failed to fetch URL: {$url}");
        }

        $text = $this->stripHtml($html);

        if (empty(trim($text))) {
            throw new RuntimeException("No extractable text found at URL: {$url}");
        }

        return $text;
    }

    public function supports(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return in_array($extension, self::$supportedExtensions, true);
    }

    // ─── Private Extractors ──────────────────────────────────────

    private function extractPlainText(string $filePath): string
    {
        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new RuntimeException("Failed to read file: {$filePath}");
        }

        return trim($content);
    }

    private function extractHtml(string $filePath): string
    {
        $html = file_get_contents($filePath);

        return $this->stripHtml($html);
    }

    private function stripHtml(string $html): string
    {
        // Remove scripts and styles
        $html = preg_replace('#<script[^>]*>.*?</script>#is', '', $html);
        $html = preg_replace('#<style[^>]*>.*?</style>#is', '', $html);

        // Convert block elements to newlines
        $html = preg_replace('#</?(div|p|h[1-6]|li|br|tr|article|section|header|footer)[^>]*>#i', "\n", $html);

        // Strip remaining tags
        $text = strip_tags($html);

        // Decode entities and normalize whitespace
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\h+/u', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    private function extractPdf(string $filePath): string
    {
        // Use pdftotext if available, otherwise use built-in parsing
        $pdftotext = trim(shell_exec('where pdftotext 2>nul') ?: '');

        if (! empty($pdftotext)) {
            $escapedPath = escapeshellarg($filePath);
            $output = shell_exec("pdftotext {$escapedPath} - 2>&1");

            return $output ? trim($output) : '';
        }

        // Fallback: basic PDF text extraction via regex
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Failed to read PDF: {$filePath}");
        }

        return $this->extractPdfTextFallback($content);
    }

    private function extractDocx(string $filePath): string
    {
        $zip = new \ZipArchive;
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException("Failed to open DOCX file: {$filePath}");
        }

        $xmlContent = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! $xmlContent) {
            throw new RuntimeException("Invalid DOCX file: no document.xml found");
        }

        $xml = simplexml_load_string($xmlContent);
        $xml->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $paragraphs = $xml->xpath('//w:p');
        $textLines = [];

        foreach ($paragraphs as $p) {
            $texts = $p->xpath('.//w:t');
            $line = '';
            foreach ($texts as $t) {
                $line .= (string) $t;
            }
            if (trim($line) !== '') {
                $textLines[] = trim($line);
            }
        }

        return implode("\n", $textLines);
    }

    private function extractXlsx(string $filePath): string
    {
        $zip = new \ZipArchive;
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException("Failed to open XLSX file: {$filePath}");
        }

        // Read shared strings
        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            $ss = simplexml_load_string($ssXml);
            $ss->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach ($ss->xpath('//s:si') as $si) {
                $texts = $si->xpath('.//s:t');
                $sharedStrings[] = implode('', array_map(fn ($t) => (string) $t, $texts));
            }
        }

        // Read first sheet
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (! $sheetXml) {
            throw new RuntimeException("Invalid XLSX file: no sheet data found");
        }

        $sheet = simplexml_load_string($sheetXml);
        $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = $sheet->xpath('//s:row');
        $output = [];

        foreach ($rows as $row) {
            $cells = $row->xpath('.//s:c');
            $rowData = [];
            foreach ($cells as $cell) {
                $type = (string) ($cell['t'] ?? '');
                $value = (string) ($cell->v ?? '');

                if ($type === 's' && isset($sharedStrings[(int) $value])) {
                    $rowData[] = $sharedStrings[(int) $value];
                } else {
                    $rowData[] = $value;
                }
            }
            $output[] = implode("\t", $rowData);
        }

        return implode("\n", $output);
    }

    private function extractPptx(string $filePath): string
    {
        $zip = new \ZipArchive;
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException("Failed to open PPTX file: {$filePath}");
        }

        $text = '';
        for ($i = 1; $i <= 100; $i++) {
            $slideXml = $zip->getFromName("ppt/slides/slide{$i}.xml");
            if (! $slideXml) {
                break;
            }

            $slide = simplexml_load_string($slideXml);
            $slide->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');

            $texts = $slide->xpath('//a:t');
            $slideText = implode(' ', array_map(fn ($t) => (string) $t, $texts));

            if (trim($slideText) !== '') {
                $text .= "Slide {$i}:\n" . trim($slideText) . "\n\n";
            }
        }

        $zip->close();

        return trim($text);
    }

    private function extractPdfTextFallback(string $content): string
    {
        // Decode stream objects
        $text = '';
        $content = preg_replace('/\s+/', ' ', $content);

        // Try to extract text from BT/ET blocks
        if (preg_match_all('/BT(.*?)ET/s', $content, $matches)) {
            foreach ($matches[1] as $block) {
                // Extract text between parentheses in Tj/TJ operators
                if (preg_match_all('/\(([^)]*)\)\s*Tj/', $block, $tjMatches)) {
                    foreach ($tjMatches[1] as $tjText) {
                        $text .= $this->decodePdfString($tjText) . ' ';
                    }
                }
            }
        }

        return trim($text);
    }

    private function decodePdfString(string $string): string
    {
        // Handle PDF escape sequences
        $string = str_replace(
            ['\\(', '\\)', '\\\\', '\n', '\r', '\t'],
            ['(', ')', '\\', "\n", "\r", "\t"],
            $string
        );

        return $string;
    }
}

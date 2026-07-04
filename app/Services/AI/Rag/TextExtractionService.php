<?php

namespace App\Services\AI\Rag;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * TextExtractionService — extracts clean text from files.
 *
 * Supports: PDF, DOCX, TXT, CSV, XLSX, Markdown, HTML, and web URLs.
 */
class TextExtractionService
{
    private static array $supportedExtensions = [
        'txt', 'md', 'markdown', 'html', 'htm', 'csv', 'json',
        'pdf', 'docx', 'xlsx', 'pptx', 'png', 'jpg', 'jpeg', 'webp', 'gif',
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
                || $mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => $this->extractDocx($filePath),

            $extension === 'xlsx'
                || $mimeType === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => $this->extractXlsx($filePath),

            $extension === 'pptx'
                || $mimeType === 'application/vnd.openxmlformats-officedocument.presentationml.presentation' => $this->extractPptx($filePath),

            in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif'])
                || str_starts_with($mimeType, 'image/') => $this->extractImage($filePath),

            default => throw new RuntimeException("Unsupported file type: {$extension} ({$mimeType})"),
        };
    }

    /**
     * Extract text from a web URL.
     */
    public function extractFromUrl(string $url): string
    {
        // Block loopback/private/link-local targets (SSRF), including redirects.
        \App\Services\Security\SsrfGuard::assertPublicUrl($url);

        try {
            $response = Http::timeout(30)
                ->withOptions([
                    'allow_redirects' => \App\Services\Security\SsrfGuard::redirectOptions(3),
                ])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; MakeAI RAG Bot/1.0)',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                ])
                ->get($url);

            if (! $response->successful()) {
                throw new RuntimeException("Failed to fetch URL (HTTP {$response->status()}): {$url}");
            }

            $html = $response->body();

            $maxBytes = max(1, (int) settings('rag_max_url_fetch_mb', 10)) * 1024 * 1024;
            if (strlen($html) > $maxBytes) {
                throw new RuntimeException('The page is too large to ingest.');
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new RuntimeException("Could not connect to URL: {$url} — {$e->getMessage()}");
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
        // Use pdftotext if available (fastest)
        $pdftotext = trim(shell_exec('where pdftotext 2>nul') ?: '');

        if (! empty($pdftotext)) {
            $escapedPath = escapeshellarg($filePath);
            $output = shell_exec("pdftotext {$escapedPath} - 2>&1");

            $text = $output ? trim($output) : '';
            if (! empty($text)) {
                return $text;
            }
        }

        // Use smalot/pdfparser (pure PHP, works everywhere)
        $parser = new PdfParser;
        $pdf = $parser->parseFile($filePath);

        return trim($pdf->getText());
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
            throw new RuntimeException('Invalid DOCX file: no document.xml found');
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
            throw new RuntimeException('Invalid XLSX file: no sheet data found');
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
                $text .= "Slide {$i}:\n".trim($slideText)."\n\n";
            }
        }

        $zip->close();

        return trim($text);
    }

    private function extractImage(string $filePath): string
    {
        try {
            $agent = new \Laravel\Ai\AnonymousAgent(
                instructions: 'You are an OCR assistant. Your sole job is to transcribe all text found in the image. Do not explain, do not add notes, do not summarize, and do not introduce your response. Just transcribe the exact text from the image. If there is no text, reply with nothing.',
                messages: [],
                tools: []
            );

            $providerName = addon_setting('ai-assistant', 'provider') ?: settings('default_ai_provider', 'openai');
            $modelName = addon_setting('ai-assistant', 'model') ?: settings('default_ai_model', 'gpt-4o-mini');

            // Inject the API key into config so the SDK can read it
            $customApiKey = addon_setting('ai-assistant', 'custom_api_key');
            if (!empty($customApiKey)) {
                config()->set("ai.providers.{$providerName}.key", $customApiKey);
            } else {
                $driverName = strtolower($providerName);
                if ($driverName === 'gemini') {
                    $driverName = 'google';
                }
                $keyRecord = \App\Models\AiKey::forProvider($driverName)->available()->orderBy('usage_count', 'asc')->first();
                if ($keyRecord) {
                    config()->set("ai.providers.{$providerName}.key", $keyRecord->api_key);
                }
            }

            $visionModel = $modelName;
            if (str_contains($modelName, 'gpt-3.5') || str_contains($modelName, 'gpt-3.5-turbo')) {
                $visionModel = 'gpt-4o-mini';
            }

            $imageAttachment = new \Laravel\Ai\Files\LocalImage($filePath);

            $response = $agent->prompt(
                prompt: 'Transcribe the text in this image.',
                attachments: [$imageAttachment],
                provider: $providerName,
                model: $visionModel
            );

            return trim($response->text);
        } catch (\Throwable $e) {
            \Log::error('OCR extraction failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException('Image OCR extraction failed: ' . $e->getMessage());
        }
    }

    // ─── Private Extractors ──────────────────────────────────────
}

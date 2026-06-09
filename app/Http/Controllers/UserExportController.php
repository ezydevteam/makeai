<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\GenerationHistory;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\LazyCollection;

class UserExportController extends Controller
{
    public function __construct(
        private readonly ExportService $export,
    ) {}

    public function export(Request $request): Response
    {
        $validated = $request->validate([
            'type' => 'required|in:generations,documents',
            'format' => 'required|in:csv,xlsx,pdf',
        ]);

        $user = Auth::user();
        $type = $validated['type'];
        $format = $validated['format'];
        $filename = "my-{$type}-".now()->format('Y-m-d');

        return match ([$type, $format]) {
            ['generations', 'csv'] => $this->streamGenerationsCsv($user, $filename),
            ['documents', 'csv'] => $this->streamDocumentsCsv($user, $filename),
            default => $this->streamGenerationsCsv($user, $filename),
        };
    }

    private function streamGenerationsCsv($user, string $filename): Response
    {
        $rows = GenerationHistory::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->lazy();

        return $this->export->streamCsv(
            $filename,
            ['Date', 'Tool', 'Model', 'Provider', 'Input Tokens', 'Output Tokens', 'Preview'],
            $rows,
            function ($row) {
                return [
                    $row->created_at->format('Y-m-d H:i'),
                    $row->tool_slug,
                    $row->model,
                    $row->provider,
                    $row->tokens_input,
                    $row->tokens_output,
                    $row->output_preview,
                ];
            },
        );
    }

    private function streamDocumentsCsv($user, string $filename): Response
    {
        $rows = Document::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->lazy();

        return $this->export->streamCsv(
            $filename,
            ['Title', 'Tool', 'Words', 'Created'],
            $rows,
            function ($row) {
                return [
                    $row->title,
                    $row->tool_slug ?? '—',
                    $row->word_count ?? 0,
                    $row->created_at->format('Y-m-d H:i'),
                ];
            },
        );
    }
}

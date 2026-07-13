<?php

namespace App\Http\Controllers\Admin;

use App\Exports\Registry\Dataset;
use App\Exports\Registry\DatasetRegistry;
use App\Exports\Registry\GenericExcelExport;
use App\Http\Controllers\Controller;
use App\Models\AiTool;
use App\Models\ExportPreset;
use App\Models\Plan;
use App\Models\ScheduledExport;
use App\Services\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Admin Export Center.
 *
 * Every export dataset — its label, license/feature gate, filters, columns and
 * per-format rendering — is declared once as a Dataset and registered in
 * DatasetRegistry. This controller is a thin driver over that registry, so
 * gating lives in exactly one place and adding a dataset never touches the
 * controller. See app/Exports/Registry.
 */
class ExportCenterController extends Controller
{
    /** Rows above this stream/queue rather than build synchronously. */
    private const SYNC_ROW_LIMIT = 5000;

    public function __construct(
        private ExportService $exportService,
        private DatasetRegistry $registry,
    ) {}

    public function index(): \Inertia\Response
    {
        $this->authorizeExports();
        $disk = Storage::disk('local');
        $files = collect($disk->files('exports/' . auth('admin')->id()))
            ->map(function ($path) use ($disk) {
                $filename = basename($path);

                return [
                    'path' => $path,
                    'filename' => $filename,
                    'size' => $disk->size($path),
                    'modified' => $disk->lastModified($path),
                    'type' => $this->guessExportType($filename),
                    'format' => $this->guessExportFormat($filename),
                ];
            })
            ->sortByDesc('modified')
            ->values();

        return Inertia::render('Admin/Reports', [
            'recentExports' => $files,
            // Registry already filters by availability, so the picker (and its
            // per-dataset column/filter metadata) reflects the current license.
            'exportTypes' => array_values(array_map(
                fn (Dataset $d) => $d->toMeta(),
                $this->registry->available()
            )),
            'isProAvailable' => isProAvailable(),
            'plans' => Plan::where('is_active', true)->select('id', 'name')->get()
                ->map(fn ($p) => ['value' => (string) $p->id, 'label' => $p->name])->values()->all(),
            'gateways' => array_values(array_map(
                fn ($slug) => ['value' => $slug, 'label' => config("payment-gateways.{$slug}.name", $slug)],
                array_keys(config('payment-gateways', []))
            )),
            'providers' => array_values(array_map(
                fn ($p) => ['value' => $p, 'label' => ucfirst($p)],
                array_keys(config('ai.providers', []))
            )),
            'toolSlugs' => AiTool::where('is_active', true)
                ->select('slug', 'name')
                ->get()
                ->map(fn ($t) => ['value' => $t->slug, 'label' => $t->name])
                ->values()
                ->all(),
            // Saved presets for this admin, limited to datasets still available.
            'presets' => ExportPreset::where('admin_id', auth('admin')->id())
                ->whereIn('dataset', $this->registry->availableKeys())
                ->latest()
                ->get()
                ->map(fn (ExportPreset $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'dataset' => $p->dataset,
                    'format' => $p->format,
                    'filters' => $p->filters ?? [],
                    'columns' => $p->columns ?? [],
                ])
                ->all(),
            'schedules' => ScheduledExport::where('admin_id', auth('admin')->id())
                ->latest()
                ->get()
                ->map(fn (ScheduledExport $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'dataset' => $s->dataset,
                    'format' => $s->format,
                    'frequency' => $s->frequency,
                    'is_active' => $s->is_active,
                    'last_run_at' => $s->last_run_at?->toIso8601String(),
                    'next_run_at' => $s->next_run_at?->toIso8601String(),
                    'available' => in_array($s->dataset, $this->registry->availableKeys(), true),
                ])
                ->all(),
        ]);
    }

    public function storePreset(Request $request): JsonResponse
    {
        $this->authorizeExports();
        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'dataset' => ['required', Rule::in($this->registry->availableKeys())],
            'format' => 'required|in:xlsx,csv,pdf',
            'filters' => 'nullable|array',
            'columns' => 'nullable|array',
            'columns.*' => 'string',
        ]);

        // Keep only column keys the dataset actually defines.
        $dataset = $this->registry->resolve($validated['dataset']);
        $columns = $this->sanitizeColumns($dataset, $validated['columns'] ?? null);

        $preset = ExportPreset::create([
            'admin_id' => auth('admin')->id(),
            'name' => $validated['name'],
            'dataset' => $validated['dataset'],
            'format' => $validated['format'],
            'filters' => $this->cleanFilters($validated['filters'] ?? []),
            'columns' => $columns,
        ]);

        return response()->json([
            'message' => translate('Preset saved.'),
            'preset' => [
                'id' => $preset->id,
                'name' => $preset->name,
                'dataset' => $preset->dataset,
                'format' => $preset->format,
                'filters' => $preset->filters ?? [],
                'columns' => $preset->columns ?? [],
            ],
        ]);
    }

    public function destroyPreset(ExportPreset $preset): JsonResponse
    {
        $this->authorizeExports();

        // Owner-scoped: an admin can only delete their own presets.
        if ($preset->admin_id !== auth('admin')->id()) {
            abort(403);
        }

        $preset->delete();

        return response()->json(['message' => translate('Preset deleted.')]);
    }

    public function storeSchedule(Request $request): JsonResponse
    {
        $this->authorizeExports();
        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'dataset' => ['required', Rule::in($this->registry->availableKeys())],
            'format' => 'required|in:xlsx,csv,pdf',
            'frequency' => ['required', Rule::in(ScheduledExport::FREQUENCIES)],
            'filters' => 'nullable|array',
            'columns' => 'nullable|array',
            'columns.*' => 'string',
        ]);

        $dataset = $this->registry->resolve($validated['dataset']);
        $columns = $this->sanitizeColumns($dataset, $validated['columns'] ?? null);

        $schedule = new ScheduledExport([
            'admin_id' => auth('admin')->id(),
            'name' => $validated['name'],
            'dataset' => $validated['dataset'],
            'format' => $validated['format'],
            'frequency' => $validated['frequency'],
            'filters' => $this->cleanFilters($validated['filters'] ?? []),
            'columns' => $columns,
            'is_active' => true,
        ]);
        // First run one interval out — never fire immediately on creation.
        $schedule->next_run_at = $schedule->computeNextRun(now());
        $schedule->save();

        return response()->json([
            'message' => translate('Schedule created.'),
            'schedule' => $this->schedulePayload($schedule),
        ]);
    }

    public function toggleSchedule(ScheduledExport $schedule): JsonResponse
    {
        $this->authorizeExports();
        if ($schedule->admin_id !== auth('admin')->id()) {
            abort(403);
        }

        $schedule->is_active = ! $schedule->is_active;
        // Resuming a paused schedule re-arms its next run.
        if ($schedule->is_active) {
            $schedule->next_run_at = $schedule->computeNextRun(now());
        }
        $schedule->save();

        return response()->json([
            'message' => $schedule->is_active ? translate('Schedule resumed.') : translate('Schedule paused.'),
            'schedule' => $this->schedulePayload($schedule),
        ]);
    }

    public function destroySchedule(ScheduledExport $schedule): JsonResponse
    {
        $this->authorizeExports();
        if ($schedule->admin_id !== auth('admin')->id()) {
            abort(403);
        }

        $schedule->delete();

        return response()->json(['message' => translate('Schedule deleted.')]);
    }

    /** @return array<string,mixed> */
    private function schedulePayload(ScheduledExport $schedule): array
    {
        return [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'dataset' => $schedule->dataset,
            'format' => $schedule->format,
            'frequency' => $schedule->frequency,
            'is_active' => $schedule->is_active,
            'last_run_at' => $schedule->last_run_at?->toIso8601String(),
            'next_run_at' => $schedule->next_run_at?->toIso8601String(),
            'available' => in_array($schedule->dataset, $this->registry->availableKeys(), true),
        ];
    }

    /**
     * Whitelist only the filter keys datasets understand, dropping empties.
     *
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    private function cleanFilters(array $filters): array
    {
        $allowed = ['date_from', 'date_to', 'status', 'plan_id', 'user_id', 'provider', 'gateway', 'tool_slug'];

        return collect($filters)
            ->only($allowed)
            ->reject(fn ($v) => $v === null || $v === '' || $v === [])
            ->all();
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeExports();
        $request->validate($this->exportRules() + [
            'format' => 'required|in:xlsx,csv,pdf',
            'columns' => 'nullable|array',
            'columns.*' => 'string',
        ]);

        // Availability is enforced by exportRules() (Rule::in available keys);
        // resolve() re-checks as defence-in-depth.
        $dataset = $this->registry->resolve($request->type);
        $filters = $this->collectFilters($request);
        $columns = $this->sanitizeColumns($dataset, $request->input('columns'));
        $filename = $request->type . '-' . $filters['date_from'] . '-to-' . $filters['date_to'];

        return match ($request->format) {
            'xlsx' => $this->smartExcel(
                new GenericExcelExport($dataset->key(), $filters, $columns),
                $filename
            ),
            'csv' => $this->exportService->streamCsv(
                $filename,
                $dataset->headings($columns),
                $dataset->query($filters)->lazy(),
                fn ($row) => $dataset->row($row, $columns)
            ),
            'pdf' => $this->exportService->downloadPdf(
                'admin.reports.pdf.dataset',
                $this->pdfData($dataset, $filters, $columns),
                $filename
            ),
            default => response()->json(['message' => translate('Unsupported export type/format')], 422),
        };
    }

    public function estimate(Request $request): JsonResponse
    {
        $this->authorizeExports();
        $request->validate($this->exportRules());

        $dataset = $this->registry->resolve($request->type);
        $count = $dataset->query($this->collectFilters($request))->count();

        return response()->json(['count' => $count]);
    }

    public function download(string $file): BinaryFileResponse
    {
        $this->authorizeExports();
        $safeFile = basename($file);
        $path = 'exports/' . auth('admin')->id() . '/' . $safeFile;

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return response()->download(Storage::disk('local')->path($path));
    }

    public function deleteFile(string $file): JsonResponse
    {
        $this->authorizeExports();
        $safeFile = basename($file);
        $path = 'exports/' . auth('admin')->id() . '/' . $safeFile;

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }

        return response()->json(['message' => translate('File deleted')]);
    }

    /**
     * Shared validation rules for export + estimate. The `type` rule is the
     * single gate: only currently-available dataset keys are accepted.
     *
     * @return array<string,mixed>
     */
    private function exportRules(): array
    {
        return [
            'type' => ['required', Rule::in($this->registry->availableKeys())],
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'status' => 'nullable|string',
            'plan_id' => 'nullable',
            'user_id' => 'nullable',
            'provider' => 'nullable|array',
            'provider.*' => 'string',
            'gateway' => 'nullable|array',
            'gateway.*' => 'string',
            'tool_slug' => 'nullable|array',
            'tool_slug.*' => 'string',
        ];
    }

    /**
     * Normalise request input into the filter array every Dataset::query reads.
     *
     * @return array<string,mixed>
     */
    private function collectFilters(Request $request): array
    {
        return [
            'date_from' => $request->date_from ?: now()->subDays(30)->toDateString(),
            'date_to' => $request->date_to ?: now()->toDateString(),
            'status' => $request->status,
            'plan_id' => $request->plan_id,
            'user_id' => $request->user_id,
            'provider' => $request->provider,
            'gateway' => $request->gateway,
            'tool_slug' => $request->tool_slug,
        ];
    }

    /**
     * Keep only column keys the dataset actually defines; null = all columns.
     *
     * @param  mixed  $requested
     * @return string[]|null
     */
    private function sanitizeColumns(Dataset $dataset, $requested): ?array
    {
        if (! is_array($requested) || empty($requested)) {
            return null;
        }

        $valid = array_map(fn ($c) => $c->key, $dataset->columns());
        $filtered = array_values(array_intersect($requested, $valid));

        return $filtered ?: null;
    }

    /**
     * @param  array<string,mixed>  $filters
     * @param  string[]|null  $columns
     * @return array<string,mixed>
     */
    private function pdfData(Dataset $dataset, array $filters, ?array $columns): array
    {
        $rows = $dataset->query($filters)->limit(self::SYNC_ROW_LIMIT)->get();

        return [
            'appName' => settings('app_name', translate('Application')),
            'adminName' => auth('admin')->user()->name,
            'title' => $dataset->label(),
            'dateFrom' => $filters['date_from'],
            'dateTo' => $filters['date_to'],
            'stats' => $dataset->stats($filters),
            'headers' => $dataset->headings($columns),
            'rows' => $rows->map(fn ($r) => $dataset->row($r, $columns))->all(),
        ];
    }

    private function smartExcel(GenericExcelExport $export, string $filename): mixed
    {
        $estimatedRows = $export->query()->count();

        if ($estimatedRows > self::SYNC_ROW_LIMIT) {
            $this->exportService->queueExcel($export, $filename, auth('admin')->id());

            return response()->json([
                'message' => translate('Export queued. You will be notified when it\'s ready.'),
                'queued' => true,
            ]);
        }

        return $this->exportService->downloadExcel($export, $filename);
    }

    private function guessExportType(string $filename): string
    {
        foreach ($this->registry->all() as $key => $dataset) {
            if (str_starts_with($filename, $key . '-')) {
                return $key;
            }
        }

        return 'unknown';
    }

    private function guessExportFormat(string $filename): string
    {
        if (str_ends_with($filename, '.xlsx')) {
            return 'xlsx';
        }
        if (str_ends_with($filename, '.csv')) {
            return 'csv';
        }
        if (str_ends_with($filename, '.pdf')) {
            return 'pdf';
        }

        return 'unknown';
    }

    private function authorizeExports(): void
    {
        // Check the actual permission, not just authentication — defense-in-depth
        // in case an export route is ever added without the reports.export
        // middleware. (Super admins bypass the permission check.)
        $admin = auth('admin')->user();

        if (! $admin || ! $admin->hasPermission('reports.export')) {
            abort(403, translate('Unauthorized.'));
        }
    }
}

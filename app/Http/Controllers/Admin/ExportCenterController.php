<?php

namespace App\Http\Controllers\Admin;

use App\Exports\Admin\AffiliateCommissionsExport;
use App\Exports\Admin\AiUsageExport;
use App\Exports\Admin\RevenueExport;
use App\Exports\Admin\UsersExport;
use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use App\Models\Payment;
use App\Models\User;
use App\Services\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportCenterController extends Controller
{
    public function __construct(private ExportService $exportService) {}

    public function index(): \Inertia\Response
    {
        $disk = Storage::disk('local');
        $files = collect($disk->files('exports/' . auth('admin')->id()))
            ->map(function ($path) use ($disk) {
                return [
                    'path' => $path,
                    'filename' => basename($path),
                    'size' => $disk->size($path),
                    'modified' => $disk->lastModified($path),
                ];
            })
            ->sortByDesc('modified')
            ->values();

        return Inertia::render('Admin/Reports/ExportCenter', [
            'recentExports' => $files,
            'exportTypes' => [
                ['value' => 'users', 'label' => translate('Users')],
                ['value' => 'ai-usage', 'label' => translate('AI Usage')],
                ['value' => 'affiliates', 'label' => translate('Affiliate Commissions')],
            ],
            'isProAvailable' => (bool) $this->isProAvailable(),
        ]);
    }

    public function export(Request $request): JsonResponse|BinaryFileResponse|Response
    {
        $request->validate([
            'type' => 'required|in:users,ai-usage,revenue,affiliates',
            'format' => 'required|in:xlsx,csv,pdf',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $type = $request->type;
        $format = $request->format;
        $dateFrom = $request->date_from ?? now()->subDays(30)->toDateString();
        $dateTo = $request->date_to ?? now()->toDateString();
        $filename = $type . '-' . $dateFrom . '-to-' . $dateTo;

        if ($type === 'revenue' && ! $this->isProAvailable()) {
            return response()->json(['message' => translate('Revenue export is only available with Pro')], 422);
        }

        return match ([$type, $format]) {
            ['users', 'xlsx'] => $this->smartExcel(
                new UsersExport(
                    status: $request->status,
                    planId: $request->plan_id,
                    dateFrom: $dateFrom,
                    dateTo: $dateTo,
                ),
                $filename
            ),
            ['ai-usage', 'xlsx'] => $this->smartExcel(
                new AiUsageExport(
                    userId: $request->user_id,
                    toolSlug: $request->tool_slug,
                    provider: $request->provider,
                    dateFrom: $dateFrom,
                    dateTo: $dateTo,
                ),
                $filename
            ),
            ['revenue', 'xlsx'] => $this->smartExcel(
                new RevenueExport($dateFrom, $dateTo, $request->gateway, $request->status),
                $filename
            ),
            ['affiliates', 'xlsx'] => $this->exportService->downloadExcel(
                new AffiliateCommissionsExport,
                $filename
            ),

            ['users', 'csv'] => $this->csvUsers($filename, $dateFrom, $dateTo, $request),
            ['ai-usage', 'csv'] => $this->csvAiUsage($filename, $dateFrom, $dateTo, $request),
            ['revenue', 'csv'] => $this->csvRevenue($filename, $dateFrom, $dateTo, $request),
            ['affiliates', 'csv'] => $this->csvAffiliates($filename),

            ['users', 'pdf'] => $this->exportService->downloadPdf(
                'admin.reports.pdf.users',
                $this->getUsersPdfData($dateFrom, $dateTo, $request),
                $filename
            ),
            ['ai-usage', 'pdf'] => $this->exportService->downloadPdf(
                'admin.reports.pdf.ai-usage',
                $this->getAiUsagePdfData($dateFrom, $dateTo, $request),
                $filename
            ),
            ['revenue', 'pdf'] => $this->exportService->downloadPdf(
                'admin.reports.pdf.revenue',
                $this->getRevenuePdfData($dateFrom, $dateTo, $request),
                $filename
            ),

            default => response()->json(['message' => translate('Unsupported export type/format')], 422),
        };
    }

    public function download(string $file): BinaryFileResponse
    {
        $path = 'exports/' . auth('admin')->id() . '/' . $file;

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return response()->download(Storage::disk('local')->path($path));
    }

    public function deleteFile(string $file): JsonResponse
    {
        $path = 'exports/' . auth('admin')->id() . '/' . $file;

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }

        return response()->json(['message' => translate('File deleted')]);
    }

    private function smartExcel(object $exportClass, string $filename): mixed
    {
        $query = $exportClass->query();
        $estimatedRows = $query->count();

        if ($estimatedRows > 5000) {
            $this->exportService->queueExcel(
                $exportClass,
                $filename,
                auth('admin')->id()
            );

            return response()->json([
                'message' => translate('Export queued. You will be notified when it\'s ready.'),
                'queued' => true,
            ]);
        }

        return $this->exportService->downloadExcel($exportClass, $filename);
    }

    private function getUsersPdfData(string $dateFrom, string $dateTo, Request $request): array
    {
        $query = User::query()
            ->with('plan')
            ->when($request->status, fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->when($request->plan_id, fn ($q) => $q->where('plan_id', $request->plan_id))
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        $total = User::count();
        $rows = $query->latest()->limit(5000)->get();

        return [
            'appName' => settings('app_name', translate('Application')),
            'adminName' => auth('admin')->user()->name,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'rows' => $rows,
            'stats' => [
                'total' => $total,
                'new' => User::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
                'active' => User::where('is_active', true)->count(),
                'pro' => User::whereHas('plan')->count(),
            ],
        ];
    }

    private function getAiUsagePdfData(string $dateFrom, string $dateTo, Request $request): array
    {
        $query = AiUsageLog::query()
            ->with('user:id,name,email')
            ->when($request->tool_slug, fn ($q) => $q->where('tool_slug', $request->tool_slug))
            ->when($request->provider, fn ($q) => $q->where('provider', $request->provider))
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        $rows = $query->latest()->limit(5000)->get();

        return [
            'appName' => settings('app_name', translate('Application')),
            'adminName' => auth('admin')->user()->name,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'totalRows' => $rows->count(),
            'rows' => $rows,
            'stats' => [
                'total_requests' => $query->count(),
                'total_tokens' => $query->sum(\DB::raw('COALESCE(input_tokens, 0) + COALESCE(output_tokens, 0)')),
                'total_cost' => (float) $query->sum('cost_usd'),
                'unique_users' => $query->distinct('user_id')->count('user_id'),
            ],
        ];
    }

    private function getRevenuePdfData(string $dateFrom, string $dateTo, Request $request): array
    {
        $query = Payment::query()
            ->with('user:id,name,email', 'plan:id,name')
            ->where('status', 'completed')
            ->when($request->gateway, fn ($q) => $q->where('gateway', $request->gateway))
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        $rows = $query->latest()->limit(5000)->get();
        $revenue = (float) $rows->sum('amount');
        $count = $rows->count();

        return [
            'appName' => settings('app_name', translate('Application')),
            'adminName' => auth('admin')->user()->name,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'rows' => $rows,
            'stats' => [
                'total_revenue' => $revenue,
                'transaction_count' => $count,
                'avg_transaction' => $count > 0 ? $revenue / $count : 0,
                'total_refunds' => (float) Payment::where('status', 'refunded')
                    ->whereBetween('created_at', [$dateFrom, $dateTo])->sum('amount'),
            ],
        ];
    }

    private function csvUsers(string $filename, string $dateFrom, string $dateTo, Request $request): Response
    {
        $query = User::query()
            ->when($request->status, fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->when($request->plan_id, fn ($q) => $q->where('plan_id', $request->plan_id))
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        return $this->exportService->streamCsv(
            $filename,
            [translate('Name'), translate('Email'), translate('Active'), translate('Plan'), translate('Credits'), translate('Joined')],
            $query->lazy(),
            fn ($u) => [$u->name, $u->email, $u->is_active ? translate('Yes') : translate('No'), $u->plan?->name ?? translate('Free'), $u->credits, $u->created_at->format('Y-m-d')]
        );
    }

    private function csvAiUsage(string $filename, string $dateFrom, string $dateTo, Request $request): Response
    {
        $query = AiUsageLog::query()
            ->with('user:id,name,email')
            ->when($request->tool_slug, fn ($q) => $q->where('tool_slug', $request->tool_slug))
            ->when($request->provider, fn ($q) => $q->where('provider', $request->provider))
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        return $this->exportService->streamCsv(
            $filename,
            [translate('Date'), translate('User'), translate('Tool'), translate('Model'), translate('Provider'), translate('Input Tokens'), translate('Output Tokens'), translate('Cost (USD)'), translate('Credits Used'), translate('Status')],
            $query->lazy(),
            fn ($l) => [
                $l->created_at->format('Y-m-d H:i'),
                $l->user?->name ?? translate('Deleted'),
                $l->tool_slug,
                $l->model,
                $l->provider,
                $l->input_tokens,
                $l->output_tokens,
                $l->cost_usd,
                $l->credits_used,
                $l->status,
            ]
        );
    }

    private function csvRevenue(string $filename, string $dateFrom, string $dateTo, Request $request): Response
    {
        $query = Payment::query()
            ->with('user:id,name,email')
            ->when($request->gateway, fn ($q) => $q->where('gateway', $request->gateway))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        return $this->exportService->streamCsv(
            $filename,
            [translate('Date'), translate('Transaction ID'), translate('User'), translate('Amount'), translate('Currency'), translate('Gateway'), translate('Status')],
            $query->lazy(),
            fn ($p) => [
                $p->created_at->format('Y-m-d H:i'),
                $p->ulid,
                $p->user?->name ?? translate('N/A'),
                $p->amount,
                $p->currency,
                $p->gateway,
                $p->status,
            ]
        );
    }

    private function csvAffiliates(string $filename): Response
    {
        return $this->exportService->streamCsv(
            $filename,
            [translate('Date'), translate('Referrer'), translate('Referred User'), translate('Amount'), translate('Status'), translate('Approved At'), translate('Paid At')],
            \App\Models\AffiliateCommission::with('referrer:id,name,email', 'referred:id,name,email')->lazy(),
            fn ($c) => [
                $c->created_at->format('Y-m-d'),
                $c->referrer?->name . ' (' . $c->referrer?->email . ')',
                $c->referred?->name,
                $c->amount,
                $c->status,
                $c->approved_at?->format('Y-m-d') ?? translate('N/A'),
                $c->paid_at?->format('Y-m-d') ?? translate('N/A'),
            ]
        );
    }

    private function isProAvailable(): bool
    {
        return (bool) (settings('is_pro_available', true) ?? true);
    }
}

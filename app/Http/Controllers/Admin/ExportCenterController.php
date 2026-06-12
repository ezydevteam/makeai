<?php

namespace App\Http\Controllers\Admin;

use App\Exports\Admin\AffiliateCommissionsExport;
use App\Exports\Admin\AiUsageExport;
use App\Exports\Admin\RevenueExport;
use App\Exports\Admin\UsersExport;
use App\Http\Controllers\Controller;
use App\Models\AiTool;
use App\Models\AiUsageLog;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;
use App\Services\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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

        return Inertia::render('Admin/Reports/ExportCenter', [
            'recentExports' => $files,
            'exportTypes' => [
                ['value' => 'users', 'label' => translate('Users')],
                ['value' => 'ai-usage', 'label' => translate('AI Usage')],
                ['value' => 'affiliates', 'label' => translate('Affiliate Commissions')],
            ],
            'isProAvailable' => (bool) $this->isProAvailable(),
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
        ]);
    }

    public function export(Request $request): JsonResponse|BinaryFileResponse|Response
    {
        $request->validate([
            'type' => 'required|in:users,ai-usage,revenue,affiliates',
            'format' => 'required|in:xlsx,csv,pdf',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'provider' => 'nullable|array',
            'provider.*' => 'string',
            'gateway' => 'nullable|array',
            'gateway.*' => 'string',
            'tool_slug' => 'nullable|array',
            'tool_slug.*' => 'string',
        ]);

        $type = $request->type;
        $format = $request->format;
        $dateFrom = $request->date_from ?? now()->subDays(30)->toDateString();
        $dateTo = $request->date_to ?? now()->toDateString();
        $filename = $type . '-' . $dateFrom . '-to-' . $dateTo;
        $provider = $request->provider;
        $gateway = $request->gateway;
        $toolSlug = $request->tool_slug;

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
                    toolSlug: $toolSlug,
                    provider: $provider,
                    dateFrom: $dateFrom,
                    dateTo: $dateTo,
                ),
                $filename
            ),
            ['revenue', 'xlsx'] => $this->smartExcel(
                new RevenueExport($dateFrom, $dateTo, $gateway, $request->status),
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
            ['affiliates', 'pdf'] => $this->exportService->downloadPdf(
                'admin.reports.pdf.affiliates',
                $this->getAffiliatesPdfData($dateFrom, $dateTo),
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
        if (! method_exists($exportClass, 'query')) {
            return $this->exportService->downloadExcel($exportClass, $filename);
        }

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
            ->when($request->tool_slug, fn ($q) => $q->whereIn('tool_slug', (array) $request->tool_slug))
            ->when($request->provider, fn ($q) => $q->whereIn('provider', (array) $request->provider))
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
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->gateway, fn ($q) => $q->whereIn('gateway', (array) $request->gateway))
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

    private function getAffiliatesPdfData(string $dateFrom, string $dateTo): array
    {
        $query = \App\Models\AffiliateCommission::query()
            ->with('referrer:id,name,email', 'referred:id,name,email')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        $rows = $query->latest()->limit(5000)->get();
        $total = (float) $rows->sum('amount');
        $count = $rows->count();

        return [
            'appName' => settings('app_name', translate('Application')),
            'adminName' => auth('admin')->user()->name,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'rows' => $rows,
            'stats' => [
                'total_commissions' => $total,
                'transaction_count' => $count,
                'unique_referrers' => $rows->pluck('referrer_id')->unique()->count(),
                'avg_commission' => $count > 0 ? $total / $count : 0,
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
            ->when($request->tool_slug, fn ($q) => $q->whereIn('tool_slug', (array) $request->tool_slug))
            ->when($request->provider, fn ($q) => $q->whereIn('provider', (array) $request->provider))
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
            ->when($request->gateway, fn ($q) => $q->whereIn('gateway', (array) $request->gateway))
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

    public function estimate(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:users,ai-usage,revenue,affiliates',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $from = $request->date_from ?? now()->subDays(30)->toDateString();
        $to = $request->date_to ?? now()->toDateString();

        $count = match ($request->type) {
            'users' => User::query()
                ->when($request->status, fn ($q) => $q->where('is_active', $request->status === 'active'))
                ->when($request->plan_id, fn ($q) => $q->where('plan_id', $request->plan_id))
                ->whereBetween('created_at', [$from, $to])->count(),
            'ai-usage' => AiUsageLog::query()
                ->when($request->tool_slug, fn ($q) => $q->whereIn('tool_slug', (array) $request->tool_slug))
                ->when($request->provider, fn ($q) => $q->whereIn('provider', (array) $request->provider))
                ->whereBetween('created_at', [$from, $to])->count(),
            'revenue' => Payment::query()
                ->when($request->status, fn ($q) => $q->where('status', $request->status))
                ->when($request->gateway, fn ($q) => $q->whereIn('gateway', (array) $request->gateway))
                ->whereBetween('created_at', [$from, $to])->count(),
            'affiliates' => \App\Models\AffiliateCommission::query()
                ->whereBetween('created_at', [$from, $to])->count(),
            default => 0,
        };

        return response()->json(['count' => $count]);
    }

    private function isProAvailable(): bool
    {
        return (bool) (settings('is_pro_available', true) ?? true);
    }

    private function guessExportType(string $filename): string
    {
        foreach (['users', 'ai-usage', 'revenue', 'affiliates'] as $type) {
            if (str_starts_with($filename, $type . '-')) {
                return $type;
            }
        }
        return 'unknown';
    }

    private function guessExportFormat(string $filename): string
    {
        if (str_ends_with($filename, '.xlsx')) return 'xlsx';
        if (str_ends_with($filename, '.csv')) return 'csv';
        if (str_ends_with($filename, '.pdf')) return 'pdf';
        return 'unknown';
    }
}

<?php

namespace App\Http\Controllers\Admin\AI;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AiUsageLogController extends Controller
{
    /**
     * Display a listing of AI usage logs.
     */
    public function index(Request $request)
    {
        $query = AiUsageLog::with('user:id,name,email')
            ->orderByDesc('created_at');

        if ($request->filled('provider')) {
            $query->where('provider', $request->provider);
        }

        if ($request->filled('tool_slug')) {
            $query->where('tool_slug', $request->tool_slug);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('model', 'like', "%{$search}%")
                    ->orWhere('tool_slug', 'like', "%{$search}%")
                    ->orWhere('provider', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Get distinct providers for filter dropdown
        $providers = AiUsageLog::distinct()->pluck('provider')->filter()->values()->toArray();

        $now = now();
        $sevenDaysAgo = $now->copy()->subDays(7);
        $fourteenDaysAgo = $now->copy()->subDays(14);

        // 1. Total Requests
        $requestsCurrent = AiUsageLog::where('created_at', '>=', $sevenDaysAgo)->count();
        $requestsPrevious = AiUsageLog::whereBetween('created_at', [$fourteenDaysAgo, $sevenDaysAgo])->count();

        // 2. Credits Used
        $creditsCurrent = (float) AiUsageLog::where('created_at', '>=', $sevenDaysAgo)->sum('credits_used');
        $creditsPrevious = (float) AiUsageLog::whereBetween('created_at', [$fourteenDaysAgo, $sevenDaysAgo])->sum('credits_used');

        // 3. Estimated Cost
        $costCurrent = (float) AiUsageLog::where('created_at', '>=', $sevenDaysAgo)->sum('cost_usd');
        $costPrevious = (float) AiUsageLog::whereBetween('created_at', [$fourteenDaysAgo, $sevenDaysAgo])->sum('cost_usd');

        // 4. Failed Requests
        $failedCurrent = AiUsageLog::where('status', 'failed')->where('created_at', '>=', $sevenDaysAgo)->count();
        $failedPrevious = AiUsageLog::where('status', 'failed')->whereBetween('created_at', [$fourteenDaysAgo, $sevenDaysAgo])->count();

        $stats = [
            'total_requests' => [
                'value' => number_format($requestsCurrent),
                'comparison' => $this->calculateComparison($requestsCurrent, $requestsPrevious),
            ],
            'credits_used' => [
                'value' => number_format($creditsCurrent, 2),
                'comparison' => $this->calculateComparison($creditsCurrent, $creditsPrevious),
            ],
            'estimated_cost' => [
                'value' => '$' . number_format($costCurrent, 4),
                'comparison' => $this->calculateComparison($costCurrent, $costPrevious),
            ],
            'failed_requests' => [
                'value' => number_format($failedCurrent),
                'comparison' => $this->calculateComparison($failedCurrent, $failedPrevious),
            ],
        ];

        return Inertia::render('Admin/AI/Logs', [
            'logs' => $query->paginate(30)->withQueryString(),
            'filters' => $request->only(['provider', 'tool_slug', 'status', 'search', 'date_from', 'date_to']),
            'providers' => $providers,
            'stats' => $stats,
        ]);
    }

    private function calculateComparison(float|int $current, float|int $previous): array
    {
        if ($previous == 0) {
            return [
                'label' => $current == 0 ? '0%' : '+100%',
                'type' => $current == 0 ? 'neutral' : 'up',
            ];
        }

        $delta = (($current - $previous) / $previous) * 100;
        $rounded = (int) round(abs($delta));

        if ($rounded === 0) {
            return [
                'label' => '0%',
                'type' => 'neutral',
            ];
        }

        return [
            'label' => ($delta > 0 ? '+' : '-') . $rounded . '%',
            'type' => $delta > 0 ? 'up' : 'down',
        ];
    }
}

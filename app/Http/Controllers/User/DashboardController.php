<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AiTool;
use App\Models\AiUsageLog;
use App\Models\CreditTransaction;
use App\Models\Document;
use App\Models\GenerationHistory;
use App\Models\LoginHistory;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $period = $request->query('chart_period', '7d');
        $showOnboarding = (bool) settings('onboarding_enabled', true)
            && $request->session()->pull('show_onboarding', ! $user->onboarding_completed_at);

        return Inertia::render('User/Dashboard', [
            'stats' => $this->stats($user),
            'usageChart' => $this->usageChart($user, $period),
            'chartPeriod' => $period,
            'recentTransactions' => $this->recentTransactions($user),
            'quickTools' => $this->quickTools(),
            'recentGenerations' => $this->recentGenerations($user),
            'recentDocuments' => $this->recentDocuments($user),
            'recentLoginHistory' => $this->recentLoginHistory($user),
            'plan' => $this->planData($user),
            'referral' => $this->referralData($user),
            'showOnboarding' => $showOnboarding,
        ]);
    }

    public function chartData(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $period = $request->query('period', '7d');

        return response()->json([
            'chart' => $this->usageChart($user, $period),
        ]);
    }

    private function stats(User $user): array
    {
        return [
            'credits' => (float) $user->credits,
            'credits_used_month' => (float) $user->credits_used_month,
            // Generations, not conversations: the conversation count came from the AI Chatbot
            // ADDON, so on any install without it the card read a permanent 0 and its panel
            // rendered an empty box. This counts core AI usage, which every install has.
            'total_generations' => AiUsageLog::where('user_id', $user->id)->count(),
            'total_documents' => $user->documents()->count(),
            'total_open_support_tickets' => SupportTicket::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['open', 'in_progress'])
                ->count(),
            'lifetime_credits_used' => (float) $user->creditTransactions()
                ->where('type', 'usage')
                ->sum(DB::raw('ABS(amount)')),
        ];
    }

    private function usageChart(User $user, string $period = '7d'): array
    {
        $days = ($period === 'month')
            ? max(1, (int) now()->day)
            : ($period === '90d' ? 90 : 7);

        return collect(range($days - 1, 0))->map(function ($daysAgo) use ($user) {
            $date = now()->subDays($daysAgo)->toDateString();

            return [
                'date' => $date,
                'credits' => (float) $user->creditTransactions()
                    ->where('type', 'usage')
                    ->whereDate('created_at', $date)
                    ->sum(DB::raw('ABS(amount)')),
            ];
        })->values()->all();
    }

    private function recentTransactions(User $user): array
    {
        return $user->creditTransactions()
            ->latest()
            ->take(8)
            ->get()
            ->map(fn (CreditTransaction $tx) => [
                'id' => $tx->id,
                'amount' => (float) $tx->amount,
                'balance_after' => (float) $tx->balance_after,
                'type' => $tx->type,
                'description' => $tx->description,
                'created_at' => $tx->created_at->toISOString(),
            ])
            ->values()
            ->all();
    }

    private function quickTools(): array
    {
        return AiTool::where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('is_featured')
            ->take(6)
            ->get()
            ->map(fn (AiTool $tool) => [
                'name' => $tool->name,
                'slug' => $tool->slug,
                'description' => $tool->description,
                'icon' => $tool->icon,
                'color' => $tool->color,
                'requires_pro' => $tool->isProRequired(),
                'is_new' => $tool->created_at && $tool->created_at->gt(now()->subDays(30)),
            ])
            ->values()
            ->all();
    }

    /**
     * The 5 most recent generations, replacing the old "Recent conversations" panel.
     *
     * That panel read from the AI Chatbot ADDON, so on an install without it the dashboard
     * carried a permanently empty box. Generation history is core, so this always has
     * something to show.
     */
    private function recentGenerations(User $user): array
    {
        $history = GenerationHistory::where('user_id', $user->id)
            ->latest('created_at')
            ->take(5)
            ->get(['ulid', 'tool_slug', 'output_preview', 'created_at']);

        // Names resolved in one query for the page rather than per row.
        $toolNames = AiTool::whereIn('slug', $history->pluck('tool_slug')->filter()->unique())
            ->pluck('name', 'slug');

        return $history
            ->map(fn (GenerationHistory $item) => [
                'ulid' => $item->ulid,
                'tool_slug' => $item->tool_slug,
                'tool_name' => $toolNames[$item->tool_slug] ?? $item->tool_slug ?? translate('Direct'),
                'output_preview' => $item->output_preview,
                'created_at' => optional($item->created_at)->toISOString(),
            ])
            ->values()
            ->all();
    }

    private function recentDocuments(User $user): array
    {
        return $user->documents()
            ->latest()
            ->take(3)
            ->get()
            ->map(fn (Document $document) => [
                'id' => $document->id,
                'title' => $document->title,
                'tool_slug' => $document->tool_slug,
                'word_count' => $document->word_count,
                'created_at' => $document->created_at->toISOString(),
            ])
            ->values()
            ->all();
    }

    private function recentLoginHistory(User $user): array
    {
        return $user->loginHistory()
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (LoginHistory $login) => [
                'id' => $login->id,
                'ip' => $login->ip,
                'country' => $login->country,
                'city' => $login->city,
                'success' => (bool) $login->success,
                'created_at' => optional($login->created_at)->toISOString(),
            ])
            ->values()
            ->all();
    }

    private function planData(User $user): ?array
    {
        // Plans/subscriptions are an Extended-license feature. Under Regular
        // license (or Extended with subscriptions off) there is no plan concept,
        // so send nothing — the dashboard hides the plan card accordingly.
        if (! isProAvailable()) {
            return null;
        }

        $plan = $user->plan;

        if (! $plan) {
            return null;
        }

        return [
            'name' => $plan->name,
            'slug' => $plan->slug,
            'is_free' => (bool) $plan->is_free,
            'features' => $this->normalizePlanFeatures($plan->features),
            'subscription_status' => $user->subscription_status,
            'subscription_ends_at' => optional($user->subscription_ends_at)->toISOString(),
            'trial_ends_at' => optional($user->trial_ends_at)->toISOString(),
        ];
    }

    private function normalizePlanFeatures(mixed $features): array
    {
        if (is_array($features)) {
            return array_values(array_filter(array_map(
                static fn (mixed $feature): string => trim((string) $feature),
                $features
            )));
        }

        if (is_string($features)) {
            $decoded = json_decode($features, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter(array_map(
                    static fn (mixed $feature): string => trim((string) $feature),
                    $decoded
                )));
            }

            return array_values(array_filter(array_map(
                static fn (string $feature): string => trim($feature),
                preg_split('/[\r\n,]+/', $features) ?: []
            )));
        }

        return [];
    }

    private function referralData(User $user): array
    {
        return [
            'code' => $user->referral_code,
            'earnings' => (float) $user->referral_earnings,
            'count' => (int) $user->referral_count,
            'link' => $user->referral_code ? url('/ref/'.$user->referral_code) : null,
        ];
    }
}

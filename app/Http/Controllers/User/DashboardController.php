<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AiTool;
use App\Models\Conversation;
use App\Models\CreditTransaction;
use App\Models\Document;
use App\Models\Plan;
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
        $showOnboarding = $request->session()->pull('show_onboarding', ! $user->onboarding_completed_at);

        return Inertia::render('User/Dashboard', [
            'stats' => $this->stats($user),
            'usageChart' => $this->usageChart($user, $period),
            'chartPeriod' => $period,
            'recentTransactions' => $this->recentTransactions($user),
            'quickTools' => $this->quickTools(),
            'recentConversations' => $this->recentConversations($user),
            'recentDocuments' => $this->recentDocuments($user),
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
            'credits_used_today' => (float) $user->credits_used_today,
            'credits_used_month' => (float) $user->credits_used_month,
            'total_conversations' => $user->conversations()->count(),
            'total_documents' => $user->documents()->count(),
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
                'requires_pro' => (bool) $tool->requires_pro,
                'is_new' => $tool->created_at && $tool->created_at->gt(now()->subDays(30)),
            ])
            ->values()
            ->all();
    }

    private function recentConversations(User $user): array
    {
        return $user->conversations()
            ->latest('last_message_at')
            ->take(3)
            ->get()
            ->map(fn (Conversation $conversation) => [
                'id' => $conversation->id,
                'ulid' => $conversation->ulid,
                'title' => $conversation->title ?: 'Untitled',
                'model' => $conversation->model,
                'message_count' => $conversation->message_count,
                'last_message_at' => optional($conversation->last_message_at)->toISOString(),
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

    private function planData(User $user): ?array
    {
        $plan = $user->plan;

        if (! $plan) {
            return null;
        }

        return [
            'name' => $plan->name,
            'slug' => $plan->slug,
            'is_free' => (bool) $plan->is_free,
            'features' => $plan->features,
            'subscription_status' => $user->subscription_status,
            'subscription_ends_at' => optional($user->subscription_ends_at)->toISOString(),
            'trial_ends_at' => optional($user->trial_ends_at)->toISOString(),
            'daily_limit' => $user->daily_limit ? (float) $user->daily_limit : null,
            'monthly_limit' => $user->monthly_limit ? (float) $user->monthly_limit : null,
        ];
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

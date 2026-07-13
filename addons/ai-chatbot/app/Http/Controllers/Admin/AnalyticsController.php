<?php

declare(strict_types=1);

namespace Addons\AiChatbot\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Addons\AiChatbot\Models\Conversation;
use Addons\AiChatbot\Models\ConversationMessage;
use Addons\AiChatbot\Models\ChatMessageFeedback;
use Addons\AiChatbot\Models\ChatbotMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        $now = now();
        // True week-over-week: compare the last 7 days against the 7 days before that,
        // instead of "everything before 7 days ago" (which made the badge a cumulative
        // growth rate mislabeled as "vs last week").
        $currentWindowStart = $now->copy()->subDays(7);
        $previousWindowStart = $now->copy()->subDays(14);

        // 1. Total Conversations (value = all-time; comparison = this week vs last week)
        $totalConversationsCurrent = Conversation::count();
        $conversationsThisWeek = Conversation::where('created_at', '>=', $currentWindowStart)->count();
        $conversationsLastWeek = Conversation::whereBetween('created_at', [$previousWindowStart, $currentWindowStart])->count();

        // 2. Total Messages
        $totalMessagesCurrent = ConversationMessage::count();
        $messagesThisWeek = ConversationMessage::where('created_at', '>=', $currentWindowStart)->count();
        $messagesLastWeek = ConversationMessage::whereBetween('created_at', [$previousWindowStart, $currentWindowStart])->count();

        // 3. Tokens & Credits — sourced from the `conversations` table, which is the only
        // place these are actually persisted (ConversationMessage.input_tokens/
        // output_tokens/credits_charged were historically left at 0 for assistant rows,
        // so summing the message columns always yielded 0). Aggregating the conversation
        // rollups gives the correct all-time totals AND correct historical figures.
        $tokenMetricsAllTime = Conversation::select(
            DB::raw('SUM(total_tokens) as total_tokens'),
            DB::raw('SUM(total_credits) as total_credits')
        )->first();
        $totalTokensCurrent = (int) ($tokenMetricsAllTime->total_tokens ?? 0);
        $totalCreditsCurrent = (float) ($tokenMetricsAllTime->total_credits ?? 0);

        // Week-over-week windows for tokens/credits (by conversation activity date).
        $tokenMetricsThisWeek = Conversation::where('created_at', '>=', $currentWindowStart)
            ->select(
                DB::raw('SUM(total_tokens) as total_tokens'),
                DB::raw('SUM(total_credits) as total_credits')
            )->first();
        $tokenMetricsLastWeek = Conversation::whereBetween('created_at', [$previousWindowStart, $currentWindowStart])
            ->select(
                DB::raw('SUM(total_tokens) as total_tokens'),
                DB::raw('SUM(total_credits) as total_credits')
            )->first();
        $tokensThisWeek = (int) ($tokenMetricsThisWeek->total_tokens ?? 0);
        $tokensLastWeek = (int) ($tokenMetricsLastWeek->total_tokens ?? 0);
        $creditsThisWeek = (float) ($tokenMetricsThisWeek->total_credits ?? 0);
        $creditsLastWeek = (float) ($tokenMetricsLastWeek->total_credits ?? 0);

        // Feedback summary
        $likesCount = ChatMessageFeedback::where('rating', 1)->count();
        $dislikesCount = ChatMessageFeedback::where('rating', -1)->count();
        $recentFeedbacks = ChatMessageFeedback::with(['user:id,name,email', 'conversation:id,ulid,title'])
            ->latest()
            ->limit(10)
            ->get();

        // 30 days trends
        $daysLimit = Carbon::now()->subDays(30);

        $dailyConversations = Conversation::where('created_at', '>=', $daysLimit)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date');

        $dailyMessages = ConversationMessage::where('created_at', '>=', $daysLimit)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date');

        // Build list of dates for frontend
        $trends = [];
        for ($i = 29; $i >= 0; $i--) {
            $dateStr = Carbon::now()->subDays($i)->format('Y-m-d');
            $trends[] = [
                'date' => Carbon::parse($dateStr)->format('M d'),
                'conversations' => $dailyConversations->get($dateStr, 0),
                'messages' => $dailyMessages->get($dateStr, 0),
            ];
        }

        // Models popularity
        $friendlyModelNames = config('ai.model_names', []);
        $friendlyProviderNames = config('ai.provider_names', []);

        $rawModels = ConversationMessage::select('model', DB::raw('COUNT(*) as count'))
            ->whereNotNull('model')
            ->groupBy('model')
            ->orderByDesc('count')
            ->get();

        // Resolve provider slugs in a single query instead of one lookup per model (N+1).
        $providerBySlug = \App\Models\AiModel::whereIn('slug', $rawModels->pluck('model')->all())
            ->pluck('provider', 'slug');

        $modelsPopularity = $rawModels->map(function ($row) use ($friendlyModelNames, $friendlyProviderNames, $providerBySlug) {
            $slug = $row->model;
            $providerSlug = $providerBySlug[$slug] ?? 'unknown';

            return [
                'model' => $slug,
                'name' => $friendlyModelNames[$slug] ?? $slug,
                'provider' => $friendlyProviderNames[$providerSlug] ?? ucfirst($providerSlug),
                'messages_count' => $row->count,
            ];
        });

        // Modes popularity. A null mode_slug and a literal 'general' slug both represent
        // the default mode, so collapse them into one bucket AFTER counting — otherwise
        // the list can show two separate "General" rows. Re-sort once merged.
        $modeNames = ChatbotMode::pluck('name', 'slug')->toArray();

        $rawModes = Conversation::select('mode_slug', DB::raw('COUNT(*) as count'))
            ->groupBy('mode_slug')
            ->get();

        $modesPopularity = $rawModes
            ->groupBy(fn ($row) => $row->mode_slug ?? 'general')
            ->map(function ($rows, $slug) use ($modeNames) {
                return [
                    'slug' => $slug,
                    'name' => $modeNames[$slug] ?? ucwords(str_replace(['-', '_'], ' ', $slug)),
                    'conversations_count' => (int) $rows->sum('count'),
                ];
            })
            ->sortByDesc('conversations_count')
            ->values();

        return Inertia::render('Addons/ai-chatbot/Admin/Analytics', [
            'stats' => [
                'total_conversations' => [
                    'value' => $totalConversationsCurrent,
                    'comparison' => $this->calculateComparison($conversationsThisWeek, $conversationsLastWeek),
                ],
                'total_messages' => [
                    'value' => $totalMessagesCurrent,
                    'comparison' => $this->calculateComparison($messagesThisWeek, $messagesLastWeek),
                ],
                'total_tokens' => [
                    'value' => $totalTokensCurrent,
                    'comparison' => $this->calculateComparison($tokensThisWeek, $tokensLastWeek),
                ],
                'total_credits' => [
                    'value' => $totalCreditsCurrent,
                    'comparison' => $this->calculateComparison($creditsThisWeek, $creditsLastWeek),
                ],
                'likes_count' => $likesCount,
                'dislikes_count' => $dislikesCount,
            ],
            'trends' => $trends,
            'modelsPopularity' => $modelsPopularity,
            'modesPopularity' => $modesPopularity,
            'recentFeedbacks' => $recentFeedbacks,
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

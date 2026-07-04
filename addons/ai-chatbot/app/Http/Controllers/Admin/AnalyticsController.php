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
        $sevenDaysAgo = $now->copy()->subDays(7);

        // 1. Total Conversations
        $totalConversationsCurrent = Conversation::count();
        $totalConversationsPrevious = Conversation::where('created_at', '<', $sevenDaysAgo)->count();

        // 2. Total Messages
        $totalMessagesCurrent = ConversationMessage::count();
        $totalMessagesPrevious = ConversationMessage::where('created_at', '<', $sevenDaysAgo)->count();

        // 3. Tokens & Credits Current
        $tokenMetricsCurrent = ConversationMessage::select(
            DB::raw('SUM(input_tokens + output_tokens) as total_tokens'),
            DB::raw('SUM(credits_charged) as total_credits')
        )->first();
        $totalTokensCurrent = (int) ($tokenMetricsCurrent->total_tokens ?? 0);
        $totalCreditsCurrent = (float) ($tokenMetricsCurrent->total_credits ?? 0);

        // 4. Tokens & Credits Previous
        $tokenMetricsPrevious = ConversationMessage::where('created_at', '<', $sevenDaysAgo)
            ->select(
                DB::raw('SUM(input_tokens + output_tokens) as total_tokens'),
                DB::raw('SUM(credits_charged) as total_credits')
            )->first();
        $totalTokensPrevious = (int) ($tokenMetricsPrevious->total_tokens ?? 0);
        $totalCreditsPrevious = (float) ($tokenMetricsPrevious->total_credits ?? 0);

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

        $modelsPopularity = $rawModels->map(function ($row) use ($friendlyModelNames, $friendlyProviderNames) {
            $slug = $row->model;
            $dbModel = \App\Models\AiModel::where('slug', $slug)->first();
            $providerSlug = $dbModel?->provider ?? 'unknown';
            
            return [
                'model' => $slug,
                'name' => $friendlyModelNames[$slug] ?? $slug,
                'provider' => $friendlyProviderNames[$providerSlug] ?? ucfirst($providerSlug),
                'messages_count' => $row->count,
            ];
        });

        // Modes popularity
        $rawModes = Conversation::select('mode_slug', DB::raw('COUNT(*) as count'))
            ->groupBy('mode_slug')
            ->orderByDesc('count')
            ->get();

        $modeNames = ChatbotMode::pluck('name', 'slug')->toArray();

        $modesPopularity = $rawModes->map(function ($row) use ($modeNames) {
            $slug = $row->mode_slug ?? 'general';
            return [
                'slug' => $slug,
                'name' => $modeNames[$slug] ?? ucwords(str_replace(['-', '_'], ' ', $slug)),
                'conversations_count' => $row->count,
            ];
        });

        return Inertia::render('Addons/ai-chatbot/Admin/Analytics', [
            'stats' => [
                'total_conversations' => [
                    'value' => $totalConversationsCurrent,
                    'comparison' => $this->calculateComparison($totalConversationsCurrent, $totalConversationsPrevious),
                ],
                'total_messages' => [
                    'value' => $totalMessagesCurrent,
                    'comparison' => $this->calculateComparison($totalMessagesCurrent, $totalMessagesPrevious),
                ],
                'total_tokens' => [
                    'value' => $totalTokensCurrent,
                    'comparison' => $this->calculateComparison($totalTokensCurrent, $totalTokensPrevious),
                ],
                'total_credits' => [
                    'value' => $totalCreditsCurrent,
                    'comparison' => $this->calculateComparison($totalCreditsCurrent, $totalCreditsPrevious),
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

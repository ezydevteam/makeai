<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateReferral;
use App\Models\AiUsageLog;
use App\Models\Comment;
use App\Models\LoginHistory;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\SupportTicket;
use App\Models\User;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();
        $today = $now->toDateString();

        // ─── 1. User stats ───
        $totalUsers = User::count();
        $newUsersToday = User::whereDate('created_at', $today)->count();
        $newUsersThisMonth = User::where('created_at', '>=', $monthStart)->count();
        $newUsersLastMonth = User::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();
        $activeUsers = User::where('is_active', true)->count();
        $bannedUsers = User::where('is_banned', true)->count();

        // ─── 2. Revenue stats ───
        $totalRevenue = Payment::where('status', 'completed')->sum('amount');
        $revenueToday = Payment::where('status', 'completed')->whereDate('created_at', $today)->sum('amount');
        $revenueThisMonth = Payment::where('status', 'completed')->where('created_at', '>=', $monthStart)->sum('amount');
        $revenueLastMonth = Payment::where('status', 'completed')->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->sum('amount');
        $mrr = Payment::where('status', 'completed')->where('type', 'subscription')->where('created_at', '>=', $monthStart)->sum('amount');

        // ─── 3. AI Usage stats ───
        $totalAiRequests = AiUsageLog::count();
        $aiRequestsToday = AiUsageLog::whereDate('created_at', $today)->count();
        $totalCreditsUsed = AiUsageLog::sum('credits_used');
        $creditsUsedToday = AiUsageLog::whereDate('created_at', $today)->sum('credits_used');
        $creditsUsedThisMonth = AiUsageLog::where('created_at', '>=', $monthStart)->sum('credits_used');
        $totalCost = AiUsageLog::sum('cost_usd');
        $costToday = AiUsageLog::whereDate('created_at', $today)->sum('cost_usd');
        $tokensUsedToday = AiUsageLog::whereDate('created_at', $today)->sum(\DB::raw('input_tokens + output_tokens'));

        // ─── 4. Subscription stats ───
        $activeSubscriptions = User::where('subscription_status', 'active')->count();
        $trialingSubscriptions = User::where('subscription_status', 'trialing')->count();
        $pastDueSubscriptions = User::where('subscription_status', 'past_due')->count();
        $activePlans = Plan::where('is_active', true)->count();

        // ─── 5. Ticket & comment stats ───
        $openTickets = SupportTicket::whereIn('status', ['open', 'in_progress'])->count();
        $pendingComments = Comment::where('status', 'pending')->count();

        // ─── 0. Earliest data point for lifetime charts ───
        $earliestUser = User::min('created_at') ?? $now->copy()->subYear();
        $lifetimeStart = now()->parse($earliestUser)->startOfMonth();

        // ─── 6. Chart: User signups (today / 7d / 30d / 90d / lifetime) ───
        $signupsChart = $this->timeSeries(function ($date, $hour, $endDate = null) {
            if ($endDate !== null) {
                return User::whereBetween('created_at', [$date, $endDate])->count();
            }
            if ($hour !== null) {
                return User::whereDate('created_at', $date)->whereRaw('HOUR(created_at) = ?', [$hour])->count();
            }
            return User::whereDate('created_at', $date)->count();
        }, $now, $lifetimeStart);

        // ─── 7. Chart: Revenue (today / 7d / 30d / 90d / lifetime) ───
        $revenueChart = $this->timeSeries(function ($date, $hour, $endDate = null) {
            $q = Payment::where('status', 'completed');
            if ($endDate !== null) {
                $q->whereBetween('created_at', [$date, $endDate]);
            } else {
                $q->whereDate('created_at', $date);
                if ($hour !== null) { $q->whereRaw('HOUR(created_at) = ?', [$hour]); }
            }
            return (float) $q->sum('amount');
        }, $now, $lifetimeStart);

        // ─── 8. Chart: AI usage by tool (top 8 tools, 30 days) ───
        $aiByTool = AiUsageLog::where('created_at', '>=', $now->copy()->subDays(30))
            ->whereNotNull('tool_slug')
            ->selectRaw('tool_slug, SUM(credits_used) as total_credits')
            ->groupBy('tool_slug')
            ->orderByDesc('total_credits')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->tool_slug,
                'credits' => (float) $row->total_credits,
            ])
            ->values()
            ->toArray();

        // ─── 9. Chart: Token cost by provider (30 days) ───
        $costByProvider = AiUsageLog::where('created_at', '>=', $now->copy()->subDays(30))
            ->selectRaw('provider, SUM(cost_usd) as total_cost')
            ->groupBy('provider')
            ->orderByDesc('total_cost')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->provider,
                'cost' => round((float) $row->total_cost, 4),
            ])
            ->values()
            ->toArray();

        // ─── 10. Chart: Revenue vs Cost (today / 7d / 30d / 90d / lifetime) ───
        $revenueVsCost = $this->timeSeriesDual(
            function ($date, $hour, $endDate = null) {
                $q = Payment::where('status', 'completed');
                if ($endDate !== null) {
                    $q->whereBetween('created_at', [$date, $endDate]);
                } else {
                    $q->whereDate('created_at', $date);
                    if ($hour !== null) { $q->whereRaw('HOUR(created_at) = ?', [$hour]); }
                }
                return (float) $q->sum('amount');
            },
            function ($date, $hour, $endDate = null) {
                $q = AiUsageLog::query();
                if ($endDate !== null) {
                    $q->whereBetween('created_at', [$date, $endDate]);
                } else {
                    $q->whereDate('created_at', $date);
                    if ($hour !== null) { $q->whereRaw('HOUR(created_at) = ?', [$hour]); }
                }
                return round((float) $q->sum('cost_usd'), 4);
            },
            $now,
            $lifetimeStart,
        );

        // ─── 11. Chart: Pro subscriptions (today / 7d / 30d / 90d / lifetime) ───
        $proSubs = $this->timeSeries(function ($date, $hour, $endDate = null) use ($now) {
            if ($endDate !== null) {
                return User::where('subscription_status', 'active')
                    ->where('created_at', '<=', $endDate)
                    ->whereDate('subscription_ends_at', '>=', $date)
                    ->count();
            }
            return User::where('subscription_status', 'active')
                ->whereDate('created_at', '<=', $date)
                ->whereDate('subscription_ends_at', '>=', $date)
                ->count();
        }, $now, $lifetimeStart);

        // ─── 12. Chart: Geo usage by country (LoginHistory, 90 days) ───
        $geoUsage = LoginHistory::where('created_at', '>=', $now->copy()->subDays(90))
            ->where('success', true)
            ->whereNotNull('country')
            ->selectRaw('country, COUNT(*) as logins')
            ->groupBy('country')
            ->orderByDesc('logins')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'country' => $row->country,
                'logins' => $row->logins,
            ])
            ->values()
            ->toArray();

        // ─── 13. Lists: top AI tools (by usage count, cost, tokens) ───
        $topToolsByUsage = AiUsageLog::whereNotNull('tool_slug')
            ->selectRaw('tool_slug, COUNT(*) as count')
            ->groupBy('tool_slug')
            ->orderByDesc('count')
            ->limit(6)
            ->get()
            ->toArray();

        $topToolsByCost = AiUsageLog::whereNotNull('tool_slug')
            ->selectRaw('tool_slug, SUM(cost_usd) as cost')
            ->groupBy('tool_slug')
            ->orderByDesc('cost')
            ->limit(6)
            ->get()
            ->toArray();

        $topToolsByTokens = AiUsageLog::whereNotNull('tool_slug')
            ->selectRaw(\DB::raw('tool_slug, SUM(input_tokens + output_tokens) as tokens'))
            ->groupBy('tool_slug')
            ->orderByDesc('tokens')
            ->limit(6)
            ->get()
            ->toArray();

        // ─── 14. Lists: top AI models (by usage count, cost, tokens) ───
        $topModelsByUsage = AiUsageLog::selectRaw('model, COUNT(*) as count')
            ->groupBy('model')
            ->orderByDesc('count')
            ->limit(6)
            ->get()
            ->toArray();

        $topModelsByCost = AiUsageLog::selectRaw('model, SUM(cost_usd) as cost')
            ->groupBy('model')
            ->orderByDesc('cost')
            ->limit(6)
            ->get()
            ->toArray();

        $topModelsByTokens = AiUsageLog::selectRaw(\DB::raw('model, SUM(input_tokens + output_tokens) as tokens'))
            ->groupBy('model')
            ->orderByDesc('tokens')
            ->limit(6)
            ->get()
            ->toArray();

        // ─── 15. Lists: recently registered users (6) ───
        $recentUsers = User::select(['ulid', 'name', 'email', 'created_at'])->latest()->limit(6)->get()->toArray();

        // ─── 16. Lists: traffic sources (derived from login + social providers) ───
        $trafficSources = $this->trafficSources($now);

        // ─── 17. Activity feed ───
        $activity = $this->getRecentActivity();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'totalUsers' => $totalUsers,
                'newUsersToday' => $newUsersToday,
                'newUsersThisMonth' => $newUsersThisMonth,
                'newUsersLastMonth' => $newUsersLastMonth,
                'activeUsers' => $activeUsers,
                'bannedUsers' => $bannedUsers,
                'totalRevenue' => (float) $totalRevenue,
                'revenueToday' => (float) $revenueToday,
                'revenueThisMonth' => (float) $revenueThisMonth,
                'revenueLastMonth' => (float) $revenueLastMonth,
                'mrr' => (float) $mrr,
                'totalAiRequests' => $totalAiRequests,
                'aiRequestsToday' => $aiRequestsToday,
                'totalCreditsUsed' => (float) $totalCreditsUsed,
                'creditsUsedToday' => (float) $creditsUsedToday,
                'creditsUsedThisMonth' => (float) $creditsUsedThisMonth,
                'totalCost' => round((float) $totalCost, 4),
                'costToday' => round((float) $costToday, 4),
                'tokensUsedToday' => (int) $tokensUsedToday,
                'activeSubscriptions' => $activeSubscriptions,
                'trialingSubscriptions' => $trialingSubscriptions,
                'pastDueSubscriptions' => $pastDueSubscriptions,
                'activePlans' => $activePlans,
                'openTickets' => $openTickets,
                'pendingComments' => $pendingComments,
            ],
            'signupsChart' => $signupsChart,
            'revenueChart' => $revenueChart,
            'aiByTool' => $aiByTool,
            'costByProvider' => $costByProvider,
            'revenueVsCost' => $revenueVsCost,
            'proSubs' => $proSubs,
            'geoUsage' => $geoUsage,
            'topToolsByUsage' => $topToolsByUsage,
            'topToolsByCost' => $topToolsByCost,
            'topToolsByTokens' => $topToolsByTokens,
            'topModelsByUsage' => $topModelsByUsage,
            'topModelsByCost' => $topModelsByCost,
            'topModelsByTokens' => $topModelsByTokens,
            'recentUsers' => $recentUsers,
            'trafficSources' => $trafficSources,
            'activity' => $activity,
        ]);
    }

    private function timeSeries(callable $callback, $now, string $startDate): array
    {
        $ranges = [
            'today' => ['start' => $now->copy()->startOfDay(), 'interval' => 'hour', 'count' => 24],
            '7d' => ['start' => $now->copy()->subDays(6)->startOfDay(), 'interval' => 'day', 'count' => 7],
            '30d' => ['start' => $now->copy()->subDays(29)->startOfDay(), 'interval' => 'day', 'count' => 30],
            '90d' => ['start' => $now->copy()->subDays(89)->startOfDay(), 'interval' => 'day', 'count' => 90],
            'lifetime' => ['start' => $startDate, 'interval' => 'month', 'count' => null],
        ];

        $result = [];
        foreach ($ranges as $key => $cfg) {
            if ($key === 'lifetime') {
                $result[$key] = $this->buildLifetimeSeries($callback, $cfg['start'], $now);
            } elseif ($key === 'today') {
                $result[$key] = collect(range(0, 23))->map(function ($hour) use ($now, $callback) {
                    $slot = $now->copy()->startOfDay()->addHours($hour);

                    return [
                        'date' => $slot->format('H:00'),
                        'value' => $callback($slot->toDateString(), $slot->format('H')),
                    ];
                })->values()->toArray();
            } else {
                $result[$key] = collect(range($cfg['count'] - 1, 0))->map(function ($daysAgo) use ($now, $callback) {
                    $date = $now->copy()->subDays($daysAgo)->toDateString();

                    return [
                        'date' => $date,
                        'value' => $callback($date, null),
                    ];
                })->values()->toArray();
            }
        }

        return $result;
    }

    private function buildLifetimeSeries(callable $callback, $startDate, $now): array
    {
        $result = [];
        $cursor = now()->parse($startDate)->copy()->startOfMonth();

        while ($cursor->lte($now)) {
            $monthStart = $cursor->toDateString();
            $monthEnd = $cursor->copy()->endOfMonth()->toDateString();
            $result[] = [
                'date' => $cursor->format('M Y'),
                'value' => $callback($monthStart, null, $monthEnd),
            ];
            $cursor->addMonth();
        }

        return $result;
    }

    private function timeSeriesDual(callable $callbackA, callable $callbackB, $now, string $startDate): array
    {
        $ranges = [
            'today' => ['start' => $now->copy()->startOfDay(), 'interval' => 'hour', 'count' => 24],
            '7d' => ['start' => $now->copy()->subDays(6)->startOfDay(), 'interval' => 'day', 'count' => 7],
            '30d' => ['start' => $now->copy()->subDays(29)->startOfDay(), 'interval' => 'day', 'count' => 30],
            '90d' => ['start' => $now->copy()->subDays(89)->startOfDay(), 'interval' => 'day', 'count' => 90],
            'lifetime' => ['start' => $startDate, 'interval' => 'month'],
        ];

        $result = [];
        foreach ($ranges as $key => $cfg) {
            if ($key === 'lifetime') {
                $result[$key] = $this->buildLifetimeDual($callbackA, $callbackB, $cfg['start'], $now);
            } elseif ($key === 'today') {
                $result[$key] = collect(range(0, 23))->map(function ($hour) use ($now, $callbackA, $callbackB) {
                    $slot = $now->copy()->startOfDay()->addHours($hour);
                    $dateStr = $slot->toDateString();
                    $hourStr = $slot->format('H');
                    return [
                        'date' => $slot->format('H:00'),
                        'revenue' => $callbackA($dateStr, $hourStr),
                        'cost' => $callbackB($dateStr, $hourStr),
                    ];
                })->values()->toArray();
            } else {
                $result[$key] = collect(range($cfg['count'] - 1, 0))->map(function ($daysAgo) use ($now, $callbackA, $callbackB) {
                    $date = $now->copy()->subDays($daysAgo)->toDateString();
                    return [
                        'date' => $date,
                        'revenue' => $callbackA($date, null),
                        'cost' => $callbackB($date, null),
                    ];
                })->values()->toArray();
            }
        }

        return $result;
    }

    private function buildLifetimeDual(callable $callbackA, callable $callbackB, $startDate, $now): array
    {
        $result = [];
        $cursor = now()->parse($startDate)->copy()->startOfMonth();
        while ($cursor->lte($now)) {
            $monthStart = $cursor->toDateString();
            $monthEnd = $cursor->copy()->endOfMonth()->toDateString();
            $result[] = [
                'date' => $cursor->format('M Y'),
                'revenue' => $callbackA($monthStart, null, $monthEnd),
                'cost' => $callbackB($monthStart, null, $monthEnd),
            ];
            $cursor->addMonth();
        }
        return $result;
    }

    private function trafficSources($now): array
    {
        $thirtyDaysAgo = $now->copy()->subDays(30);

        $socialProviders = User::whereNotNull('oauth_provider')
            ->whereBetween('created_at', [$thirtyDaysAgo, $now])
            ->selectRaw('oauth_provider, COUNT(*) as count')
            ->groupBy('oauth_provider')
            ->orderByDesc('count')
            ->get();

        $referred = User::whereNotNull('referred_by')
            ->whereBetween('created_at', [$thirtyDaysAgo, $now])
            ->count();

        $emailLogin = LoginHistory::where('success', true)
            ->whereBetween('created_at', [$thirtyDaysAgo, $now])
            ->count();

        // social logins already counted via login_history
        $total = $emailLogin + $socialProviders->sum('count');

        $sources = $socialProviders->map(fn ($row) => [
            'label' => ucfirst($row->oauth_provider),
            'count' => $row->count,
        ])->toArray();

        // direct (email)
        $directLogins = max(0, $emailLogin - $socialProviders->sum('count'));
        array_unshift($sources, ['label' => translate('Direct'), 'count' => $directLogins]);

        if ($referred > 0) {
            $sources[] = ['label' => translate('Referral'), 'count' => $referred];
        }

        return $sources;
    }

    private function getRecentActivity(): array
    {
        $activities = collect();

        User::latest()->take(8)->get()->each(function ($user) use (&$activities) {
            $activities->push([
                'type' => 'user_registered',
                'icon' => 'user',
                'title' => $user->name,
                'detail' => $user->email,
                'time' => $user->created_at,
            ]);
        });

        Payment::with('user')->where('status', 'completed')->latest()->take(8)->get()->each(function ($payment) use (&$activities) {
            $activities->push([
                'type' => $payment->type === 'subscription' ? 'subscription' : 'payment',
                'icon' => 'dollar',
                'title' => $payment->user?->name ?? translate('Unknown'),
                'detail' => number_format($payment->amount, 2).' '.$payment->currency,
                'time' => $payment->created_at,
            ]);
        });

        AiUsageLog::with('user')->latest()->take(8)->get()->each(function ($log) use (&$activities) {
            $activities->push([
                'type' => 'ai_request',
                'icon' => 'spark',
                'title' => $log->user?->name ?? translate('Unknown'),
                'detail' => ($log->tool_slug ?? $log->provider.' / '.$log->model).' — '.number_format($log->credits_used, 2).' credits',
                'time' => $log->created_at,
            ]);
        });

        AffiliateReferral::with(['referrer', 'referred'])->whereNotNull('converted_at')->latest('converted_at')->take(8)->get()->each(function ($ref) use (&$activities) {
            $activities->push([
                'type' => 'referral',
                'icon' => 'user',
                'title' => $ref->referrer?->name ?? translate('Unknown'),
                'detail' => $ref->referred?->email ?? translate('Unknown'),
                'time' => $ref->converted_at,
            ]);
        });

        return $activities->sortByDesc('time')->take(20)->values()->toArray();
    }
}

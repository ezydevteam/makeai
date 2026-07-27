<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateReferral;
use App\Models\AiTool;
use App\Models\AiModel;
use App\Models\AiUsageLog;
use App\Models\BlogPost;
use App\Models\NewsletterSubscriber;
use App\Models\GatewaySubscription;
use App\Models\Comment;
use App\Models\LoginHistory;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $period = request('period', '7d');
        $cacheKey = 'admin_dashboard_' . $period;
        $cacheTtl = now()->addMinutes(10);

        $data = Cache::remember($cacheKey, $cacheTtl, function () use ($period) {
            return $this->buildDashboardData($period);
        });

        // Revenue and subscription analytics are premium (Extended License) data.
        // The Vue page hides these sections, but the data must not ship in the
        // Inertia payload either. Applied after the cache so a license/toggle
        // change takes effect immediately.
        if (! isProAvailable()) {
            $data = $this->stripPremiumData($data);
        }

        return Inertia::render('Admin/Dashboard/Index', $data);
    }

    private function stripPremiumData(array $data): array
    {
        $emptySeries = ['today' => [], '7d' => [], '30d' => [], '90d' => [], 'lifetime' => []];

        foreach (['revenueChart', 'subscriptionRevenueChart', 'subscriptionConversionsChart', 'subscriptionRetainedChart', 'subscriptionChurnedChart', 'revenueVsCost', 'proSubs'] as $key) {
            $data['dashboardCharts'][$key] = $emptySeries;
        }

        foreach ($data['dashboardCharts']['cardComparisons'] ?? [] as $period => $comparison) {
            if (isset($comparison['revenue'])) {
                $data['dashboardCharts']['cardComparisons'][$period]['revenue'] = ['current' => 0.0, 'previous' => 0.0];
            }
        }

        $data['recentProSubscriptions'] = [];

        return $data;
    }

    /**
     * Start of the "30d" chart bucket.
     *
     * A real install gets month-to-date, which is what the finance-facing cards are
     * understood to mean — changing that would silently restate live revenue figures.
     *
     * The demo gets a true rolling 30 days. Month-to-date collapses to a single bar on the
     * 1st and stays visibly thin for the first week of every month, so the showcase looked
     * broken on those dates no matter how good the seeded data was — the one thing a demo
     * cannot afford, since a buyer cannot tell "start of the month" from "this product has
     * no data". Guarded by config('demo.enabled').
     */
    private function thirtyDayStart(\Illuminate\Support\Carbon $now): \Illuminate\Support\Carbon
    {
        return config('demo.enabled')
            ? $now->copy()->subDays(29)->startOfDay()
            : $now->copy()->startOfMonth();
    }

    private function buildDashboardData(string $period): array
    {
        $now = now();
        $dashboardPeriod = $this->dashboardPeriod($period);
        $selectedPeriodStart = null;
        $monthStart = $now->copy()->startOfMonth();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();

        // System (internal AI) user — drives the internal-usage card comparisons and charts below.
        $systemUser = User::where('email', User::internalAiEmail())->first();

        // ─── 0. Earliest data point for lifetime charts ───
        $earliestUser = User::min('created_at') ?? $now->copy()->subYear();
        $lifetimeStart = now()->parse($earliestUser)->startOfMonth();
        [$selectedPeriodStart, $selectedPeriodEnd] = $this->dashboardPeriodRange($now, $dashboardPeriod, $lifetimeStart);

        $cardComparisonPeriods = [
            'today' => 1,
            '7d' => 7,
            '30d' => config('demo.enabled') ? 30 : $now->daysInMonth,
            '90d' => 90,
        ];

        $cardComparisons = [];
        foreach ($cardComparisonPeriods as $period => $days) {
            // In demo mode the 30d series is a rolling window (see thirtyDayStart), so the
            // comparison has to roll with it or the card's trend would describe a different
            // period than the chart above it.
            if ($period === '30d' && ! config('demo.enabled')) {
                // The 30d card value sums a month-to-date series (startOfMonth → now),
                // so its trend must compare month-to-date against the SAME elapsed slice
                // of last month — not a rolling 30-day window.
                $elapsedDays = (int) $monthStart->diffInDays($now);
                $currentStart = $monthStart->copy();
                $currentEnd = $now->copy()->endOfDay();
                $previousStart = $lastMonthStart->copy();
                $previousEnd = $lastMonthStart->copy()->addDays($elapsedDays)->endOfDay();
            } else {
                $currentStart = $now->copy()->subDays($days - 1)->startOfDay();
                $currentEnd = $now->copy()->endOfDay();
                $previousStart = $now->copy()->subDays($days * 2 - 1)->startOfDay();
                $previousEnd = $now->copy()->subDays($days)->endOfDay();
            }

            // Batch User queries
            $signupsCurrent = User::whereBetween('created_at', [$currentStart, $currentEnd])->count();
            $signupsPrevious = User::whereBetween('created_at', [$previousStart, $previousEnd])->count();

            // Batch Payment queries
            $revenueData = Payment::where('status', 'completed')
                ->selectRaw("
                    SUM(CASE WHEN created_at BETWEEN ? AND ? THEN amount ELSE 0 END) as current_revenue,
                    SUM(CASE WHEN created_at BETWEEN ? AND ? THEN amount ELSE 0 END) as previous_revenue
                ", [$currentStart, $currentEnd, $previousStart, $previousEnd])
                ->first();

            // Batch AiUsageLog queries
            $aiData = AiUsageLog::selectRaw("
                SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as current_requests,
                SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as previous_requests,
                SUM(CASE WHEN created_at BETWEEN ? AND ? THEN output_tokens ELSE 0 END) as current_tokens,
                SUM(CASE WHEN created_at BETWEEN ? AND ? THEN output_tokens ELSE 0 END) as previous_tokens,
                SUM(CASE WHEN created_at BETWEEN ? AND ? THEN credits_used ELSE 0 END) as current_credits,
                SUM(CASE WHEN created_at BETWEEN ? AND ? THEN credits_used ELSE 0 END) as previous_credits,
                SUM(CASE WHEN created_at BETWEEN ? AND ? THEN cost_usd ELSE 0 END) as current_cost,
                SUM(CASE WHEN created_at BETWEEN ? AND ? THEN cost_usd ELSE 0 END) as previous_cost
            ", [
                $currentStart, $currentEnd, $previousStart, $previousEnd,
                $currentStart, $currentEnd, $previousStart, $previousEnd,
                $currentStart, $currentEnd, $previousStart, $previousEnd,
                $currentStart, $currentEnd, $previousStart, $previousEnd,
            ])->first();

            // Internal AI queries
            $internalAiCurrent = $systemUser ? AiUsageLog::where('user_id', $systemUser->id)->whereBetween('created_at', [$currentStart, $currentEnd])->count() : 0;
            $internalAiPrevious = $systemUser ? AiUsageLog::where('user_id', $systemUser->id)->whereBetween('created_at', [$previousStart, $previousEnd])->count() : 0;
            $internalAiCostCurrent = $systemUser ? (float) AiUsageLog::where('user_id', $systemUser->id)->whereBetween('created_at', [$currentStart, $currentEnd])->sum('cost_usd') : 0;
            $internalAiCostPrevious = $systemUser ? (float) AiUsageLog::where('user_id', $systemUser->id)->whereBetween('created_at', [$previousStart, $previousEnd])->sum('cost_usd') : 0;

            // Ticket queries
            $ticketsCurrent = SupportTicket::whereIn('status', ['open', 'in_progress'])->whereBetween('created_at', [$currentStart, $currentEnd])->count();
            $ticketsPrevious = SupportTicket::whereIn('status', ['open', 'in_progress'])->whereBetween('created_at', [$previousStart, $previousEnd])->count();

            // Active users — see $activeUsersChart for why last_login_at is used.
            $activeUsersCurrent = User::excludingInternal()->whereBetween('last_login_at', [$currentStart, $currentEnd])->count();
            $activeUsersPrevious = User::excludingInternal()->whereBetween('last_login_at', [$previousStart, $previousEnd])->count();

            $cardComparisons[$period] = [
                'signups' => [
                    'current' => $signupsCurrent,
                    'previous' => $signupsPrevious,
                ],
                'revenue' => [
                    'current' => (float) ($revenueData->current_revenue ?? 0),
                    'previous' => (float) ($revenueData->previous_revenue ?? 0),
                ],
                'aiRequests' => [
                    'current' => (int) ($aiData->current_requests ?? 0),
                    'previous' => (int) ($aiData->previous_requests ?? 0),
                ],
                'outputTokens' => [
                    'current' => (int) ($aiData->current_tokens ?? 0),
                    'previous' => (int) ($aiData->previous_tokens ?? 0),
                ],
                'internalAiRequests' => [
                    'current' => $internalAiCurrent,
                    'previous' => $internalAiPrevious,
                ],
                'internalAiCost' => [
                    'current' => $internalAiCostCurrent,
                    'previous' => $internalAiCostPrevious,
                ],
                'creditsUsed' => [
                    'current' => (float) ($aiData->current_credits ?? 0),
                    'previous' => (float) ($aiData->previous_credits ?? 0),
                ],
                'totalCost' => [
                    'current' => (float) ($aiData->current_cost ?? 0),
                    'previous' => (float) ($aiData->previous_cost ?? 0),
                ],
                'openTickets' => [
                    'current' => $ticketsCurrent,
                    'previous' => $ticketsPrevious,
                ],
                'activeUsers' => [
                    'current' => $activeUsersCurrent,
                    'previous' => $activeUsersPrevious,
                ],
            ];
        }

        // ─── 6. Chart: User signups (today / 7d / 30d / 90d / lifetime) ───
        $signupsChart = $this->buildSeriesFromQuery(
            User::query(),
            'created_at',
            $now,
            $lifetimeStart,
            'count'
        );

        // ─── 7. Chart: Revenue (today / 7d / 30d / 90d / lifetime) ───
        $revenueChart = $this->buildSeriesFromQuery(
            Payment::where('status', 'completed'),
            'created_at',
            $now,
            $lifetimeStart,
            'sum',
            'amount'
        );

        // ─── 8. Chart: AI usage by tool (selected dashboard range) ───
        $aiByToolRows = AiUsageLog::whereBetween('created_at', [$selectedPeriodStart, $selectedPeriodEnd])
            ->whereNotNull('tool_slug')
            ->selectRaw('tool_slug, SUM(credits_used) as total_credits')
            ->groupBy('tool_slug')
            ->orderByDesc('total_credits')
            ->limit(8)
            ->get();

        $aiToolNames = AiTool::whereIn('slug', $aiByToolRows->pluck('tool_slug')->filter()->unique())
            ->pluck('name', 'slug');

        $aiByTool = $aiByToolRows
            ->map(fn ($row) => [
                'label' => $aiToolNames[$row->tool_slug] ?? $row->tool_slug,
                'credits' => (float) $row->total_credits,
            ])
            ->values()
            ->toArray();

        // ─── 9. Chart: Token cost by provider (selected dashboard range) ───
        $costByProvider = AiUsageLog::whereBetween('created_at', [$selectedPeriodStart, $selectedPeriodEnd])
            ->selectRaw('provider, SUM(cost_usd) as total_cost')
            ->groupBy('provider')
            ->orderByDesc('total_cost')
            ->get()
            ->map(fn ($row) => [
                'label' => $this->formatProviderLabel($row->provider),
                'cost' => round((float) $row->total_cost, 4),
            ])
            ->values()
            ->toArray();

        $providerUsageByCount = AiUsageLog::whereBetween('created_at', [$selectedPeriodStart, $selectedPeriodEnd])
            ->selectRaw('provider, COUNT(*) as total_requests')
            ->groupBy('provider')
            ->orderByDesc('total_requests')
            ->get()
            ->map(fn ($row) => [
                'label' => $this->formatProviderLabel($row->provider),
                'count' => (int) $row->total_requests,
            ])
            ->values()
            ->toArray();

        // ─── 10. Chart: Revenue vs Cost (today / 7d / 30d / 90d / lifetime) ───
        $revenueVsCost = $this->buildDualSeriesFromQuery(
            Payment::where('status', 'completed'),
            'created_at',
            AiUsageLog::query(),
            'created_at',
            $now,
            $lifetimeStart,
            'sum',
            'amount',
            'sum',
            'cost_usd'
        );

        // ─── 10b. Growth / health series for the selected dashboard range ───
        $subscriptionRevenueChart = $this->buildSeriesFromQuery(
            Payment::where('status', 'completed')->where('type', 'subscription'),
            'created_at',
            $now,
            $lifetimeStart,
            'sum',
            'amount'
        );

        $totalCostChart = $this->buildSeriesFromQuery(
            AiUsageLog::query(),
            'created_at',
            $now,
            $lifetimeStart,
            'sum',
            'cost_usd'
        );

        $outputTokensChart = $this->buildSeriesFromQuery(
            AiUsageLog::query(),
            'created_at',
            $now,
            $lifetimeStart,
            'sum',
            'output_tokens'
        );

        $newsletterSubscribersChart = $this->buildSeriesFromQuery(
            NewsletterSubscriber::query(),
            'created_at',
            $now,
            $lifetimeStart,
            'count'
        );

        $subscriptionConversionsChart = $this->buildSeriesFromQuery(
            Payment::where('status', 'completed')->where('type', 'subscription'),
            'created_at',
            $now,
            $lifetimeStart,
            'count'
        );

        // Subscription health (premium): new subscriptions started per period, mirrored
        // against the per-period churn series below. Sourced from billing subscriptions —
        // NOT the users table — and stripped for non-pro installs in stripPremiumData().
        // The cumulative active-subscription curve lives separately in the proSubs chart.
        $subscriptionRetainedChart = $this->buildSeriesFromQuery(
            GatewaySubscription::whereIn('status', ['active', 'trialing']),
            'created_at',
            $now,
            $lifetimeStart,
            'count'
        );

        // Special case: subscriptionChurnedChart - uses cancelled_at or current_period_end
        $subscriptionChurnedChart = $this->buildChurnedSeries(
            GatewaySubscription::query(),
            $now,
            $lifetimeStart
        );

        // Dual series: referral chart
        $referralChart = $this->buildDualSeriesFromQuery(
            AffiliateReferral::whereNotNull('landed_at'),
            'landed_at',
            AffiliateReferral::whereNotNull('converted_at'),
            'converted_at',
            $now,
            $lifetimeStart,
            'count',
            null,
            'count',
            null
        );

        $aiRequestsTotalChart = $this->buildSeriesFromQuery(
            AiUsageLog::query(),
            'created_at',
            $now,
            $lifetimeStart,
            'count'
        );

        $aiFailedRequestsChart = $this->buildSeriesFromQuery(
            AiUsageLog::whereIn('status', ['failed', 'cancelled']),
            'created_at',
            $now,
            $lifetimeStart,
            'count'
        );

        $aiRequestsChart = $this->buildSeriesFromQuery(
            AiUsageLog::query(),
            'created_at',
            $now,
            $lifetimeStart,
            'count'
        );

        $creditsUsedChart = $this->buildSeriesFromQuery(
            AiUsageLog::query(),
            'created_at',
            $now,
            $lifetimeStart,
            'sum',
            'credits_used'
        );

        // Special case: internal AI charts need user_id filter
        $internalAiRequestsChart = $systemUser 
            ? $this->buildSeriesFromQuery(
                AiUsageLog::where('user_id', $systemUser->id),
                'created_at',
                $now,
                $lifetimeStart,
                'count'
            )
            : $this->buildEmptySeries($now, $lifetimeStart);

        $internalAiCostChart = $systemUser
            ? $this->buildSeriesFromQuery(
                AiUsageLog::where('user_id', $systemUser->id),
                'created_at',
                $now,
                $lifetimeStart,
                'sum',
                'cost_usd'
            )
            : $this->buildEmptySeries($now, $lifetimeStart);

        // Special case: openTicketsChart - cumulative count
        $openTicketsChart = $this->buildCumulativeSeries(
            SupportTicket::whereIn('status', ['open', 'in_progress']),
            'created_at',
            $now,
            $lifetimeStart
        );

        // Active users = logged in during the window, matching the definition in
        // UserManagementController (NOT the is_active column, which measures
        // account age rather than engagement). Bucketing on last_login_at is safe
        // to sum: it holds one timestamp per user, so nobody lands in two buckets.
        $activeUsersChart = $this->buildSeriesFromQuery(
            User::excludingInternal()->whereNotNull('last_login_at'),
            'last_login_at',
            $now,
            $lifetimeStart,
            'count'
        );

        // Special case: pendingCommentsChart - cumulative count
        $pendingCommentsChart = $this->buildCumulativeSeries(
            Comment::where('status', 'pending'),
            'created_at',
            $now,
            $lifetimeStart
        );

        // Special case: proSubs - active subscriptions at each point in time
        $proSubs = $this->buildActiveSubscriptionsSeries(
            User::where('subscription_status', 'active'),
            $now,
            $lifetimeStart
        );

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
        $topToolsByUsageRows = AiUsageLog::whereBetween('created_at', [$selectedPeriodStart, $selectedPeriodEnd])
            ->whereNotNull('tool_slug')
            ->selectRaw('tool_slug, COUNT(*) as count')
            ->groupBy('tool_slug')
            ->orderByDesc('count')
            ->limit(9)
            ->get();

        // Fetch id alongside the name so the dashboard can deep-link each tool to its
        // admin edit page (AiTool binds by id).
        $topTools = AiTool::whereIn('slug', $topToolsByUsageRows->pluck('tool_slug')->filter()->unique())
            ->get(['id', 'slug', 'name'])
            ->keyBy('slug');

        $topToolsByUsage = $topToolsByUsageRows
            ->map(fn ($row) => [
                'tool_id' => $topTools[$row->tool_slug]->id ?? null,
                'tool_slug' => $row->tool_slug,
                'tool_name' => $topTools[$row->tool_slug]->name ?? $row->tool_slug,
                'count' => (int) $row->count,
            ])
            ->values()
            ->toArray();

        $topToolsByCost = AiUsageLog::whereBetween('created_at', [$selectedPeriodStart, $selectedPeriodEnd])
            ->whereNotNull('tool_slug')
            ->selectRaw('tool_slug, SUM(cost_usd) as cost')
            ->groupBy('tool_slug')
            ->orderByDesc('cost')
            ->limit(6)
            ->get()
            ->toArray();

        $topToolsByTokens = AiUsageLog::whereBetween('created_at', [$selectedPeriodStart, $selectedPeriodEnd])
            ->whereNotNull('tool_slug')
            ->selectRaw(\DB::raw('tool_slug, SUM(input_tokens + output_tokens) as tokens'))
            ->groupBy('tool_slug')
            ->orderByDesc('tokens')
            ->limit(6)
            ->get()
            ->toArray();

        // ─── 14. Lists: top AI models (by usage count, cost, tokens) ───
        $topModelsByUsageRows = AiUsageLog::whereBetween('created_at', [$selectedPeriodStart, $selectedPeriodEnd])
            ->selectRaw('model, COUNT(*) as count')
            ->groupBy('model')
            ->orderByDesc('count')
            ->limit(9)
            ->get();

        $topModelNames = AiModel::whereIn('slug', $topModelsByUsageRows->pluck('model')->filter()->unique())
            ->pluck('name', 'slug');

        $topModelsByUsage = $topModelsByUsageRows
            ->map(fn ($row) => [
                'model' => $row->model,
                'model_name' => $topModelNames[$row->model] ?? $row->model,
                'count' => (int) $row->count,
            ])
            ->values()
            ->toArray();

        $topModelsByCostRows = AiUsageLog::whereBetween('created_at', [$selectedPeriodStart, $selectedPeriodEnd])
            ->selectRaw('model, SUM(cost_usd) as cost')
            ->groupBy('model')
            ->orderByDesc('cost')
            ->limit(6)
            ->get();

        $topModelsByCost = $topModelsByCostRows
            ->map(fn ($row) => [
                'model' => $row->model,
                'model_name' => $topModelNames[$row->model] ?? $row->model,
                'cost' => (float) $row->cost,
            ])
            ->values()
            ->toArray();

        $topModelsByTokensRows = AiUsageLog::whereBetween('created_at', [$selectedPeriodStart, $selectedPeriodEnd])
            ->selectRaw(\DB::raw('model, SUM(input_tokens + output_tokens) as tokens'))
            ->groupBy('model')
            ->orderByDesc('tokens')
            ->limit(6)
            ->get();

        $topModelsByTokens = $topModelsByTokensRows
            ->map(fn ($row) => [
                'model' => $row->model,
                'model_name' => $topModelNames[$row->model] ?? $row->model,
                'tokens' => (int) $row->tokens,
            ])
            ->values()
            ->toArray();

        // ─── 15. Lists: recently registered users (6) ───
        $recentUsers = User::select(['ulid', 'name', 'email', 'created_at'])
            ->whereBetween('created_at', [$selectedPeriodStart, $selectedPeriodEnd])
            ->latest()
            ->limit(6)
            ->get()
            ->toArray();

        // ─── 16. Lists: popular blog posts (6) ───
        $popularBlogPosts = BlogPost::query()
            ->published()
            ->orderByDesc('views_count')
            ->latest('published_at')
            ->limit(5)
            ->get(['ulid', 'title', 'views_count', 'published_at'])
            ->map(fn (BlogPost $post) => [
                'ulid' => $post->ulid,
                'title' => $post->title,
                'views_count' => (int) $post->views_count,
                'published_at' => optional($post->published_at)?->toISOString(),
            ])
            ->toArray();

        // ─── 17. Lists: recent pro subscriptions (5) ───
        $recentProSubscriptions = GatewaySubscription::query()
            ->with(['user:id,ulid,name,email', 'plan:id,name'])
            ->whereIn('status', ['active', 'trialing'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (GatewaySubscription $sub) => [
                'id' => $sub->id,
                'status' => $sub->status,
                'billing_cycle' => $sub->billing_cycle,
                'created_at' => optional($sub->created_at)?->toISOString(),
                'user' => $sub->user ? [
                    'ulid' => $sub->user->ulid,
                    'name' => $sub->user->name,
                    'email' => $sub->user->email,
                ] : null,
                'plan' => $sub->plan ? [
                    'name' => $sub->plan->name,
                ] : null,
            ])
            ->toArray();

        // ─── 18. Lists: traffic sources (derived from login + social providers) ───
        $trafficSources = $this->trafficSources($now, $dashboardPeriod, $lifetimeStart);

        // ─── 19. Activity feed ───
        $activity = $this->getRecentActivity(5);

        // ─── 20. Addon stats ───
        $addonStats = $this->getAddonStats();

        $payload = [
            'dashboardCharts' => [
                'signupsChart' => $signupsChart,
                'revenueChart' => $revenueChart,
                'subscriptionRevenueChart' => $subscriptionRevenueChart,
                'totalCostChart' => $totalCostChart,
                'outputTokensChart' => $outputTokensChart,
                'newsletterSubscribersChart' => $newsletterSubscribersChart,
                'subscriptionConversionsChart' => $subscriptionConversionsChart,
                'subscriptionRetainedChart' => $subscriptionRetainedChart,
                'subscriptionChurnedChart' => $subscriptionChurnedChart,
                'referralChart' => $referralChart,
                'aiRequestsTotalChart' => $aiRequestsTotalChart,
                'aiFailedRequestsChart' => $aiFailedRequestsChart,
                'aiRequestsChart' => $aiRequestsChart,
                'creditsUsedChart' => $creditsUsedChart,
                'internalAiRequestsChart' => $internalAiRequestsChart,
                'internalAiCostChart' => $internalAiCostChart,
                'openTicketsChart' => $openTicketsChart,
                'activeUsersChart' => $activeUsersChart,
                'pendingCommentsChart' => $pendingCommentsChart,
                'revenueVsCost' => $revenueVsCost,
                'proSubs' => $proSubs,
                'cardComparisons' => $cardComparisons,
            ],
            'dashboardPeriod' => $dashboardPeriod,
            'aiByTool' => $aiByTool,
            'costByProvider' => $costByProvider,
            'providerUsageByCount' => $providerUsageByCount,
            'geoUsage' => $geoUsage,
            'topToolsByUsage' => $topToolsByUsage,
            'topToolsByCost' => $topToolsByCost,
            'topToolsByTokens' => $topToolsByTokens,
            'topModelsByUsage' => $topModelsByUsage,
            'topModelsByCost' => $topModelsByCost,
            'topModelsByTokens' => $topModelsByTokens,
            'recentUsers' => $recentUsers,
            'popularBlogPosts' => $popularBlogPosts,
            'recentProSubscriptions' => $recentProSubscriptions,
            'trafficSources' => $trafficSources,
            'activity' => $activity,
            'addonStats' => $addonStats,
        ];

        // Demo-only: guarantee every period and list renders — see applyDemoDashboardFloor().
        if (config('demo.enabled')) {
            $payload = $this->applyDemoDashboardFloor($payload, $now);
        }

        return $payload;
    }

    /**
     * Demo-only dashboard "data floor".
     *
     * DemoSeeder anchors rows to now() and demo:reset re-runs every 6 hours, but between
     * midnight and the next reset the "today" buckets (and any genuinely quiet period)
     * fall to all-zero — which the dashboard renders as "No data found". A demo must
     * never look empty regardless of the wall-clock date, so this fills every all-zero
     * period series and every empty list/donut with plausible, deterministic fixed
     * values. Guarded by config('demo.enabled'); a real install never reaches it. Runs
     * inside the cached build and BEFORE the premium strip, so a non-pro demo still hides
     * revenue/subscription series (stripPremiumData zeroes them afterwards).
     */
    private function applyDemoDashboardFloor(array $data, $now): array
    {
        foreach (($data['dashboardCharts'] ?? []) as $key => $series) {
            if ($key === 'cardComparisons' || ! is_array($series)) {
                continue;
            }
            foreach ($series as $period => $points) {
                if (is_array($points) && $points !== [] && $this->demoSeriesIsAllZero($points)) {
                    $data['dashboardCharts'][$key][$period] = $this->demoFillSeries($points, $key . $period);
                }
            }
        }

        // KPI cards: a zeroed current/previous pair reads as a dead business — give it life.
        foreach (($data['dashboardCharts']['cardComparisons'] ?? []) as $period => $comparison) {
            foreach ($comparison as $metric => $pair) {
                if (is_array($pair) && (float) ($pair['current'] ?? 0) === 0.0 && (float) ($pair['previous'] ?? 0) === 0.0) {
                    $seed = crc32($period . $metric);
                    $current = 4 + ($seed % 40);
                    $data['dashboardCharts']['cardComparisons'][$period][$metric] = [
                        'current' => $current,
                        'previous' => max(0, $current - (1 + ($seed % 9))),
                    ];
                }
            }
        }

        // Real tool/model names keep labels and links authentic; the metrics are synthetic.
        // Falls back to fixed rows so the floor holds even if the catalog is somehow empty.
        $tools = AiTool::query()->orderBy('id')->limit(9)->get(['id', 'slug', 'name'])
            ->map(fn ($t) => ['id' => $t->id, 'slug' => $t->slug, 'name' => $t->name ?? $t->slug])->all();
        if ($tools === []) {
            $tools = array_map(fn ($s) => ['id' => null, 'slug' => $s, 'name' => Str::headline($s)], [
                'blog-article-generator', 'seo-meta-description-generator', 'email-subject-line-generator',
                'product-description-writer', 'social-media-caption', 'ad-copy-generator',
                'code-explainer', 'paragraph-rewriter', 'youtube-script-writer',
            ]);
        }

        $models = AiModel::query()->orderBy('id')->limit(9)->get(['slug', 'name'])
            ->map(fn ($m) => ['slug' => $m->slug, 'name' => $m->name ?? $m->slug])->all();
        if ($models === []) {
            $models = [
                ['slug' => 'gpt-5.6-terra', 'name' => 'GPT-5.6 Terra'],
                ['slug' => 'gpt-5.4-mini', 'name' => 'GPT-5.4 Mini'],
                ['slug' => 'claude-sonnet-4-6', 'name' => 'Claude Sonnet 4.6'],
                ['slug' => 'claude-haiku-4-5', 'name' => 'Claude Haiku 4.5'],
                ['slug' => 'gemini-3.5-flash', 'name' => 'Gemini 3.5 Flash'],
                ['slug' => 'deepseek-v4-pro', 'name' => 'DeepSeek V4 Pro'],
                ['slug' => 'llama-3.3-70b-versatile', 'name' => 'Llama 3.3 70B Versatile'],
            ];
        }

        if (empty($data['aiByTool'])) {
            $data['aiByTool'] = collect($tools)->take(6)->values()
                ->map(fn ($t, $i) => ['label' => $t['name'], 'credits' => (float) (900 - $i * 120)])
                ->toArray();
        }
        if (empty($data['topToolsByUsage'])) {
            $data['topToolsByUsage'] = collect($tools)->values()
                ->map(fn ($t, $i) => ['tool_id' => $t['id'] ?? null, 'tool_slug' => $t['slug'], 'tool_name' => $t['name'], 'count' => 240 - $i * 22])
                ->toArray();
        }
        if (empty($data['topToolsByCost'])) {
            $data['topToolsByCost'] = collect($tools)->take(6)->values()
                ->map(fn ($t, $i) => ['tool_slug' => $t['slug'], 'cost' => round(18.5 - $i * 2.3, 2)])
                ->toArray();
        }
        if (empty($data['topToolsByTokens'])) {
            $data['topToolsByTokens'] = collect($tools)->take(6)->values()
                ->map(fn ($t, $i) => ['tool_slug' => $t['slug'], 'tokens' => 120000 - $i * 14000])
                ->toArray();
        }
        if (empty($data['topModelsByUsage'])) {
            $data['topModelsByUsage'] = collect($models)->values()
                ->map(fn ($m, $i) => ['model' => $m['slug'], 'model_name' => $m['name'], 'count' => 300 - $i * 26])
                ->toArray();
        }
        if (empty($data['topModelsByCost'])) {
            $data['topModelsByCost'] = collect($models)->take(6)->values()
                ->map(fn ($m, $i) => ['model' => $m['slug'], 'model_name' => $m['name'], 'cost' => round(24.0 - $i * 3.1, 2)])
                ->toArray();
        }
        if (empty($data['topModelsByTokens'])) {
            $data['topModelsByTokens'] = collect($models)->take(6)->values()
                ->map(fn ($m, $i) => ['model' => $m['slug'], 'model_name' => $m['name'], 'tokens' => 210000 - $i * 24000])
                ->toArray();
        }
        if (empty($data['costByProvider'])) {
            $data['costByProvider'] = [
                ['label' => 'OpenAI', 'cost' => 18.42],
                ['label' => 'Anthropic', 'cost' => 12.90],
                ['label' => 'Google', 'cost' => 7.35],
                ['label' => 'DeepSeek', 'cost' => 3.10],
            ];
        }
        if (empty($data['providerUsageByCount'])) {
            $data['providerUsageByCount'] = [
                ['label' => 'OpenAI', 'count' => 320],
                ['label' => 'Anthropic', 'count' => 210],
                ['label' => 'Google', 'count' => 140],
                ['label' => 'Meta', 'count' => 90],
            ];
        }
        if (empty($data['geoUsage'])) {
            $data['geoUsage'] = [
                ['country' => 'United States', 'logins' => 142],
                ['country' => 'United Kingdom', 'logins' => 86],
                ['country' => 'India', 'logins' => 73],
                ['country' => 'Germany', 'logins' => 54],
                ['country' => 'Singapore', 'logins' => 38],
            ];
        }
        if (empty($data['trafficSources'])) {
            $data['trafficSources'] = [
                ['label' => translate('Direct'), 'count' => 128],
                ['label' => 'Google', 'count' => 74],
                ['label' => translate('Referral'), 'count' => 39],
                ['label' => 'Github', 'count' => 21],
            ];
        }
        if (empty($data['recentUsers'])) {
            $data['recentUsers'] = User::excludingInternal()
                ->latest()->limit(6)->get(['ulid', 'name', 'email', 'created_at'])->toArray();
        }
        if (empty($data['popularBlogPosts'])) {
            $data['popularBlogPosts'] = BlogPost::published()
                ->orderByDesc('views_count')->limit(5)
                ->get(['ulid', 'title', 'views_count', 'published_at'])
                ->map(fn ($p) => [
                    'ulid' => $p->ulid,
                    'title' => $p->title,
                    'views_count' => (int) $p->views_count,
                    'published_at' => optional($p->published_at)?->toISOString(),
                ])->toArray();
        }
        if (empty($data['activity'])) {
            $data['activity'] = $this->getRecentActivity(5);
        }

        return $data;
    }

    /**
     * True when every point in a period series is zero (the same "empty" the Vue's
     * isEmptySeries() detects and renders as "No data").
     */
    private function demoSeriesIsAllZero(array $points): bool
    {
        foreach ($points as $p) {
            if (array_key_exists('value', $p)) {
                if ((float) $p['value'] !== 0.0) {
                    return false;
                }
            } elseif ((float) ($p['revenue'] ?? 0) !== 0.0 || (float) ($p['cost'] ?? 0) !== 0.0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Overwrite a period's points with a deterministic non-zero wave, preserving the
     * date labels already computed for the current wall-clock. Cumulative charts rise
     * monotonically; the rest gently wave. Same seed key → identical output (cache-safe).
     */
    private function demoFillSeries(array $points, string $seedKey): array
    {
        $seed = crc32($seedKey);
        $cumulative = str_contains($seedKey, 'proSubs')
            || str_contains($seedKey, 'openTickets')
            || str_contains($seedKey, 'pendingComments');
        $n = max(1, count($points));
        $running = 4 + ($seed % 6);

        foreach ($points as $i => $p) {
            $wave = max(1, (int) round(7 + 5 * sin(($i / $n) * 6.2832 + ($seed % 7))));

            if ($cumulative) {
                $running += (int) max(1, round($wave / 2));
                $value = $running;
            } else {
                $value = $wave + ($seed % 3);
            }

            if (array_key_exists('value', $p)) {
                $points[$i]['value'] = $value;
            } else {
                $points[$i]['revenue'] = $value * (6 + ($seed % 5));
                $points[$i]['cost'] = round($value * (1.2 + ($seed % 3) * 0.3), 2);
            }
        }

        return $points;
    }

    private function buildSeriesFromQuery(
        $baseQuery,
        string $dateColumn,
        $now,
        string $startDate,
        string $aggregation = 'count',
        ?string $sumColumn = null
    ): array {
        $ranges = [
            'today' => ['start' => $now->copy()->startOfDay(), 'end' => $now->copy()->endOfDay(), 'interval' => 'hour'],
            '7d' => ['start' => $now->copy()->subDays(6)->startOfDay(), 'end' => $now->copy()->endOfDay(), 'interval' => 'day'],
            '30d' => ['start' => $this->thirtyDayStart($now), 'end' => $now->copy()->endOfDay(), 'interval' => 'day'],
            '90d' => ['start' => $now->copy()->subDays(89)->startOfDay(), 'end' => $now->copy()->endOfDay(), 'interval' => 'day'],
            'lifetime' => ['start' => $startDate, 'end' => $now->copy()->endOfDay(), 'interval' => 'month'],
        ];

        // Sum aggregations over fractional columns (cost_usd, credits_used, amount) must NOT
        // be truncated to int — a daily cost bucket is cents, so (int) collapsed the whole
        // day/hour series to zero (lifetime's monthly buckets were >= $1 so they survived,
        // which is why cost cards read $0 at 90d but non-zero All-Time). Counts stay integers.
        $castValue = fn ($v) => $aggregation === 'sum' ? round((float) ($v ?? 0), 2) : (int) ($v ?? 0);

        $result = [];
        foreach ($ranges as $key => $cfg) {
            $query = clone $baseQuery;
            $query->whereBetween($dateColumn, [$cfg['start'], $cfg['end']]);

            if ($cfg['interval'] === 'hour') {
                $query->selectRaw('HOUR(' . $dateColumn . ') as period, ' .
                    ($aggregation === 'sum' ? "SUM({$sumColumn}) as total" : 'COUNT(*) as total'));
                $query->groupByRaw('HOUR(' . $dateColumn . ')');
                $data = $query->pluck('total', 'period');

                $result[$key] = collect(range(0, 23))->map(function ($hour) use ($data, $now, $castValue) {
                    $slot = $now->copy()->startOfDay()->addHours($hour);
                    return [
                        'date' => $slot->format('H:00'),
                        'value' => $castValue($data[$hour] ?? 0),
                    ];
                })->values()->toArray();
            } elseif ($cfg['interval'] === 'day') {
                $query->selectRaw('DATE(' . $dateColumn . ') as period, ' .
                    ($aggregation === 'sum' ? "SUM({$sumColumn}) as total" : 'COUNT(*) as total'));
                $query->groupByRaw('DATE(' . $dateColumn . ')');
                $data = $query->pluck('total', 'period');

                $days = $cfg['start']->diffInDays($cfg['end']);
                $result[$key] = collect(range(0, $days))->map(function ($daysAgo) use ($data, $cfg, $castValue) {
                    $date = $cfg['end']->copy()->subDays($daysAgo)->toDateString();
                    return [
                        'date' => $date,
                        'value' => $castValue($data[$date] ?? 0),
                    ];
                })->values()->toArray();
            } else { // month
                $query->selectRaw('DATE_FORMAT(' . $dateColumn . ', "%Y-%m") as period, ' .
                    ($aggregation === 'sum' ? "SUM({$sumColumn}) as total" : 'COUNT(*) as total'));
                $query->groupByRaw('DATE_FORMAT(' . $dateColumn . ', "%Y-%m")');
                $data = $query->pluck('total', 'period');

                $cursor = now()->parse($startDate)->copy()->startOfMonth();
                $result[$key] = [];
                while ($cursor->lte($now)) {
                    $monthKey = $cursor->format('Y-m');
                    $result[$key][] = [
                        'date' => $cursor->format('M Y'),
                        'value' => $castValue($data[$monthKey] ?? 0),
                    ];
                    $cursor->addMonth();
                }
            }
        }

        return $result;
    }

    private function buildDualSeriesFromQuery(
        $baseQueryA,
        string $dateColumnA,
        $baseQueryB,
        string $dateColumnB,
        $now,
        string $startDate,
        string $aggregationA = 'count',
        ?string $sumColumnA = null,
        string $aggregationB = 'count',
        ?string $sumColumnB = null
    ): array {
        $seriesA = $this->buildSeriesFromQuery($baseQueryA, $dateColumnA, $now, $startDate, $aggregationA, $sumColumnA);
        $seriesB = $this->buildSeriesFromQuery($baseQueryB, $dateColumnB, $now, $startDate, $aggregationB, $sumColumnB);

        $result = [];
        foreach ($seriesA as $key => $points) {
            $result[$key] = collect($points)->map(function ($pointA, $index) use ($seriesB, $key) {
                $pointB = $seriesB[$key][$index] ?? ['date' => $pointA['date'], 'value' => 0];
                return [
                    'date' => $pointA['date'],
                    'revenue' => $pointA['value'],
                    'cost' => $pointB['value'],
                ];
            })->values()->toArray();
        }

        return $result;
    }

    private function buildCumulativeSeries($baseQuery, string $dateColumn, $now, string $startDate): array
    {
        $ranges = [
            'today' => ['start' => $now->copy()->startOfDay(), 'end' => $now->copy()->endOfDay(), 'interval' => 'hour'],
            '7d' => ['start' => $now->copy()->subDays(6)->startOfDay(), 'end' => $now->copy()->endOfDay(), 'interval' => 'day'],
            '30d' => ['start' => $this->thirtyDayStart($now), 'end' => $now->copy()->endOfDay(), 'interval' => 'day'],
            '90d' => ['start' => $now->copy()->subDays(89)->startOfDay(), 'end' => $now->copy()->endOfDay(), 'interval' => 'day'],
            'lifetime' => ['start' => $startDate, 'end' => $now->copy()->endOfDay(), 'interval' => 'month'],
        ];

        $result = [];
        foreach ($ranges as $key => $cfg) {
            // Get total count before the period starts
            $baseCount = (clone $baseQuery)->where($dateColumn, '<', $cfg['start'])->count();
            
            // Get counts within the period
            $query = clone $baseQuery;
            $query->whereBetween($dateColumn, [$cfg['start'], $cfg['end']]);

            if ($cfg['interval'] === 'hour') {
                $query->selectRaw('HOUR(' . $dateColumn . ') as period, COUNT(*) as total');
                $query->groupByRaw('HOUR(' . $dateColumn . ')');
                $data = $query->pluck('total', 'period');
                
                $cumulative = $baseCount;
                $result[$key] = collect(range(0, 23))->map(function ($hour) use ($data, $now, &$cumulative) {
                    $slot = $now->copy()->startOfDay()->addHours($hour);
                    $cumulative += (int) ($data[$hour] ?? 0);
                    return [
                        'date' => $slot->format('H:00'),
                        'value' => $cumulative,
                    ];
                })->values()->toArray();
            } elseif ($cfg['interval'] === 'day') {
                $query->selectRaw('DATE(' . $dateColumn . ') as period, COUNT(*) as total');
                $query->groupByRaw('DATE(' . $dateColumn . ')');
                $data = $query->pluck('total', 'period');
                
                $days = $cfg['start']->diffInDays($cfg['end']);
                $cumulative = $baseCount;
                $result[$key] = collect(range(0, $days))->map(function ($daysAgo) use ($data, $cfg, &$cumulative) {
                    $date = $cfg['end']->copy()->subDays($daysAgo)->toDateString();
                    $cumulative += (int) ($data[$date] ?? 0);
                    return [
                        'date' => $date,
                        'value' => $cumulative,
                    ];
                })->values()->toArray();
            } else { // month
                $query->selectRaw('DATE_FORMAT(' . $dateColumn . ', "%Y-%m") as period, COUNT(*) as total');
                $query->groupByRaw('DATE_FORMAT(' . $dateColumn . ', "%Y-%m")');
                $data = $query->pluck('total', 'period');
                
                $cursor = now()->parse($startDate)->copy()->startOfMonth();
                $result[$key] = [];
                $cumulative = $baseCount;
                while ($cursor->lte($now)) {
                    $monthKey = $cursor->format('Y-m');
                    $cumulative += (int) ($data[$monthKey] ?? 0);
                    $result[$key][] = [
                        'date' => $cursor->format('M Y'),
                        'value' => $cumulative,
                    ];
                    $cursor->addMonth();
                }
            }
        }

        return $result;
    }

    private function buildChurnedSeries($baseQuery, $now, string $startDate): array
    {
        $ranges = [
            'today' => ['start' => $now->copy()->startOfDay(), 'end' => $now->copy()->endOfDay(), 'interval' => 'hour'],
            '7d' => ['start' => $now->copy()->subDays(6)->startOfDay(), 'end' => $now->copy()->endOfDay(), 'interval' => 'day'],
            '30d' => ['start' => $this->thirtyDayStart($now), 'end' => $now->copy()->endOfDay(), 'interval' => 'day'],
            '90d' => ['start' => $now->copy()->subDays(89)->startOfDay(), 'end' => $now->copy()->endOfDay(), 'interval' => 'day'],
            'lifetime' => ['start' => $startDate, 'end' => $now->copy()->endOfDay(), 'interval' => 'month'],
        ];

        $result = [];
        foreach ($ranges as $key => $cfg) {
            $query = clone $baseQuery;
            $query->where(function ($subQuery) {
                $subQuery->whereNotNull('cancelled_at')
                    ->orWhereIn('status', ['expired', 'past_due']);
            });

            if ($cfg['interval'] === 'hour') {
                $query->where(function ($q) use ($cfg) {
                    $q->whereBetween('cancelled_at', [$cfg['start'], $cfg['end']])
                      ->orWhereBetween('current_period_end', [$cfg['start'], $cfg['end']]);
                });
                $query->selectRaw('COALESCE(HOUR(cancelled_at), HOUR(current_period_end)) as period, COUNT(*) as total');
                $query->groupByRaw('period');
                $data = $query->pluck('total', 'period');
                
                $result[$key] = collect(range(0, 23))->map(function ($hour) use ($data, $now) {
                    $slot = $now->copy()->startOfDay()->addHours($hour);
                    return [
                        'date' => $slot->format('H:00'),
                        'value' => (int) ($data[$hour] ?? 0),
                    ];
                })->values()->toArray();
            } elseif ($cfg['interval'] === 'day') {
                $query->where(function ($q) use ($cfg) {
                    $q->whereBetween('cancelled_at', [$cfg['start'], $cfg['end']])
                      ->orWhereBetween('current_period_end', [$cfg['start'], $cfg['end']]);
                });
                $query->selectRaw('COALESCE(DATE(cancelled_at), DATE(current_period_end)) as period, COUNT(*) as total');
                $query->groupByRaw('period');
                $data = $query->pluck('total', 'period');
                
                $days = $cfg['start']->diffInDays($cfg['end']);
                $result[$key] = collect(range(0, $days))->map(function ($daysAgo) use ($data, $cfg) {
                    $date = $cfg['end']->copy()->subDays($daysAgo)->toDateString();
                    return [
                        'date' => $date,
                        'value' => (int) ($data[$date] ?? 0),
                    ];
                })->values()->toArray();
            } else { // month
                $query->where(function ($q) use ($cfg) {
                    $q->whereBetween('cancelled_at', [$cfg['start'], $cfg['end']])
                      ->orWhereBetween('current_period_end', [$cfg['start'], $cfg['end']]);
                });
                $query->selectRaw('COALESCE(DATE_FORMAT(cancelled_at, "%Y-%m"), DATE_FORMAT(current_period_end, "%Y-%m")) as period, COUNT(*) as total');
                $query->groupByRaw('period');
                $data = $query->pluck('total', 'period');
                
                $cursor = now()->parse($startDate)->copy()->startOfMonth();
                $result[$key] = [];
                while ($cursor->lte($now)) {
                    $monthKey = $cursor->format('Y-m');
                    $result[$key][] = [
                        'date' => $cursor->format('M Y'),
                        'value' => (int) ($data[$monthKey] ?? 0),
                    ];
                    $cursor->addMonth();
                }
            }
        }

        return $result;
    }

    private function buildActiveSubscriptionsSeries($baseQuery, $now, string $startDate): array
    {
        $ranges = [
            'today' => ['start' => $now->copy()->startOfDay(), 'end' => $now->copy()->endOfDay(), 'interval' => 'hour'],
            '7d' => ['start' => $now->copy()->subDays(6)->startOfDay(), 'end' => $now->copy()->endOfDay(), 'interval' => 'day'],
            '30d' => ['start' => $this->thirtyDayStart($now), 'end' => $now->copy()->endOfDay(), 'interval' => 'day'],
            '90d' => ['start' => $now->copy()->subDays(89)->startOfDay(), 'end' => $now->copy()->endOfDay(), 'interval' => 'day'],
            'lifetime' => ['start' => $startDate, 'end' => $now->copy()->endOfDay(), 'interval' => 'month'],
        ];

        $result = [];
        foreach ($ranges as $key => $cfg) {
            // Get base count of users who subscribed before the period
            $baseCount = (clone $baseQuery)
                ->where('created_at', '<', $cfg['start'])
                ->where('subscription_ends_at', '>=', $cfg['start'])
                ->count();

            // Get new subscriptions in the period
            $newQuery = clone $baseQuery;
            $newQuery->whereBetween('created_at', [$cfg['start'], $cfg['end']]);

            if ($cfg['interval'] === 'hour') {
                $newQuery->selectRaw('HOUR(created_at) as period, COUNT(*) as total');
                $newQuery->groupByRaw('HOUR(created_at)');
                $newData = $newQuery->pluck('total', 'period');

                // Get cancellations in the period
                $cancelQuery = $baseQuery->getModel()->newQuery()
                    ->where('subscription_status', '!=', 'active')
                    ->whereBetween('subscription_ends_at', [$cfg['start'], $cfg['end']]);
                $cancelQuery->selectRaw('HOUR(subscription_ends_at) as period, COUNT(*) as total');
                $cancelQuery->groupByRaw('HOUR(subscription_ends_at)');
                $cancelData = $cancelQuery->pluck('total', 'period');

                $cumulative = $baseCount;
                $result[$key] = collect(range(0, 23))->map(function ($hour) use ($newData, $cancelData, $now, &$cumulative) {
                    $slot = $now->copy()->startOfDay()->addHours($hour);
                    $cumulative += (int) ($newData[$hour] ?? 0);
                    $cumulative -= (int) ($cancelData[$hour] ?? 0);
                    $cumulative = max(0, $cumulative);
                    return [
                        'date' => $slot->format('H:00'),
                        'value' => $cumulative,
                    ];
                })->values()->toArray();
            } elseif ($cfg['interval'] === 'day') {
                $newQuery->selectRaw('DATE(created_at) as period, COUNT(*) as total');
                $newQuery->groupByRaw('DATE(created_at)');
                $newData = $newQuery->pluck('total', 'period');

                $cancelQuery = $baseQuery->getModel()->newQuery()
                    ->where('subscription_status', '!=', 'active')
                    ->whereBetween('subscription_ends_at', [$cfg['start'], $cfg['end']]);
                $cancelQuery->selectRaw('DATE(subscription_ends_at) as period, COUNT(*) as total');
                $cancelQuery->groupByRaw('DATE(subscription_ends_at)');
                $cancelData = $cancelQuery->pluck('total', 'period');

                $days = $cfg['start']->diffInDays($cfg['end']);
                $cumulative = $baseCount;
                $result[$key] = collect(range(0, $days))->map(function ($daysAgo) use ($newData, $cancelData, $cfg, &$cumulative) {
                    $date = $cfg['end']->copy()->subDays($daysAgo)->toDateString();
                    $cumulative += (int) ($newData[$date] ?? 0);
                    $cumulative -= (int) ($cancelData[$date] ?? 0);
                    $cumulative = max(0, $cumulative);
                    return [
                        'date' => $date,
                        'value' => $cumulative,
                    ];
                })->values()->toArray();
            } else { // month
                $newQuery->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as period, COUNT(*) as total');
                $newQuery->groupByRaw('DATE_FORMAT(created_at, "%Y-%m")');
                $newData = $newQuery->pluck('total', 'period');

                $cancelQuery = $baseQuery->getModel()->newQuery()
                    ->where('subscription_status', '!=', 'active')
                    ->whereBetween('subscription_ends_at', [$cfg['start'], $cfg['end']]);
                $cancelQuery->selectRaw('DATE_FORMAT(subscription_ends_at, "%Y-%m") as period, COUNT(*) as total');
                $cancelQuery->groupByRaw('DATE_FORMAT(subscription_ends_at, "%Y-%m")');
                $cancelData = $cancelQuery->pluck('total', 'period');

                $cursor = now()->parse($startDate)->copy()->startOfMonth();
                $result[$key] = [];
                $cumulative = $baseCount;
                while ($cursor->lte($now)) {
                    $monthKey = $cursor->format('Y-m');
                    $cumulative += (int) ($newData[$monthKey] ?? 0);
                    $cumulative -= (int) ($cancelData[$monthKey] ?? 0);
                    $cumulative = max(0, $cumulative);
                    $result[$key][] = [
                        'date' => $cursor->format('M Y'),
                        'value' => $cumulative,
                    ];
                    $cursor->addMonth();
                }
            }
        }

        return $result;
    }

    private function buildEmptySeries($now, string $startDate): array
    {
        $ranges = [
            'today' => ['start' => $now->copy()->startOfDay(), 'end' => $now->copy()->endOfDay(), 'interval' => 'hour'],
            '7d' => ['start' => $now->copy()->subDays(6)->startOfDay(), 'end' => $now->copy()->endOfDay(), 'interval' => 'day'],
            '30d' => ['start' => $this->thirtyDayStart($now), 'end' => $now->copy()->endOfDay(), 'interval' => 'day'],
            '90d' => ['start' => $now->copy()->subDays(89)->startOfDay(), 'end' => $now->copy()->endOfDay(), 'interval' => 'day'],
            'lifetime' => ['start' => $startDate, 'end' => $now->copy()->endOfDay(), 'interval' => 'month'],
        ];

        $result = [];
        foreach ($ranges as $key => $cfg) {
            if ($cfg['interval'] === 'hour') {
                $result[$key] = collect(range(0, 23))->map(function ($hour) use ($now) {
                    $slot = $now->copy()->startOfDay()->addHours($hour);
                    return ['date' => $slot->format('H:00'), 'value' => 0];
                })->values()->toArray();
            } elseif ($cfg['interval'] === 'day') {
                $days = $cfg['start']->diffInDays($cfg['end']);
                $result[$key] = collect(range(0, $days))->map(function ($daysAgo) use ($cfg) {
                    $date = $cfg['end']->copy()->subDays($daysAgo)->toDateString();
                    return ['date' => $date, 'value' => 0];
                })->values()->toArray();
            } else {
                $cursor = now()->parse($startDate)->copy()->startOfMonth();
                $result[$key] = [];
                while ($cursor->lte($now)) {
                    $result[$key][] = ['date' => $cursor->format('M Y'), 'value' => 0];
                    $cursor->addMonth();
                }
            }
        }

        return $result;
    }

    private function dashboardPeriod(string $period): string
    {
        return in_array($period, ['today', '7d', '30d', '90d', 'lifetime'], true) ? $period : '7d';
    }

    private function dashboardPeriodRange($now, string $period, ?string $lifetimeStart = null): array
    {
        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            '30d' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            '90d' => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
            'lifetime' => [$lifetimeStart ? now()->parse($lifetimeStart)->startOfMonth() : $now->copy()->subYear(), $now->copy()->endOfDay()],
            default => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    private function trafficSources($now, string $period = '30d', ?string $lifetimeStart = null): array
    {
        [$start, $end] = $this->dashboardPeriodRange($now, $period, $lifetimeStart);

        $socialProviders = User::whereNotNull('oauth_provider')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('oauth_provider, COUNT(*) as count')
            ->groupBy('oauth_provider')
            ->orderByDesc('count')
            ->get();

        $referred = User::whereNotNull('referred_by')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $emailLogin = LoginHistory::where('success', true)
            ->whereBetween('created_at', [$start, $end])
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

    private function getAddonStats(): array
    {
        $addonPath = base_path('addons');
        $totalAddons = 0;
        $activeAddons = 0;

        if (is_dir($addonPath)) {
            $dirs = glob($addonPath . '/*', GLOB_ONLYDIR);
            $totalAddons = count($dirs);

            foreach ($dirs as $dir) {
                $jsonFile = $dir . '/addon.json';
                if (file_exists($jsonFile)) {
                    $config = json_decode(file_get_contents($jsonFile), true);
                    if (isset($config['is_active']) && $config['is_active']) {
                        $activeAddons++;
                    }
                }
            }
        }

        $recentlyActivated = \DB::table('admin_audit_logs')
            ->where('action', 'like', '%addon%activat%')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return [
            'total' => $totalAddons,
            'active' => $activeAddons,
            'recently_activated' => $recentlyActivated,
        ];
    }

    private function getRecentActivity(int $limit = 20, $start = null, $end = null): array
    {
        return $this->getRecentActivityCollection($start, $end)
            ->take($limit)
            ->values()
            ->toArray();
    }

    private function getRecentActivityCollection($start = null, $end = null): Collection
    {
        $activities = collect();
        $toolNames = AiTool::pluck('name', 'slug');
        $modelNames = AiModel::pluck('name', 'slug');

        $userQuery = User::query()->latest();
        if ($start && $end) {
            $userQuery->whereBetween('created_at', [$start, $end]);
        }
        if (! ($start && $end)) {
            $userQuery->take(8);
        }
        $userQuery->get()->each(function ($user) use (&$activities) {
            $activities->push([
                'type' => 'user_registered',
                'icon' => 'user',
                'title' => $user->name,
                'detail' => $user->email,
                'time' => $user->created_at,
            ]);
        });

        $paymentQuery = Payment::with('user')->where('status', 'completed')->latest();
        if ($start && $end) {
            $paymentQuery->whereBetween('created_at', [$start, $end]);
        }
        if (! ($start && $end)) {
            $paymentQuery->take(8);
        }
        $paymentQuery->get()->each(function ($payment) use (&$activities) {
            $activities->push([
                'type' => $payment->type === 'subscription' ? 'subscription' : 'payment',
                'icon' => 'dollar',
                'title' => $payment->user?->name ?? translate('Unknown'),
                'detail' => number_format($payment->amount, 2).' '.$payment->currency,
                'time' => $payment->created_at,
            ]);
        });

        $usageQuery = AiUsageLog::with('user')->latest();
        if ($start && $end) {
            $usageQuery->whereBetween('created_at', [$start, $end]);
        }
        if (! ($start && $end)) {
            $usageQuery->take(8);
        }
        $usageQuery->get()->each(function ($log) use (&$activities, $toolNames, $modelNames) {
            $toolLabel = $log->tool_slug ? ($toolNames[$log->tool_slug] ?? $log->tool_slug) : null;
            $modelLabel = $log->model ? ($modelNames[$log->model] ?? $log->model) : null;
            $providerLabel = $this->formatProviderLabel($log->provider);
            $fallbackModelLabel = $this->formatModelLabel($log->model, $log->provider);

            $activities->push([
                'type' => 'ai_request',
                'icon' => 'spark',
                'title' => $log->user?->name ?? translate('Unknown'),
                'detail' => ($toolLabel ?? $providerLabel.' / '.($modelLabel ?? $fallbackModelLabel)).' — '.number_format($log->credits_used, 2).' credits',
                'time' => $log->created_at,
            ]);
        });

        $referralQuery = AffiliateReferral::with(['referrer', 'referred'])->whereNotNull('converted_at')->latest('converted_at');
        if ($start && $end) {
            $referralQuery->whereBetween('converted_at', [$start, $end]);
        }
        if (! ($start && $end)) {
            $referralQuery->take(8);
        }
        $referralQuery->get()->each(function ($ref) use (&$activities) {
            $activities->push([
                'type' => 'referral',
                'icon' => 'user',
                'title' => $ref->referrer?->name ?? translate('Unknown'),
                'detail' => $ref->referred?->email ?? translate('Unknown'),
                'time' => $ref->converted_at,
            ]);
        });

        return $activities->sortByDesc('time')->values();
    }

    private function paginateActivityCollection(Collection $activities, int $perPage = 20): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = $activities->values();
        $results = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator($results, $items->count(), $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    private function formatProviderLabel(?string $provider): string
    {
        if (! $provider) {
            return translate('Unknown');
        }

        return match (strtolower($provider)) {
            'openrouter' => 'OpenRouter',
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'google' => 'Google',
            default => Str::headline($provider),
        };
    }

    private function formatModelLabel(?string $model, ?string $provider = null): string
    {
        if (! $model) {
            return translate('Unknown');
        }

        $cleaned = Str::afterLast($model, '/');
        $cleaned = preg_replace('/-\d{8}$/', '', $cleaned) ?? $cleaned;

        return Str::of($cleaned)
            ->replace(['_', '.'], ' ')
            ->replace('-', ' ')
            ->headline()
            ->toString();
    }
}

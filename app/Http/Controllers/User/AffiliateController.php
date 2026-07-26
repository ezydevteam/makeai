<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;

use App\Http\Requests\AffiliateAliasRequest;
use App\Http\Requests\AffiliatePayoutRequest;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\AffiliateReferral;
use App\Models\Page;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\NotificationEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateController extends Controller
{
    public function __construct(private readonly AffiliateService $affiliate) {}

    public function capture(Request $request, string $code): RedirectResponse
    {
        $this->affiliate->captureVisit($request, $code);

        return redirect()->route('register');
    }

    public function dashboard(Request $request): Response
    {

        $user = $request->user();
        $program = $this->affiliate->program();
        $availableBalance = $this->affiliate->availableBalance($user);
        $termsPage = $program->terms_page_slug
            ? Page::query()->published()->where('slug', $program->terms_page_slug)->first(['title', 'slug'])
            : null;
        $totalReferrals = AffiliateReferral::where('referrer_id', $user->id)->whereNotNull('referred_id')->count();
        $conversions = AffiliateCommission::where('referrer_id', $user->id)->distinct('referred_id')->count('referred_id');

        // Pre-aggregate commission totals per referred user in ONE query, so the
        // referrals list below doesn't fire a SUM() query per row (N+1).
        $commissionByReferred = AffiliateCommission::where('referrer_id', $user->id)
            ->whereNotNull('referred_id')
            ->selectRaw('referred_id, SUM(amount) as total')
            ->groupBy('referred_id')
            ->pluck('total', 'referred_id');

        return Inertia::render('User/Affiliate', [
            'program' => [
                'commission_type' => $program->commission_type,
                'commission_value' => (float) $program->commission_value,
                'min_payout' => (float) $program->min_payout,
                'max_payout' => (float) ($program->max_payout ?? 0),
                'payouts_enabled' => (bool) $program->payouts_enabled,
                'payout_methods' => $program->payout_methods ?: ['paypal', 'bank_transfer', 'credits'],
                'commission_hold_days' => (int) $program->commission_hold_days,
                'allow_custom_alias' => (bool) $program->allow_custom_alias,
                'terms_page' => $termsPage ? [
                    'title' => $termsPage->title,
                    'url' => route('page.show', $termsPage->slug),
                ] : null,
            ],
            'availableBalance' => $availableBalance,
            'creditPricePerUnit' => (float) settings('credit_price_per_unit', 0.01),
            // Amounts are sent as raw floats (the frontend formats them once). Sending
            // format_currency() strings here caused a double-format on any currency whose
            // formatted string isn't cleanly parseable by the frontend (trailing symbol,
            // comma decimals, space thousands).
            'stats' => [
                'total_earnings' => (float) AffiliateCommission::where('referrer_id', $user->id)->whereIn('status', ['approved', 'paid'])->sum('amount'),
                'pending_earnings' => (float) AffiliateCommission::where('referrer_id', $user->id)->where('status', 'pending')->sum('amount'),
                'total_referrals' => $totalReferrals,
                'successful_conversions' => $conversions,
            ],
            'referral' => [
                'code' => $user->referral_code,
                'custom_slug' => $user->affiliate_custom_slug,
                'link' => route('register', ['ref' => $user->referral_code]),
                'alias_link' => $user->affiliate_custom_slug ? route('affiliate.capture', $user->affiliate_custom_slug) : null,
            ],
            'chart' => $this->getChartData($user->id, '1M'),
            // The commissions status filter is applied server-side (it has to be: the list is
            // paginated, so filtering the loaded page only ever searched the newest 10 rows).
            'filters' => ['status' => $request->query('status')],
            'marketing' => [
                'banners' => $program->marketing_banners ?: [],
                'emails' => $program->promotional_emails ?: [],
                'posts' => $program->social_posts ?: [],
            ],
            // Paginated, not ->limit(50): an established affiliate has hundreds of referrals
            // and the rest were simply unreachable. Each list on this page needs its OWN page
            // parameter — with the default `page` they share one, so paging the commissions
            // table silently moved the referrals table too.
            'referrals' => tap(AffiliateReferral::query()
                ->with('referred:id,email')
                ->where('referrer_id', $user->id)
                ->latest()
                ->paginate(10, ['*'], 'referrals_page')
                ->withQueryString(), function ($paginator) use ($commissionByReferred) {
                    $paginator->getCollection()->transform(fn (AffiliateReferral $referral) => [
                        'email' => $referral->referred ? $this->maskEmail($referral->referred->email) : translate('Pending'),
                        'joined_at' => $referral->converted_at?->toDateString(),
                        'status' => $referral->referred_id ? 'registered' : 'clicked',
                        'commission' => (float) ($commissionByReferred[$referral->referred_id] ?? 0),
                    ]);
                }),
            'commissions' => tap(AffiliateCommission::query()
                ->with('payment:id,ulid,amount,currency')
                ->where('referrer_id', $user->id)
                ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
                ->latest()
                // withQueryString(), or paging while filtered drops the status and lands the
                // user back in the unfiltered list.
                ->paginate(10, ['*'], 'commissions_page')->withQueryString(), function ($paginator) {
                    $paginator->getCollection()->transform(fn (AffiliateCommission $commission) => [
                        'id' => $commission->id,
                        'amount' => (float) $commission->amount,
                        'status' => $commission->status,
                        'created_at' => $commission->created_at?->toIso8601String(),
                        'payment' => $commission->payment ? [
                            'amount' => format_currency((float) $commission->payment->amount, $commission->payment->currency),
                            'currency' => $commission->payment->currency,
                        ] : null,
                    ]);
                }),
            'payouts' => tap(AffiliatePayout::query()
                ->where('user_id', $user->id)
                ->latest()
                // Own page parameter for the same reason as the two lists above, even though
                // this table has no pager yet — otherwise it silently follows theirs.
                ->paginate(10, ['*'], 'payouts_page')->withQueryString(), function ($paginator) {
                    $paginator->getCollection()->transform(fn (AffiliatePayout $payout) => [
                        'id' => $payout->id,
                        'amount' => (float) $payout->amount,
                        'method' => $payout->method,
                        'status' => $payout->status,
                        'created_at' => $payout->created_at?->toIso8601String(),
                    ]);
                }),
        ]);
    }

    public function storePayout(AffiliatePayoutRequest $request): RedirectResponse|JsonResponse
    {
        $program = $this->affiliate->program();
        abort_unless((bool) $program->payouts_enabled, 404);

        $user = $request->user();
        abort_unless(! $user->is_banned && ! $user->affiliate_banned, 403);

        $amount = (float) $request->validated('amount');
        $maxPayout = (float) ($program->max_payout ?? 0);

        if ($maxPayout > 0 && $amount > $maxPayout) {
            return $this->payoutError($request, 'PAYOUT_AMOUNT_EXCEEDS_MAX', translate('Amount exceeds the maximum payout limit.'), 'amount');
        }

        // Atomic balance check + payout creation: lock the user row so two
        // concurrent requests cannot both read the same balance and overdraw.
        $exceedsBalance = false;
        $payout = DB::transaction(function () use ($user, $amount, $request, &$exceedsBalance) {
            $locked = User::lockForUpdate()->find($user->id);
            $available = $this->affiliate->availableBalance($locked);

            if ($amount > $available) {
                $exceedsBalance = true;

                return null;
            }

            return AffiliatePayout::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'method' => $request->validated('method'),
                'payout_details' => $request->validated('details'),
                'status' => 'pending',
            ]);
        });

        if ($exceedsBalance || ! $payout) {
            return $this->payoutError($request, 'PAYOUT_AMOUNT_EXCEEDS_BALANCE', translate('Requested amount exceeds available balance.'), 'amount');
        }

        app(NotificationEventService::class)->payoutRequested($payout);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => ['available_balance' => $this->affiliate->availableBalance($user)],
                'message' => translate('Payout request submitted successfully.'),
            ]);
        }

        return back()->with('success', translate('Payout request submitted successfully.'));
    }

    protected function payoutError(Request $request, string $code, string $message, string $field): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'code' => $code,
                'message' => $message,
            ], 422);
        }

        return back()->withErrors([$field => $message]);
    }

    public function updateAlias(AffiliateAliasRequest $request): RedirectResponse
    {
        abort_unless((bool) $this->affiliate->program()->allow_custom_alias, 404);

        $request->user()->update([
            'affiliate_custom_slug' => $request->validated('custom_slug') ?: null,
        ]);

        return back()->with('success', translate('Affiliate alias updated successfully.'));
    }

    public function api(Request $request): array
    {

        return [
            'success' => true,
            'data' => [
                'referral_code' => $request->user()->referral_code,
                'referral_link' => route('register', ['ref' => $request->user()->referral_code]),
                'available_balance' => $this->affiliate->availableBalance($request->user()),
            ],
            'message' => translate('Done'),
        ];
    }

    public function referralsApi(Request $request): array
    {

        return [
            'success' => true,
            'data' => AffiliateReferral::query()
                ->where('referrer_id', $request->user()->id)
                ->latest()
                ->paginate(20),
            'message' => translate('Done'),
        ];
    }

    public function commissionsApi(Request $request): array
    {

        return [
            'success' => true,
            'data' => AffiliateCommission::query()
                ->where('referrer_id', $request->user()->id)
                ->latest()
                ->paginate(20),
            'message' => translate('Done'),
        ];
    }

    public function payoutsApi(Request $request): array
    {

        return [
            'success' => true,
            'data' => tap(AffiliatePayout::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->paginate(20), function ($paginator) {
                    $paginator->getCollection()->transform(fn (AffiliatePayout $payout) => [
                        'id' => $payout->id,
                        'amount' => format_currency((float) $payout->amount),
                        'method' => $payout->method,
                        'status' => $payout->status,
                        'created_at' => $payout->created_at?->toIso8601String(),
                        'processed_at' => $payout->processed_at?->toIso8601String(),
                    ]);
                }),
            'message' => translate('Done'),
        ];
    }

    public function chartData(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $period = $request->input('period', '1M');
        $data = $this->getChartData($user->id, $period);
        return response()->json($data);
    }

    private function getChartData(int $userId, string $period): array
    {
        switch ($period) {
            case '1D':
                $hours = collect(range(23, 0))->map(fn (int $hoursAgo) => now()->subHours($hoursAgo));
                return $hours->map(function ($time) use ($userId) {
                    $start = $time->copy()->startOfHour();
                    $end = $time->copy()->endOfHour();
                    return [
                        'label' => $time->translatedFormat('M j, g A'),
                        'clicks' => AffiliateReferral::where('referrer_id', $userId)->whereBetween('landed_at', [$start, $end])->count(),
                        'registrations' => AffiliateReferral::where('referrer_id', $userId)->whereBetween('converted_at', [$start, $end])->count(),
                        'conversions' => AffiliateCommission::where('referrer_id', $userId)->whereBetween('created_at', [$start, $end])->count(),
                        'is_current' => $time->isCurrentHour(),
                    ];
                })->values()->toArray();

            case '7D':
                $days = collect(range(6, 0))->map(fn (int $daysAgo) => now()->subDays($daysAgo));
                return $days->map(function ($date) use ($userId) {
                    $start = $date->copy()->startOfDay();
                    $end = $date->copy()->endOfDay();
                    return [
                        'label' => $date->translatedFormat('M j'),
                        'clicks' => AffiliateReferral::where('referrer_id', $userId)->whereBetween('landed_at', [$start, $end])->count(),
                        'registrations' => AffiliateReferral::where('referrer_id', $userId)->whereBetween('converted_at', [$start, $end])->count(),
                        'conversions' => AffiliateCommission::where('referrer_id', $userId)->whereBetween('created_at', [$start, $end])->count(),
                        'is_current' => $date->isToday(),
                    ];
                })->values()->toArray();

            case '1Y':
                $months = collect(range(11, 0))->map(fn (int $monthsAgo) => now()->subMonths($monthsAgo));
                return $months->map(function ($month) use ($userId) {
                    $start = $month->copy()->startOfMonth();
                    $end = $month->copy()->endOfMonth();
                    return [
                        'label' => $month->translatedFormat('M Y'),
                        'clicks' => AffiliateReferral::where('referrer_id', $userId)->whereBetween('landed_at', [$start, $end])->count(),
                        'registrations' => AffiliateReferral::where('referrer_id', $userId)->whereBetween('converted_at', [$start, $end])->count(),
                        'conversions' => AffiliateCommission::where('referrer_id', $userId)->whereBetween('created_at', [$start, $end])->count(),
                        'is_current' => $month->isCurrentMonth(),
                    ];
                })->values()->toArray();

            case '1M':
            default:
                $days = collect(range(29, 0))->map(fn (int $daysAgo) => now()->subDays($daysAgo));
                return $days->map(function ($date) use ($userId) {
                    $start = $date->copy()->startOfDay();
                    $end = $date->copy()->endOfDay();
                    return [
                        'label' => $date->translatedFormat('M j'),
                        'clicks' => AffiliateReferral::where('referrer_id', $userId)->whereBetween('landed_at', [$start, $end])->count(),
                        'registrations' => AffiliateReferral::where('referrer_id', $userId)->whereBetween('converted_at', [$start, $end])->count(),
                        'conversions' => AffiliateCommission::where('referrer_id', $userId)->whereBetween('created_at', [$start, $end])->count(),
                        'is_current' => $date->isToday(),
                    ];
                })->values()->toArray();
        }
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($name, 0, 1).'***@'.$domain;
    }
}

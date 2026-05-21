<?php

namespace App\Http\Controllers;

use App\Http\Requests\AffiliateAliasRequest;
use App\Http\Requests\AffiliatePayoutRequest;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\AffiliateReferral;
use App\Models\Page;
use App\Services\AffiliateService;
use App\Services\NotificationEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        abort_unless($this->affiliate->isEnabled(), 404);

        $user = $request->user();
        $program = $this->affiliate->program();
        $availableBalance = $this->affiliate->availableBalance($user);
        $termsPage = $program->terms_page_slug
            ? Page::query()->published()->where('slug', $program->terms_page_slug)->first(['title', 'slug'])
            : null;
        $totalReferrals = AffiliateReferral::where('referrer_id', $user->id)->whereNotNull('referred_id')->count();
        $conversions = AffiliateCommission::where('referrer_id', $user->id)->distinct('referred_id')->count('referred_id');
        $totalClicks = AffiliateReferral::where('referrer_id', $user->id)->count();

        return Inertia::render('Affiliate/Dashboard', [
            'program' => [
                'commission_type' => $program->commission_type,
                'commission_value' => (float) $program->commission_value,
                'min_payout' => (float) $program->min_payout,
                'payouts_enabled' => (bool) $program->payouts_enabled,
                'payout_methods' => $program->payout_methods ?: ['paypal', 'bank_transfer', 'credits'],
                'commission_hold_days' => (int) $program->commission_hold_days,
                'allow_custom_alias' => (bool) $program->allow_custom_alias,
                'terms_page' => $termsPage ? [
                    'title' => $termsPage->title,
                    'url' => route('page.show', $termsPage->slug),
                ] : null,
            ],
            'stats' => [
                'total_earnings' => (float) AffiliateCommission::where('referrer_id', $user->id)->whereIn('status', ['approved', 'paid'])->sum('amount'),
                'pending_earnings' => (float) AffiliateCommission::where('referrer_id', $user->id)->where('status', 'pending')->sum('amount'),
                'available_balance' => $availableBalance,
                'total_referrals' => $totalReferrals,
                'successful_conversions' => $conversions,
                'conversion_rate' => $totalClicks > 0 ? round(($conversions / $totalClicks) * 100, 2) : 0,
            ],
            'referral' => [
                'code' => $user->referral_code,
                'custom_slug' => $user->affiliate_custom_slug,
                'link' => route('register', ['ref' => $user->referral_code]),
                'alias_link' => $user->affiliate_custom_slug ? route('affiliate.capture', $user->affiliate_custom_slug) : null,
            ],
            'chart' => $this->chart($user->id),
            'referrals' => AffiliateReferral::query()
                ->with('referred:id,ulid,name,email,created_at')
                ->where('referrer_id', $user->id)
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (AffiliateReferral $referral) => [
                    'email' => $referral->referred ? $this->maskEmail($referral->referred->email) : translate('Pending'),
                    'joined_at' => $referral->converted_at?->toDateString(),
                    'status' => $referral->referred_id ? 'registered' : 'clicked',
                    'commission' => (float) AffiliateCommission::where('referrer_id', $user->id)
                        ->where('referred_id', $referral->referred_id)
                        ->sum('amount'),
                ]),
            'commissions' => AffiliateCommission::query()
                ->with('payment:id,ulid,amount,currency')
                ->where('referrer_id', $user->id)
                ->latest()
                ->paginate(10),
            'payouts' => AffiliatePayout::query()
                ->where('user_id', $user->id)
                ->latest()
                ->paginate(10),
        ]);
    }

    public function storePayout(AffiliatePayoutRequest $request): RedirectResponse|JsonResponse
    {
        abort_unless($this->affiliate->isEnabled(), 404);
        abort_unless((bool) $this->affiliate->program()->payouts_enabled, 404);

        $user = $request->user();
        $available = $this->affiliate->availableBalance($user);
        $amount = (float) $request->validated('amount');

        if ($amount > $available) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'code' => 'PAYOUT_AMOUNT_EXCEEDS_BALANCE',
                    'message' => translate('Requested amount exceeds available balance.'),
                ], 422);
            }

            return back()->withErrors(['amount' => translate('Requested amount exceeds available balance.')]);
        }

        $payout = AffiliatePayout::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'method' => $request->validated('method'),
            'payout_details' => $request->validated('details'),
        ]);

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

    public function updateAlias(AffiliateAliasRequest $request): RedirectResponse
    {
        abort_unless($this->affiliate->isEnabled(), 404);
        abort_unless((bool) $this->affiliate->program()->allow_custom_alias, 404);

        $request->user()->update([
            'affiliate_custom_slug' => $request->validated('custom_slug') ?: null,
        ]);

        return back()->with('success', translate('Affiliate alias updated successfully.'));
    }

    public function api(Request $request): array
    {
        abort_unless($this->affiliate->isEnabled(), 404);

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
        abort_unless($this->affiliate->isEnabled(), 404);

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
        abort_unless($this->affiliate->isEnabled(), 404);

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
        abort_unless($this->affiliate->isEnabled(), 404);

        return [
            'success' => true,
            'data' => AffiliatePayout::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->paginate(20),
            'message' => translate('Done'),
        ];
    }

    private function chart(int $userId): array
    {
        $days = collect(range(29, 0))->map(fn (int $daysAgo) => now()->subDays($daysAgo)->toDateString());

        return $days->map(function (string $date) use ($userId) {
            return [
                'date' => $date,
                'clicks' => AffiliateReferral::where('referrer_id', $userId)->whereDate('landed_at', $date)->count(),
                'registrations' => AffiliateReferral::where('referrer_id', $userId)->whereDate('converted_at', $date)->count(),
                'conversions' => AffiliateCommission::where('referrer_id', $userId)->whereDate('created_at', $date)->count(),
            ];
        })->values()->all();
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($name, 0, 1).'***@'.$domain;
    }
}

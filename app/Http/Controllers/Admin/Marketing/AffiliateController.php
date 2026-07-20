<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AffiliatePayoutProcessRequest;
use App\Http\Requests\Admin\AffiliateSettingsRequest;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\AffiliateProgram;
use App\Models\AffiliateReferral;
use App\Models\Page;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\NotificationEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateController extends Controller
{
    public function __construct(private readonly AffiliateService $affiliate) {}

    // ─── Pages ───────────────────────────────────────────────

    public function index(): Response
    {
        return Inertia::render('Admin/Marketing/Affiliate/Dashboard', [
            'stats' => $this->stats(),
            'program' => AffiliateProgram::current(),
            'recentCommissions' => $this->recentCommissions(5),
            'recentPayouts' => $this->recentPayouts(5),
            'topAffiliates' => $this->topAffiliates(5),
        ]);
    }

    public function commissionsIndex(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();

        $commissions = tap(AffiliateCommission::query()
            ->with(['referrer:id,ulid,name,email', 'referred:id,ulid,name,email', 'payment:id,ulid,amount,currency'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, function ($q) use ($search) {
                $q->whereHas('referrer', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('referred', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate(15), function ($paginator) {
                $paginator->getCollection()->transform(fn (AffiliateCommission $c) => $this->commissionToArray($c));
            });

        return Inertia::render('Admin/Marketing/Affiliate/Commissions', [
            'commissions' => $commissions,
            'filters' => ['search' => $search, 'status' => $status],
            'stats' => $this->stats(),
        ]);
    }

    public function payoutsIndex(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();

        $payouts = tap(AffiliatePayout::query()
            ->with(['user:id,ulid,name,email', 'processor:id,name'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, function ($q) use ($search) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate(15), function ($paginator) {
                $paginator->getCollection()->transform(fn (AffiliatePayout $p) => $this->payoutToArray($p));
            });

        return Inertia::render('Admin/Marketing/Affiliate/Payouts', [
            'payouts' => $payouts,
            'filters' => ['search' => $search, 'status' => $status],
            'stats' => $this->stats(),
        ]);
    }

    public function affiliatesIndex(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString(); // all | active | banned

        $affiliates = tap(User::query()
            ->withCount(['affiliateReferrals', 'affiliateCommissions'])
            ->withSum('affiliateCommissions as total_commissions', 'amount')
            ->where(function ($query) {
                $query->whereHas('affiliateReferrals')->orWhereHas('affiliateCommissions');
            })
            ->when($status === 'banned', fn ($q) => $q->where('affiliate_banned', true))
            ->when($status === 'active', fn ($q) => $q->where('affiliate_banned', false))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('referral_code', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(15), function ($paginator) {
                $paginator->getCollection()->transform(fn (User $u) => $this->affiliateToArray($u));
            });

        return Inertia::render('Admin/Marketing/Affiliate/Affiliates', [
            'affiliates' => $affiliates,
            'filters' => ['search' => $search, 'status' => $status ?: 'all'],
            'stats' => $this->stats(),
        ]);
    }

    public function show(User $user): Response
    {
        $user->loadCount(['affiliateReferrals', 'affiliateCommissions']);
        $user->loadSum('affiliateCommissions as total_commissions', 'amount');

        $referrals = AffiliateReferral::query()
            ->with('referred:id,ulid,name,email,created_at')
            ->where('referrer_id', $user->id)
            ->latest()
            ->limit(20)
            ->get();

        $commissions = tap(AffiliateCommission::query()
            ->with(['referred:id,ulid,name,email', 'payment:id,ulid,amount,currency'])
            ->where('referrer_id', $user->id)
            ->latest()
            ->paginate(10), function ($paginator) {
                $paginator->getCollection()->transform(fn (AffiliateCommission $c) => $this->commissionToArray($c));
            });

        $payouts = tap(AffiliatePayout::query()
            ->where('user_id', $user->id)
            ->with('processor:id,name')
            ->latest()
            ->paginate(10), function ($paginator) {
                $paginator->getCollection()->transform(fn (AffiliatePayout $p) => $this->payoutToArray($p));
            });

        return Inertia::render('Admin/Marketing/Affiliate/Show', [
            'affiliate' => $this->affiliateToArray($user),
            'availableBalance' => $this->affiliate->availableBalance($user),
            'referrals' => $referrals->map(fn (AffiliateReferral $r) => [
                'email' => $r->referred?->email ?? translate('Pending'),
                'joined_at' => $r->converted_at?->toDateString(),
                'status' => $r->referred_id ? 'registered' : 'clicked',
            ]),
            'commissions' => $commissions,
            'payouts' => $payouts,
        ]);
    }

    public function settingsEdit(): Response
    {
        return Inertia::render('Admin/Marketing/Affiliate/Settings', [
            'program' => AffiliateProgram::current(),
            'termsPageOptions' => Page::query()->published()->orderBy('title')->get(['title', 'slug']),
        ]);
    }

    // ─── Actions ─────────────────────────────────────────────

    public function updateSettings(AffiliateSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->has('marketing_banners')) {
            $data['marketing_banners'] = array_values(array_filter(
                $request->input('marketing_banners', []),
                fn ($b) => is_array($b) && ! empty($b['url'])
            ));
        }

        if ($request->has('promotional_emails')) {
            $data['promotional_emails'] = array_values(array_filter(
                $request->input('promotional_emails', []),
                fn ($e) => is_array($e) && ! empty($e['subject'])
            ));
        }

        if ($request->has('social_posts')) {
            $data['social_posts'] = array_values(array_filter(
                $request->input('social_posts', []),
                fn ($p) => is_array($p) && ! empty($p['text'])
            ));
        }

        AffiliateProgram::current()->update($data);

        return back()->with('success', translate('Affiliate settings updated successfully.'));
    }

    public function approveCommission(AffiliateCommission $commission): RedirectResponse
    {
        $this->affiliate->approveCommission($commission);

        return back()->with('success', translate('Commission approved successfully.'));
    }

    public function bulkApproveCommissions(Request $request): RedirectResponse
    {
        $ids = $request->input('ids', []);

        if (! is_array($ids) || empty($ids)) {
            return back()->withErrors(['ids' => translate('Select at least one commission to approve.')]);
        }

        $count = 0;
        AffiliateCommission::query()
            ->whereIn('id', $ids)
            ->where('status', 'pending')
            ->get()
            ->each(function (AffiliateCommission $commission) use (&$count) {
                $this->affiliate->approveCommission($commission);
                $count++;
            });

        return back()->with('success', translate(':count commission(s) approved successfully.', ['count' => $count]));
    }

    public function rejectCommission(AffiliateCommission $commission): RedirectResponse
    {
        $this->affiliate->rejectCommission($commission);

        return back()->with('success', translate('Commission rejected successfully.'));
    }

    public function toggleAffiliateBan(Request $request, User $user): RedirectResponse
    {
        $user->update(['affiliate_banned' => ! (bool) $user->affiliate_banned]);

        $message = $user->affiliate_banned
            ? translate('Affiliate suspended. Their referral links and payout requests are now blocked.')
            : translate('Affiliate reinstated successfully.');

        return back()->with('success', $message);
    }

    public function processPayout(AffiliatePayoutProcessRequest $request, AffiliatePayout $payout): RedirectResponse
    {
        // Terminal-state guard: paid/rejected payouts cannot be re-processed.
        if (in_array($payout->status, ['paid', 'rejected'], true)) {
            return back()->withErrors(['status' => translate('This payout has already been finalized and cannot be changed.')]);
        }

        $status = $request->validated('status');
        $holdDays = (int) $this->affiliate->program()->commission_hold_days;

        // Paying out as wallet credits converts the currency amount via the store
        // credit price. Guard a zero/misconfigured price up-front so we never mark
        // a payout paid while granting a bogus number of credits.
        if ($status === 'paid' && $payout->method === 'credits' && (float) settings('credit_price_per_unit', 0.01) <= 0) {
            return back()->withErrors(['status' => translate('Set a credit price greater than zero before paying out as credits.')]);
        }

        $processedPayout = DB::transaction(function () use ($request, $payout, $status, $holdDays) {
            // Lock the payout row and re-check the terminal guard INSIDE the
            // transaction. Without this, two concurrent submits (e.g. an admin
            // double-click) could both pass the pre-transaction guard and run the
            // "paid" branch twice — double-paying commissions and credits.
            $locked = AffiliatePayout::lockForUpdate()->find($payout->id);

            if (! $locked || in_array($locked->status, ['paid', 'rejected'], true)) {
                return null;
            }

            $locked->update([
                'status' => $status,
                'admin_note' => $request->validated('admin_note'),
                'processed_by' => auth('admin')->id(),
                'processed_at' => in_array($status, ['paid', 'rejected'], true) ? now() : null,
            ]);

            if ($status === 'paid') {
                $this->affiliate->markCommissionsPaid($locked->user_id, (float) $locked->amount, $holdDays);

                if ($locked->method === 'credits') {
                    // The payout amount is denominated in the store base currency; convert
                    // it to wallet credits with the same anchor the top-up flow uses
                    // (credit_price_per_unit), so "$X of affiliate balance" grants the same
                    // credits a buyer gets spending $X. Granting the raw amount as a credit
                    // count would short the affiliate by 1/price (e.g. $10 → 10 credits
                    // instead of 1000 at the default $0.01/credit). round() before floor()
                    // so float error doesn't shave a whole credit (19.99/0.01 = 1998.99… → 1999).
                    $pricePerUnit = (float) settings('credit_price_per_unit', 0.01);
                    $credits = $pricePerUnit > 0
                        ? (int) floor(round((float) $locked->amount / $pricePerUnit, 6))
                        : 0;

                    $locked->user->addCredits((float) $credits, 'referral', 'Affiliate payout', [
                        'payout_id' => $locked->id,
                        'payout_amount' => (float) $locked->amount,
                        'price_per_unit' => $pricePerUnit,
                        'credits' => $credits,
                    ]);
                }
            }

            return $locked->fresh('user');
        });

        if (! $processedPayout) {
            return back()->withErrors(['status' => translate('This payout has already been finalized and cannot be changed.')]);
        }

        $notifications = app(NotificationEventService::class);

        if ($processedPayout?->status === 'paid') {
            $notifications->payoutApproved($processedPayout);
        } elseif ($processedPayout?->status === 'processing') {
            $notifications->payoutProcessing($processedPayout);
        } elseif ($processedPayout?->status === 'rejected') {
            $notifications->payoutCancelled($processedPayout);
        }

        return back()->with('success', translate('Payout updated successfully.'));
    }

    // ─── Shared helpers ──────────────────────────────────────

    protected function stats(): array
    {
        $now = now();
        $sevenDaysAgo = $now->copy()->subDays(7);

        // 1. Total Affiliates — someone was an affiliate a week ago if they already
        //    had a referral before T (referral date, not the user's registration date).
        $affiliatesCurrent = User::whereHas('affiliateReferrals')->count();
        $affiliatesPrevious = User::whereHas('affiliateReferrals', fn ($q) => $q->where('created_at', '<', $sevenDaysAgo))->count();

        // 2. Total Paid Payouts — driven by processed_at (when paid), not created_at.
        $paidCurrent = (float) AffiliatePayout::where('status', 'paid')->sum('amount');
        $paidPrevious = (float) AffiliatePayout::where('status', 'paid')->whereNotNull('processed_at')->where('processed_at', '<', $sevenDaysAgo)->sum('amount');

        // 3. Pending Payouts
        $pendingPayoutsCurrent = (float) AffiliatePayout::whereIn('status', ['pending', 'processing'])->sum('amount');
        $pendingPayoutsPrevious = (float) AffiliatePayout::whereIn('status', ['pending', 'processing'])->where('created_at', '<', $sevenDaysAgo)->sum('amount');

        // 4. Pending Commissions
        $pendingCommissionsCurrent = (float) AffiliateCommission::where('status', 'pending')->sum('amount');
        $pendingCommissionsPrevious = (float) AffiliateCommission::where('status', 'pending')->where('created_at', '<', $sevenDaysAgo)->sum('amount');

        // 5. Total Earnings (Approved/Paid Commissions) — earned a week ago is driven
        //    by approved_at (when the commission was approved), not created_at.
        $earningsCurrent = (float) AffiliateCommission::whereIn('status', ['paid', 'approved'])->sum('amount');
        $earningsPrevious = (float) AffiliateCommission::whereNotNull('approved_at')->where('approved_at', '<', $sevenDaysAgo)->sum('amount');

        return [
            'total_affiliates' => [
                'value' => $affiliatesCurrent,
                'comparison' => $this->calculateComparison($affiliatesCurrent, $affiliatesPrevious),
            ],
            'total_paid' => [
                'value' => format_currency($paidCurrent),
                'comparison' => $this->calculateComparison($paidCurrent, $paidPrevious),
            ],
            'pending_payouts' => [
                'value' => format_currency($pendingPayoutsCurrent),
                'comparison' => $this->calculateComparison($pendingPayoutsCurrent, $pendingPayoutsPrevious),
            ],
            'pending_commissions' => [
                'value' => format_currency($pendingCommissionsCurrent),
                'comparison' => $this->calculateComparison($pendingCommissionsCurrent, $pendingCommissionsPrevious),
            ],
            'total_earnings' => [
                'value' => format_currency($earningsCurrent),
                'comparison' => $this->calculateComparison($earningsCurrent, $earningsPrevious),
            ],
        ];
    }

    private function calculateComparison(float $current, float $previous): array
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

    protected function recentCommissions(int $limit): array
    {
        return AffiliateCommission::query()
            ->with(['referrer:id,ulid,name,email', 'referred:id,ulid,name,email'])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (AffiliateCommission $c) => $this->commissionToArray($c))
            ->all();
    }

    protected function recentPayouts(int $limit): array
    {
        return AffiliatePayout::query()
            ->with(['user:id,ulid,name,email', 'processor:id,name'])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (AffiliatePayout $p) => $this->payoutToArray($p))
            ->all();
    }

    protected function topAffiliates(int $limit): array
    {
        return User::query()
            ->where(function ($query) {
                $query->whereHas('affiliateReferrals')->orWhereHas('affiliateCommissions');
            })
            ->orderByDesc('referral_earnings')
            ->limit($limit)
            ->get(['id', 'ulid', 'name', 'email', 'referral_earnings', 'referral_count'])
            ->map(fn (User $u) => [
                'ulid' => $u->ulid,
                'name' => $u->name,
                'email' => $u->email,
                'referral_earnings' => (float) $u->referral_earnings,
                'referral_count' => (int) $u->referral_count,
            ])
            ->all();
    }

    protected function commissionToArray(AffiliateCommission $commission): array
    {
        return [
            'id' => $commission->id,
            'amount' => (float) $commission->amount,
            'status' => $commission->status,
            'created_at' => $commission->created_at?->toIso8601String(),
            'referrer' => $commission->referrer ? [
                'ulid' => $commission->referrer->ulid,
                'name' => $commission->referrer->name,
                'email' => $commission->referrer->email,
            ] : null,
            'referred' => $commission->referred ? [
                'ulid' => $commission->referred->ulid,
                'name' => $commission->referred->name,
                'email' => $commission->referred->email,
            ] : null,
            'payment' => $commission->payment ? [
                'amount' => (float) $commission->payment->amount,
                'currency' => $commission->payment->currency,
            ] : null,
        ];
    }

    protected function payoutToArray(AffiliatePayout $payout): array
    {
        return [
            'id' => $payout->id,
            'amount' => (float) $payout->amount,
            'method' => $payout->method,
            'status' => $payout->status,
            'payout_details' => $payout->payout_details,
            'admin_note' => $payout->admin_note,
            'processed_at' => $payout->processed_at?->toIso8601String(),
            'processed_by' => $payout->processor?->only(['id', 'name']),
            'created_at' => $payout->created_at?->toIso8601String(),
            'user' => $payout->user ? [
                'ulid' => $payout->user->ulid,
                'name' => $payout->user->name,
                'email' => $payout->user->email,
            ] : null,
        ];
    }

    protected function affiliateToArray(User $user): array
    {
        return [
            'id' => $user->id,
            'ulid' => $user->ulid,
            'name' => $user->name,
            'email' => $user->email,
            'referral_code' => $user->referral_code,
            'referral_earnings' => (float) $user->referral_earnings,
            'affiliate_referrals_count' => $user->affiliate_referrals_count ?? 0,
            'affiliate_commissions_count' => $user->affiliate_commissions_count ?? 0,
            'total_commissions' => (float) ($user->total_commissions ?? 0),
            'affiliate_banned' => (bool) $user->affiliate_banned,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}

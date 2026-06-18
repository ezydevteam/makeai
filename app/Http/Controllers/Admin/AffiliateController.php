<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AffiliatePayoutProcessRequest;
use App\Http\Requests\Admin\AffiliateSettingsRequest;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\AffiliateProgram;
use App\Models\Page;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\NotificationEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateController extends Controller
{
    public function __construct(private readonly AffiliateService $affiliate) {}

    public function index(): Response
    {
        abort_unless(isProAvailable(), 404);
        abort_unless(auth('admin')->user()?->hasPermission('payments.gateways'), 403);

        return Inertia::render('Admin/Affiliate/Index', [
            'program' => AffiliateProgram::current(),
            'stats' => [
                'total_affiliates' => User::whereHas('affiliateReferrals')->count(),
                'total_paid' => (float) AffiliatePayout::where('status', 'paid')->sum('amount'),
                'pending_payouts' => (float) AffiliatePayout::whereIn('status', ['pending', 'processing'])->sum('amount'),
                'pending_commissions' => (float) AffiliateCommission::where('status', 'pending')->sum('amount'),
            ],
            'affiliates' => User::query()
                ->withCount(['affiliateReferrals', 'affiliateCommissions'])
                ->withSum('affiliateCommissions as total_commissions', 'amount')
                ->where(function ($query) {
                    $query->whereHas('affiliateReferrals')->orWhereHas('affiliateCommissions');
                })
                ->latest()
                ->paginate(15, ['id', 'ulid', 'name', 'email', 'referral_code', 'referral_earnings', 'referral_count', 'created_at']),
            'commissions' => AffiliateCommission::query()
                ->with(['referrer:id,ulid,name,email', 'referred:id,ulid,name,email', 'payment:id,ulid,amount,currency'])
                ->latest()
                ->paginate(15),
            'payouts' => AffiliatePayout::query()
                ->with(['user:id,ulid,name,email'])
                ->latest()
                ->paginate(15),
            'topEarners' => User::query()
                ->orderByDesc('referral_earnings')
                ->limit(5)
                ->get(['ulid', 'name', 'email', 'referral_earnings']),
            'termsPageOptions' => Page::query()
                ->published()
                ->orderBy('title')
                ->get(['title', 'slug']),
        ]);
    }

    public function updateSettings(AffiliateSettingsRequest $request): RedirectResponse
    {
        abort_unless(isProAvailable(), 404);
        abort_unless(auth('admin')->user()?->hasPermission('payments.gateways'), 403);

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
        abort_unless(isProAvailable(), 404);
        abort_unless(auth('admin')->user()?->hasPermission('payments.gateways'), 403);

        $this->affiliate->approveCommission($commission);

        return back()->with('success', translate('Commission approved successfully.'));
    }

    public function rejectCommission(AffiliateCommission $commission): RedirectResponse
    {
        abort_unless(isProAvailable(), 404);
        abort_unless(auth('admin')->user()?->hasPermission('payments.gateways'), 403);

        $commission->update(['status' => 'rejected']);

        return back()->with('success', translate('Commission rejected successfully.'));
    }

    public function processPayout(AffiliatePayoutProcessRequest $request, AffiliatePayout $payout): RedirectResponse
    {
        abort_unless(isProAvailable(), 404);
        abort_unless(auth('admin')->user()?->hasPermission('payments.gateways'), 403);

        $processedPayout = DB::transaction(function () use ($request, $payout) {
            $status = $request->validated('status');

            $payout->update([
                'status' => $status,
                'admin_note' => $request->validated('admin_note'),
                'processed_by' => auth('admin')->id(),
                'processed_at' => in_array($status, ['paid', 'rejected'], true) ? now() : null,
            ]);

            if ($status === 'paid') {
                $remaining = (float) $payout->amount;

                AffiliateCommission::query()
                    ->where('referrer_id', $payout->user_id)
                    ->where('status', 'approved')
                    ->where('approved_at', '<=', now()->subDays((int) $this->affiliate->program()->commission_hold_days))
                    ->oldest('approved_at')
                    ->get()
                    ->each(function (AffiliateCommission $commission) use (&$remaining) {
                        $commissionAmount = (float) $commission->amount;

                        if ($remaining < $commissionAmount) {
                            return false;
                        }

                        $remaining -= $commissionAmount;
                        $commission->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                        ]);

                        return true;
                    });

                if ($payout->method === 'credits') {
                    $payout->user->addCredits((float) $payout->amount, 'referral', 'Affiliate payout', [
                        'payout_id' => $payout->id,
                    ]);
                }
            }

            return $payout->fresh('user');
        });

        if ($processedPayout?->status === 'paid') {
            app(NotificationEventService::class)->payoutApproved($processedPayout);
        }

        if ($processedPayout?->status === 'rejected') {
            app(NotificationEventService::class)->payoutCancelled($processedPayout);
        }

        return back()->with('success', translate('Payout updated successfully.'));
    }
}

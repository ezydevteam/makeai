<?php

namespace App\Http\Controllers\Admin\Premium;

use App\Http\Controllers\Concerns\AuthorizesAdminActions;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    use AuthorizesAdminActions;

    public function index(Request $request): Response
    {
        abort_unless(coupons_enabled(), 404);

        $now = now();
        $sevenDaysAgo = $now->copy()->subDays(7);

        // 1. Total Coupons — total now vs total a week ago.
        $totalCouponsCurrent = Coupon::count();
        $totalCouponsPrevious = Coupon::where('created_at', '<', $sevenDaysAgo)->count();

        // 2. Active Coupons — currently active, trended against those already active a
        //    week ago (activated_at drives the flow; legacy nulls count as pre-existing).
        $activeCouponsCurrent = Coupon::where('is_active', true)->count();
        $activeCouponsPrevious = Coupon::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('activated_at')->orWhere('activated_at', '<', $sevenDaysAgo))
            ->count();

        // 3. Published Coupons — currently shown in header, trended against those already
        //    shown a week ago (published_at drives the flow; legacy nulls pre-existing).
        $publishedCouponsCurrent = Coupon::where('show_in_header', true)->count();
        $publishedCouponsPrevious = Coupon::where('show_in_header', true)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<', $sevenDaysAgo))
            ->count();

        // 4. Redemptions — how often coupons are actually being used. (This replaced a
        //    "Recurring Coupons" card: coupons force a one-time charge, so a recurring
        //    coupon could never take effect and the flag has been removed.)
        $redemptionsCurrent = CouponRedemption::count();
        $redemptionsPrevious = CouponRedemption::where('created_at', '<', $sevenDaysAgo)->count();

        $stats = [
            'total' => [
                'value' => $totalCouponsCurrent,
                'comparison' => $this->calculateComparison($totalCouponsCurrent, $totalCouponsPrevious),
            ],
            'active' => [
                'value' => $activeCouponsCurrent,
                'comparison' => $this->calculateComparison($activeCouponsCurrent, $activeCouponsPrevious),
            ],
            'published' => [
                'value' => $publishedCouponsCurrent,
                'comparison' => $this->calculateComparison($publishedCouponsCurrent, $publishedCouponsPrevious),
            ],
            'redemptions' => [
                'value' => $redemptionsCurrent,
                'comparison' => $this->calculateComparison($redemptionsCurrent, $redemptionsPrevious),
            ],
        ];

        $filters = [
            'search' => trim((string) $request->string('search')->value()),
            'status' => trim((string) $request->string('status')->value()),
        ];

        $coupons = Coupon::with('plan:id,name')
            ->when($filters['status'] !== '', function ($query) use ($filters) {
                if ($filters['status'] === 'active') {
                    $query->where('is_active', true);
                } elseif ($filters['status'] === 'inactive') {
                    $query->where('is_active', false);
                } elseif ($filters['status'] === 'published') {
                    $query->where('show_in_header', true);
                } elseif ($filters['status'] === 'unpublished') {
                    $query->where('show_in_header', false);
                }
            })
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $query->where('code', 'like', '%'.$filters['search'].'%')
                        ->orWhereHas('plan', function ($query) use ($filters) {
                            $query->where('name', 'like', '%'.$filters['search'].'%');
                        });
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Premium/Coupons', [
            'coupons' => $coupons,
            'plans' => Plan::query()
                ->orderBy('sort_order')
                ->get(['id', 'name']),
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }

    private function calculateComparison(int $current, int $previous): array
    {
        if ($previous === 0) {
            return [
                'label' => $current === 0 ? '0%' : '+100%',
                'type' => $current === 0 ? 'neutral' : 'up',
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

    public function store(Request $request): RedirectResponse
    {
        abort_unless(coupons_enabled(), 404);
        $this->authorizeAdmin('payments.gateways');

        $this->persist($this->validated($request));

        return back()->with('success', translate('Coupon created successfully.'));
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        abort_unless(coupons_enabled(), 404);
        $this->authorizeAdmin('payments.gateways');

        $this->persist($this->validated($request, $coupon), $coupon);

        return back()->with('success', translate('Coupon updated successfully.'));
    }

    /**
     * Create or update a coupon, honouring the "publish immediately" toggle.
     *
     * The header shows a single coupon (getHeaderCoupon() reads ->first()), so
     * publishing one demotes every other — the same invariant toggleHeader() keeps.
     * Wrapped in a transaction so the demotion and the save can't half-apply and
     * leave two coupons published at once.
     */
    private function persist(array $data, ?Coupon $coupon = null): Coupon
    {
        return DB::transaction(function () use ($data, $coupon) {
            if (! empty($data['show_in_header'])) {
                // Query-builder update bypasses model events, so clear published_at here
                // (mirrors toggleHeader). whereKeyNot skips the coupon being edited so it
                // doesn't demote itself before its own save re-publishes it.
                Coupon::query()
                    ->where('show_in_header', true)
                    ->when($coupon, fn ($query) => $query->whereKeyNot($coupon->id))
                    ->update(['show_in_header' => false, 'published_at' => null]);
            }

            if ($coupon) {
                $coupon->update($data);

                return $coupon;
            }

            return Coupon::create($data);
        });
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        abort_unless(coupons_enabled(), 404);
        $this->authorizeAdmin('payments.gateways');

        $coupon->delete();

        return back()->with('success', translate('Coupon deleted successfully.'));
    }

    public function toggleHeader(Coupon $coupon): RedirectResponse
    {
        abort_unless(coupons_enabled(), 404);
        $this->authorizeAdmin('payments.gateways');

        DB::transaction(function () use ($coupon) {
            $showInHeader = ! $coupon->show_in_header;

            // Query-builder update bypasses model events, so clear published_at here.
            Coupon::query()->where('show_in_header', true)->update(['show_in_header' => false, 'published_at' => null]);
            $coupon->update(['show_in_header' => $showInHeader]);
        });

        return back()->with('success', $coupon->fresh()->show_in_header
            ? translate('Coupon is now shown in the site header.')
            : translate('Coupon hidden from the site header.'));
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code,'.($coupon?->id ?? 'NULL')],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'max_discount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'user_limit' => ['required', 'string', 'in:all,active,inactive,free,pro,recent_30_days'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
            'show_in_header' => ['boolean'],
        ]);

        // A percentage discount above 100% would produce a negative charge.
        if ($data['type'] === 'percent' && (float) $data['value'] > 100) {
            throw ValidationException::withMessages([
                'value' => translate('A percentage discount cannot exceed 100%.'),
            ]);
        }

        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['show_in_header'] = (bool) ($data['show_in_header'] ?? false);
        $data['user_limit'] = $data['user_limit'] ?? 'all';

        return $data;
    }
}

<?php

namespace App\Http\Controllers\Admin\Premium\Payments;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(Request $request): Response
    {
        $now = now();
        $sevenDaysAgo = $now->copy()->subDays(7);
        $fourteenDaysAgo = $now->copy()->subDays(14);

        // 1. Total Coupons
        $totalCouponsCurrent = Coupon::count();
        $totalCouponsPrevious = Coupon::where('created_at', '<', $sevenDaysAgo)->count();

        // 2. Active Coupons
        $activeCouponsCurrent = Coupon::where('is_active', true)->count();
        $activeCouponsPrevious = Coupon::where('is_active', true)->where('created_at', '<', $sevenDaysAgo)->count();

        // 3. Published Coupons
        $publishedCouponsCurrent = Coupon::where('show_in_header', true)->count();
        $publishedCouponsPrevious = Coupon::where('show_in_header', true)->where('created_at', '<', $sevenDaysAgo)->count();

        // 4. Recurring Coupons
        $recurringCouponsCurrent = Coupon::where('is_recurring', true)->count();
        $recurringCouponsPrevious = Coupon::where('is_recurring', true)->where('created_at', '<', $sevenDaysAgo)->count();

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
            'recurring' => [
                'value' => $recurringCouponsCurrent,
                'comparison' => $this->calculateComparison($recurringCouponsCurrent, $recurringCouponsPrevious),
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

        return Inertia::render('Admin/Premium/Payments/Coupons', [
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

        Coupon::create($this->validated($request));

        return back()->with('success', translate('Coupon created successfully.'));
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {

        $coupon->update($this->validated($request, $coupon));

        return back()->with('success', translate('Coupon updated successfully.'));
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {

        $coupon->delete();

        return back()->with('success', translate('Coupon deleted successfully.'));
    }

    public function toggleHeader(Coupon $coupon): RedirectResponse
    {

        DB::transaction(function () use ($coupon) {
            $showInHeader = ! $coupon->show_in_header;

            Coupon::query()->where('show_in_header', true)->update(['show_in_header' => false]);
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
            'is_recurring' => ['boolean'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'user_limit' => ['required', 'string', 'in:all,active,inactive,free,pro,recent_30_days'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
        ]);

        // A percentage discount above 100% would produce a negative charge.
        if ($data['type'] === 'percent' && (float) $data['value'] > 100) {
            throw ValidationException::withMessages([
                'value' => translate('A percentage discount cannot exceed 100%.'),
            ]);
        }

        $data['code'] = strtoupper($data['code']);
        $data['is_recurring'] = (bool) ($data['is_recurring'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['user_limit'] = $data['user_limit'] ?? 'all';

        return $data;
    }
}

<?php

namespace App\Http\Controllers\Admin\Premium\Payments;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(): Response
    {

        return Inertia::render('Admin/Premium/Payments/Coupons', [
            'coupons' => Coupon::with('plan:id,name')
                ->latest()
                ->paginate(25),
            'plans' => Plan::query()
                ->orderBy('sort_order')
                ->get(['id', 'name']),
        ]);
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
            'is_recurring' => ['boolean'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'user_limit' => ['required', 'string', 'in:all,active,inactive,free,pro,recent_30_days'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['is_recurring'] = (bool) ($data['is_recurring'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['user_limit'] = $data['user_limit'] ?? 'all';

        return $data;
    }
}

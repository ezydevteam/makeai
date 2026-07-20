<?php

namespace App\Http\Controllers\Admin\Premium;

use App\Http\Controllers\Concerns\AuthorizesAdminActions;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditSettingsController extends Controller
{
    use AuthorizesAdminActions;

    public function index(): Response
    {

        return Inertia::render('Admin/Premium/CreditSettings', [
            'settings' => $this->creditSettings(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeAdmin('settings.manage', 'payments.gateways');

        $data = $request->validate([
            'credit_price_per_unit' => ['required', 'numeric', 'min:0.0001', 'max:999.9999'],
            'credit_topup_minimum' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'credit_topup_quick_amounts' => ['required', 'array', 'min:1', 'max:10'],
            'credit_topup_quick_amounts.*' => ['numeric', 'min:0.01', 'max:999999.99'],
            'credit_topup_bonus_tiers' => ['required', 'array', 'max:10'],
            'credit_topup_bonus_tiers.*.min_amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'credit_topup_bonus_tiers.*.bonus_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'credit_topup_enabled' => ['boolean'],
        ]);

        settings_set('credit_price_per_unit', $data['credit_price_per_unit'], 'string', 'billing');
        settings_set('credit_topup_minimum', $data['credit_topup_minimum'], 'string', 'billing');
        settings_set('credit_topup_quick_amounts', json_encode($data['credit_topup_quick_amounts']), 'json', 'billing');
        settings_set('credit_topup_bonus_tiers', json_encode($data['credit_topup_bonus_tiers']), 'json', 'billing');
        settings_set('credit_topup_enabled', $data['credit_topup_enabled'] ?? false, 'boolean', 'billing');

        return back()->with('success', translate('Credit settings updated successfully.'));
    }

    private function creditSettings(): array
    {
        $quickAmounts = settings('credit_topup_quick_amounts', [5, 10, 25, 50, 100]);
        if (is_string($quickAmounts)) {
            $quickAmounts = json_decode($quickAmounts, true) ?? [5, 10, 25, 50, 100];
        }

        $bonusTiers = settings('credit_topup_bonus_tiers', []);
        if (is_string($bonusTiers)) {
            $bonusTiers = json_decode($bonusTiers, true) ?? [];
        }

        return [
            'credit_price_per_unit' => (float) settings('credit_price_per_unit', 0.01),
            'credit_topup_minimum' => (float) settings('credit_topup_minimum', 1.00),
            'credit_topup_quick_amounts' => $quickAmounts,
            'credit_topup_bonus_tiers' => $bonusTiers,
            'credit_topup_enabled' => (bool) settings('credit_topup_enabled', true),
        ];
    }
}

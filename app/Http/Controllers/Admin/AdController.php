<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdRequest;
use App\Models\Ad;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdController extends Controller
{
    public function index(): Response
    {
        $ads = Ad::orderBy('zone')->orderBy('sort_order')->latest()->get()->map(function ($ad) {
            return array_merge($ad->toArray(), [
                'ctr' => $ad->ctr,
            ]);
        });

        return Inertia::render('Admin/Ads/Index', [
            'ads' => $ads,
            'zones' => config('ads.zones'),
            'settings' => [
                'ads_enabled' => (bool) settings('ads_enabled', true),
                'adsense_publisher_id' => (string) settings('adsense_publisher_id', ''),
                'ads_auto_ads_enabled' => (bool) settings('ads_auto_ads_enabled', false),
                'ads_disable_for_subscribed_users' => (bool) settings('ads_disable_for_subscribed_users', false),
                'ads_disabled_plan_ids' => settings('ads_disabled_plan_ids', []),
            ],
            'plans' => Plan::query()->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Ads/Editor', [
            'ad' => null,
            'zones' => config('ads.zones'),
        ]);
    }

    public function store(AdRequest $request): RedirectResponse
    {
        $validated = $this->payload($request);

        Ad::create($validated);

        return redirect()->route('admin.ads.index')->with('success', translate('Advertisement created successfully.'));
    }

    public function edit(Ad $ad): Response
    {
        return Inertia::render('Admin/Ads/Editor', [
            'ad' => $ad,
            'zones' => config('ads.zones'),
        ]);
    }

    public function update(AdRequest $request, Ad $ad): RedirectResponse
    {
        $validated = $this->payload($request);

        $ad->update($validated);

        return redirect()->route('admin.ads.index')->with('success', translate('Advertisement updated successfully.'));
    }

    public function destroy(Ad $ad): RedirectResponse
    {
        $ad->delete();

        return redirect()->route('admin.ads.index')->with('success', translate('Advertisement deleted successfully.'));
    }

    public function toggle(Ad $ad): RedirectResponse
    {
        $ad->update(['is_active' => ! $ad->is_active]);

        return back();
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ads_enabled' => ['boolean'],
            'adsense_publisher_id' => ['nullable', 'string', 'max:100', 'regex:/^ca-pub-[0-9]+$/'],
            'ads_auto_ads_enabled' => ['boolean'],
            'ads_disable_for_subscribed_users' => ['boolean'],
            'ads_disabled_plan_ids' => ['array'],
            'ads_disabled_plan_ids.*' => ['integer', 'exists:plans,id'],
        ]);

        settings_set('ads_enabled', (bool) ($data['ads_enabled'] ?? false), 'boolean', 'ads');
        settings_set('adsense_publisher_id', $data['adsense_publisher_id'] ?? '', 'string', 'ads');
        settings_set('ads_auto_ads_enabled', (bool) ($data['ads_auto_ads_enabled'] ?? false), 'boolean', 'ads');
        settings_set('ads_disable_for_subscribed_users', (bool) ($data['ads_disable_for_subscribed_users'] ?? false), 'boolean', 'ads');
        settings_set('ads_disabled_plan_ids', array_map('intval', $data['ads_disabled_plan_ids'] ?? []), 'json', 'ads');

        return back()->with('success', translate('Ad settings updated successfully.'));
    }

    private function payload(AdRequest $request): array
    {
        $validated = $request->validated();
        $validated['adsense_client'] = $validated['adsense_client'] ?: settings('adsense_publisher_id');
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        return $validated;
    }
}

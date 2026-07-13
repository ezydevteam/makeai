<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdRequest;
use App\Models\Ad;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AdController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->renderIndex($request);
    }

    public function create(Request $request): Response
    {
        return $this->renderIndex($request, editorMode: 'create');
    }

    public function store(AdRequest $request): RedirectResponse
    {
        $validated = $this->payload($request);

        Ad::create($validated);

        return redirect()->route('admin.ads.index')->with('success', translate('Advertisement created successfully.'));
    }

    public function edit(Request $request, Ad $ad): Response
    {
        return $this->renderIndex($request, $ad, 'edit');
    }

    public function update(AdRequest $request, Ad $ad): RedirectResponse
    {
        $validated = $this->payload($request);

        $ad->update($validated);

        return redirect()->route('admin.ads.index')->with('success', translate('Advertisement updated successfully.'));
    }

    public function destroy(Ad $ad): RedirectResponse
    {
        $this->authorizeAds();
        $ad->delete();

        return redirect()->route('admin.ads.index')->with('success', translate('Advertisement deleted successfully.'));
    }

    public function toggle(Ad $ad): RedirectResponse
    {
        $this->authorizeAds();
        $ad->update(['is_active' => ! $ad->is_active]);

        return back()->with('success', translate('Advertisement status updated successfully.'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorizeAds();

        $data = $request->validate([
            'ads_enabled' => ['boolean'],
            'adsense_publisher_id' => ['nullable', 'string', 'max:100', 'regex:/^ca-pub-[0-9]+$/'],
            'ads_auto_ads_enabled' => ['boolean'],
            'ads_disable_for_subscribed_users' => ['boolean'],
            'ads_disabled_plan_ids' => ['array'],
            'ads_disabled_plan_ids.*' => ['integer', 'exists:plans,id'],
        ]);

        if (($data['ads_enabled'] ?? false) && blank($data['adsense_publisher_id'] ?? null)) {
            return back()
                ->withErrors([
                    'adsense_publisher_id' => translate('AdSense Publisher ID is required before ads can be enabled.'),
                ])
                ->withInput();
        }

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
        $currentAd = $request->route('ad');

        if ($request->hasFile('image_file')) {
            if ($currentAd?->image_url) {
                $this->deleteUploadedAdImage($currentAd->image_url);
            }

            $validated['image_url'] = Storage::url(store_public_upload($request->file('image_file'), 'ads'));
        } elseif (($validated['type'] ?? null) === 'image_link') {
            $validated['image_url'] = $currentAd?->image_url;
        } else {
            $validated['image_url'] = null;
        }

        unset($validated['image_file']);
        $validated['adsense_client'] = $validated['adsense_client'] ?: settings('adsense_publisher_id');
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        return $validated;
    }

    private function deleteUploadedAdImage(?string $imageUrl): void
    {
        if (blank($imageUrl)) {
            return;
        }

        $storagePrefix = Storage::url('');

        if (str_starts_with($imageUrl, $storagePrefix)) {
            Storage::disk('public')->delete(str_replace($storagePrefix, '', $imageUrl));
        }
    }

    private function authorizeAds(): void
    {
        if (! auth('admin')->check()) {
            abort(403, translate('Unauthorized.'));
        }
    }

    private function renderIndex(Request $request, ?Ad $editorAd = null, ?string $editorMode = null): Response
    {
        $query = Ad::query();

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('zone', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhere('show_to', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        $ads = $query->orderBy('zone')->orderBy('sort_order')->latest()->get()->map(function ($ad) {
            return array_merge($ad->toArray(), [
                'ctr' => $ad->ctr,
            ]);
        });

        return Inertia::render('Admin/Marketing/Advertisement', [
            'ads' => $ads,
            'filters' => $request->only(['search', 'status']),
            'zones' => config('ads.zones'),
            'settings' => [
                'ads_enabled' => (bool) settings('ads_enabled', true),
                'adsense_publisher_id' => (string) settings('adsense_publisher_id', ''),
                'ads_auto_ads_enabled' => (bool) settings('ads_auto_ads_enabled', false),
                'ads_disable_for_subscribed_users' => (bool) settings('ads_disable_for_subscribed_users', false),
                'ads_disabled_plan_ids' => settings('ads_disabled_plan_ids', []),
            ],
            'plans' => Plan::query()->orderBy('sort_order')->get(['id', 'name']),
            'editorAd' => $editorAd,
            'editorMode' => $editorMode,
        ]);
    }
}

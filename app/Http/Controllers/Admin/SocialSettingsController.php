<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SocialSettingsRequest;
use App\Models\SocialFollowCount;
use App\Services\SocialService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SocialSettingsController extends Controller
{
    public function edit(SocialService $socialService)
    {
        $this->authorizeSocialSettings();

        return Inertia::render('Admin/Social/Settings', [
            'platforms' => $socialService->followPlatforms(),
            'profiles' => $socialService->configuredFollowProfiles(),
            'settings' => [
                'social_follow_display_mode' => $socialService->followDisplayMode(),
                'social_follow_refresh_hours' => (int) settings('social_follow_refresh_hours', 24),
            ],
        ]);
    }

    public function update(SocialSettingsRequest $request)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            $socialService = app(SocialService::class);

            settings_set(
                'social_follow_display_mode',
                $validated['settings']['social_follow_display_mode'],
                'string',
                'social'
            );
            settings_set(
                'social_follow_refresh_hours',
                (int) $validated['settings']['social_follow_refresh_hours'],
                'integer',
                'social'
            );

            foreach ($validated['profiles'] as $profile) {
                if (! empty($profile['api_key'])) {
                    settings_set(
                        $socialService->apiKeySettingKey($profile['platform']),
                        $profile['api_key'],
                        'encrypted',
                        'social'
                    );
                }

                settings_set(
                    $socialService->externalIdSettingKey($profile['platform']),
                    $profile['external_id'] ?? '',
                    'string',
                    'social'
                );

                SocialFollowCount::updateOrCreate(
                    ['platform' => $profile['platform']],
                    [
                        'profile_url' => $profile['profile_url'] ?? null,
                        'manual_count' => $profile['manual_count'] ?? null,
                        'count_source' => $profile['count_source'],
                        'fetch_enabled' => (bool) $profile['fetch_enabled'],
                        'sort_order' => (int) $profile['sort_order'],
                        'is_active' => (bool) $profile['is_active'],
                        'last_error' => null,
                    ]
                );
            }
        });

        return back()->with('success', translate('Social follow counters saved.'));
    }

    private function authorizeSocialSettings(): void
    {
        abort_unless(auth('admin')->user()?->hasPermission('settings.manage'), 403);
    }
}

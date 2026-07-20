<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SocialSettingsRequest;
use App\Models\SocialFollowCount;
use App\Services\SocialService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SocialCountersController extends Controller
{
    public function editFollow(SocialService $socialService): Response
    {
        $this->authorizeSocialSettings();

        return Inertia::render('Admin/Settings/SocialCounters', [
            'platforms' => $socialService->followPlatforms(),
            'profiles' => $socialService->configuredFollowProfiles(),
            'settings' => [
                'social_follow_display_mode' => $socialService->followDisplayMode(),
                'social_follow_refresh_hours' => (int) settings('social_follow_refresh_hours', 24),
            ],
        ]);
    }

    public function updateFollow(SocialSettingsRequest $request)
    {
        $this->authorizeSocialSettings();
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
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
                settings_set(
                    "social_follow_api_key_{$profile['platform']}",
                    $profile['api_key'] ?? '',
                    'encrypted',
                    'social'
                );
                settings_set(
                    "social_follow_external_id_{$profile['platform']}",
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
        // Defense-in-depth: enforce the permission in-controller too, in case this
        // is ever reached without the settings.manage route middleware.
        if (! auth('admin')->user()?->hasPermission('settings.manage')) {
            abort(403, translate('Unauthorized.'));
        }
    }
}

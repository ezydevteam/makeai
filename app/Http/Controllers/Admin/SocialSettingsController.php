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
    private const SOCIAL_LOGIN_PROVIDERS = [
        'google' => 'Google',
        'github' => 'GitHub',
        'facebook' => 'Facebook',
        'reddit' => 'Reddit',
        'twitter' => 'Twitter',
    ];

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
            'socialLoginProviders' => $this->socialLoginProviders(),
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

            foreach ($validated['social_login_providers'] as $provider) {
                $slug = $provider['provider'];

                settings_set(
                    "social_login_{$slug}_enabled",
                    (bool) $provider['enabled'],
                    'boolean',
                    'social_login'
                );

                settings_set(
                    "social_login_{$slug}_client_id",
                    $provider['client_id'] ?? '',
                    'encrypted',
                    'social_login'
                );

                if (! empty($provider['client_secret'])) {
                    settings_set(
                        "social_login_{$slug}_client_secret",
                        $provider['client_secret'],
                        'encrypted',
                        'social_login'
                    );
                }
            }

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

    private function socialLoginProviders(): array
    {
        return collect(self::SOCIAL_LOGIN_PROVIDERS)
            ->map(fn (string $label, string $provider) => [
                'provider' => $provider,
                'label' => $label,
                'enabled' => (bool) settings("social_login_{$provider}_enabled", false),
                'client_id' => (string) settings("social_login_{$provider}_client_id", ''),
                'client_secret' => '',
                'client_secret_configured' => filled(settings("social_login_{$provider}_client_secret", '')),
                'redirect_url' => route('social.callback', $provider),
            ])
            ->values()
            ->all();
    }
}

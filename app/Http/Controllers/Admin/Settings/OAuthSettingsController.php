<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SocialSettingsRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OAuthSettingsController extends Controller
{
    private const SOCIAL_LOGIN_PROVIDERS = [
        'google' => 'Google',
        'github' => 'GitHub',
        'facebook' => 'Facebook',
        'reddit' => 'Reddit',
        'twitter' => 'Twitter',
        'linkedin' => 'LinkedIn',
    ];

    public function edit(): Response
    {
        $this->authorizeSocialSettings();

        return Inertia::render('Admin/Settings/OAuth', [
            'settings' => [
                'social_share_blog_style' => (string) settings('social_share_blog_style', 'icon-label'),
            ],
            'socialLoginProviders' => $this->socialLoginProviders(),
        ]);
    }

    public function update(SocialSettingsRequest $request)
    {
        $this->authorizeSocialSettings();
        $validated = $request->validated();
        $providerErrors = [];

        foreach ($validated['social_login_providers'] as $index => $provider) {
            $savedClientId = (string) settings("social_login_{$provider['provider']}_client_id", '');
            $savedClientSecret = (string) settings("social_login_{$provider['provider']}_client_secret", '');
            $effectiveClientId = trim((string) ($provider['client_id'] ?: $savedClientId));
            $effectiveClientSecret = trim((string) ($provider['client_secret'] ?: $savedClientSecret));

            if ((bool) $provider['enabled'] && ($effectiveClientId === '' || $effectiveClientSecret === '')) {
                $providerErrors["social_login_providers.{$index}.enabled"] = translate('Client ID and client secret are required before enabling this provider.');
            }
        }

        if ($providerErrors !== []) {
            throw ValidationException::withMessages($providerErrors);
        }

        DB::transaction(function () use ($validated) {
            settings_set(
                'social_share_blog_style',
                $validated['settings']['social_share_blog_style'],
                'string',
                'social'
            );

            foreach ($validated['social_login_providers'] as $provider) {
                settings_set(
                    "social_login_{$provider['provider']}_enabled",
                    (bool) $provider['enabled'],
                    'boolean',
                    'social'
                );
                settings_set(
                    "social_login_{$provider['provider']}_client_id",
                    $provider['client_id'] ?? '',
                    'string',
                    'social'
                );

                if (filled($provider['client_secret'] ?? null)) {
                    settings_set(
                        "social_login_{$provider['provider']}_client_secret",
                        $provider['client_secret'],
                        'encrypted',
                        'social'
                    );
                }
            }
        });

        return back()->with('success', translate('OAuth settings saved.'));
    }

    private function authorizeSocialSettings(): void
    {
    }

    private function socialLoginProviders(): array
    {
        return collect(self::SOCIAL_LOGIN_PROVIDERS)
            ->map(fn (string $label, string $provider) => [
                'provider' => $provider,
                'label' => $label,
                'enabled' => (bool) settings("social_login_{$provider}_enabled", false),
                'client_id' => '',
                'client_id_configured' => filled(settings("social_login_{$provider}_client_id", '')),
                'client_id_masked' => $this->maskValue((string) settings("social_login_{$provider}_client_id", '')),
                'client_secret' => '',
                'client_secret_configured' => filled(settings("social_login_{$provider}_client_secret", '')),
                'client_secret_masked' => $this->maskValue((string) settings("social_login_{$provider}_client_secret", '')),
                'redirect_url' => route('social.callback', $provider),
            ])
            ->values()
            ->all();
    }

    private function maskValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $length = mb_strlen($value);

        if ($length <= 8) {
            return str_repeat('*', max(4, $length));
        }

        return mb_substr($value, 0, 4)
            . str_repeat('*', max(4, $length - 8))
            . mb_substr($value, -4);
    }
}

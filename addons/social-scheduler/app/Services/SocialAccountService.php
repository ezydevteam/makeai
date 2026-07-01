<?php

namespace Addons\SocialScheduler\Services;

use Addons\SocialScheduler\Exceptions\AccountLimitException;
use Addons\SocialScheduler\Exceptions\SocialTokenRefreshException;
use Addons\SocialScheduler\Models\SsSocialAccount;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\Factory as Socialite;

class SocialAccountService
{
    private const PLATFORMS = [
        'instagram' => ['facebook', 'instagram_basic', 'instagram_content_publish', 'pages_show_list', 'pages_read_engagement'],
        'facebook' => ['facebook', 'pages_manage_posts', 'pages_read_engagement', 'pages_show_list'],
        'twitter' => ['twitter-oauth-2', 'tweet.read', 'tweet.write', 'users.read', 'offline.access'],
        'linkedin' => ['linkedin-openid', 'openid', 'profile', 'w_member_social', 'r_basicprofile'],
    ];

    public function getRedirectUrl(string $platform): string
    {
        $this->validatePlatform($platform);
        $this->checkPlatformConfigured($platform);

        return $this->buildDriver($platform)->redirect()->getTargetUrl();
    }

    public function checkPlatformConfigured(string $platform): void
    {
        $this->validatePlatform($platform);
        $provider = $platform === 'instagram' ? 'facebook' : $platform;

        $enabled = (bool) settings("social_login_{$provider}_enabled", false);
        $clientId = trim((string) settings("social_login_{$provider}_client_id", ''));
        $clientSecret = trim((string) settings("social_login_{$provider}_client_secret", ''));

        if (! $enabled) {
            throw new \RuntimeException(translate("The :platform connection is currently disabled by the administrator.", ['platform' => ucfirst($platform)]));
        }

        if ($clientId === '' || $clientSecret === '') {
            throw new \RuntimeException(translate("The :platform integration is not fully configured. Please contact the administrator.", ['platform' => ucfirst($platform)]));
        }
    }

    public function handleCallback(string $platform, User $user): SsSocialAccount
    {
        $this->validatePlatform($platform);

        $driver = $this->buildDriver($platform);
        $socialUser = $driver->user();

        $account = SsSocialAccount::where('user_id', $user->id)
            ->where('platform', $platform)
            ->where('platform_user_id', (string) $socialUser->getId())
            ->first();

        $data = [
            'user_id' => $user->id,
            'platform' => $platform,
            'platform_user_id' => (string) $socialUser->getId(),
            'platform_username' => $socialUser->getNickname(),
            'platform_name' => $socialUser->getName(),
            'avatar_url' => $socialUser->getAvatar(),
            'access_token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken ?? null,
            'token_expires_at' => $socialUser->expiresIn
                ? now()->addSeconds($socialUser->expiresIn)
                : null,
            'scopes' => $socialUser->approvedScopes ?? $this->defaultScopes($platform),
            'is_active' => true,
        ];

        if ($account) {
            $account->update($data);
        } else {
            $this->checkAccountLimit($user);
            $account = SsSocialAccount::create($data);
        }

        // Facebook/Instagram: exchange for long-lived page token
        if (in_array($platform, ['facebook', 'instagram'])) {
            $this->exchangeLongLivedToken($account);
        }

        return $account->fresh();
    }

    public function refreshToken(SsSocialAccount $account): SsSocialAccount
    {
        try {
            $refreshToken = $account->getDecryptedRefreshToken();

            if (! $refreshToken) {
                throw new SocialTokenRefreshException('No refresh token available. Reconnect the account.');
            }

            if ($account->platform === 'twitter') {
                $response = Http::asForm()->post('https://api.twitter.com/2/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'client_id' => $this->clientId($account->platform),
                ]);

                if (! $response->successful()) {
                    throw new SocialTokenRefreshException('Twitter token refresh failed: ' . $response->body());
                }

                $data = $response->json();
                $account->update([
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'] ?? $refreshToken,
                    'token_expires_at' => isset($data['expires_in'])
                        ? now()->addSeconds($data['expires_in'])
                        : null,
                ]);
            }

            if (in_array($account->platform, ['facebook', 'instagram'])) {
                $response = Http::get('https://graph.facebook.com/v21.0/oauth/access_token', [
                    'grant_type' => 'fb_exchange_token',
                    'client_id' => $this->clientId($account->platform),
                    'client_secret' => $this->clientSecret($account->platform),
                    'fb_exchange_token' => $account->getDecryptedAccessToken(),
                ]);

                if (! $response->successful()) {
                    throw new SocialTokenRefreshException('Facebook token exchange failed: ' . $response->body());
                }

                $data = $response->json();
                $account->update([
                    'access_token' => $data['access_token'],
                    'token_expires_at' => isset($data['expires_in'])
                        ? now()->addSeconds($data['expires_in'])
                        : now()->addDays(60),
                ]);
            }

            return $account->fresh();
        } catch (\Throwable $e) {
            throw new SocialTokenRefreshException(
                "Token refresh failed for {$account->platform}: " . $e->getMessage(),
                0,
                $e,
            );
        }
    }

    public function getApiClient(SsSocialAccount $account): PlatformApiClient
    {
        if ($account->is_token_expired && $account->refresh_token) {
            $account = $this->refreshToken($account);
        }

        return new PlatformApiClient(
            platform: $account->platform,
            accessToken: $account->getDecryptedAccessToken(),
            pageId: $account->page_id,
            http: Http::withToken($account->getDecryptedAccessToken())
                ->timeout(30)
                ->withOptions(['http_errors' => false]),
        );
    }

    public function disconnect(SsSocialAccount $account): void
    {
        $account->update([
            'is_active' => false,
            'access_token' => '',
            'refresh_token' => null,
        ]);
    }

    public function checkAccountLimit(User $user): void
    {
        $max = (int) addon_setting('social-scheduler', 'max_accounts_per_user', 10);
        $current = SsSocialAccount::where('user_id', $user->id)->active()->count();

        if ($current >= $max) {
            throw new AccountLimitException("You can connect up to {$max} accounts. Disconnect one first.");
        }
    }

    private function validatePlatform(string $platform): void
    {
        if (! isset(self::PLATFORMS[$platform])) {
            throw new \InvalidArgumentException("Unsupported platform: {$platform}");
        }
    }

    private function buildDriver(string $platform)
    {
        $socialite = app(Socialite::class);

        $config = [
            'client_id' => $this->clientId($platform),
            'client_secret' => $this->clientSecret($platform),
            'redirect' => route('addon.social.user.accounts.callback', $platform),
        ];

        $driverName = self::PLATFORMS[$platform][0];

        config()->set("services.{$driverName}", $config);

        $driver = $socialite->driver($driverName);

        $scopes = array_slice(self::PLATFORMS[$platform], 1);
        if (method_exists($driver, 'scopes')) {
            $driver->scopes($scopes);
        }

        if ($platform === 'twitter') {
            config()->set("services.{$driverName}.oauth", 2);
        }

        return $driver;
    }

    private function clientId(string $platform): string
    {
        $provider = $platform === 'instagram' ? 'facebook' : $platform;
        return (string) settings("social_login_{$provider}_client_id", '');
    }

    private function clientSecret(string $platform): string
    {
        $provider = $platform === 'instagram' ? 'facebook' : $platform;
        return (string) settings("social_login_{$provider}_client_secret", '');
    }

    private function defaultScopes(string $platform): array
    {
        return array_slice(self::PLATFORMS[$platform], 1);
    }

    private function exchangeLongLivedToken(SsSocialAccount $account): void
    {
        try {
            $response = Http::get('https://graph.facebook.com/v21.0/oauth/access_token', [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $this->clientId($account->platform),
                'client_secret' => $this->clientSecret($account->platform),
                'fb_exchange_token' => $account->getDecryptedAccessToken(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $account->update([
                    'access_token' => $data['access_token'],
                    'token_expires_at' => isset($data['expires_in'])
                        ? now()->addSeconds($data['expires_in'])
                        : now()->addDays(60),
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning("Failed to exchange long-lived token: " . $e->getMessage());
        }
    }
}

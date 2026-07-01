<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\NotificationEventService;
use App\Services\RateLimiterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SocialAuthController extends Controller
{
    private const PROVIDERS = ['google', 'github', 'facebook', 'reddit', 'twitter', 'linkedin'];

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $provider = $this->normalizeProvider($provider);
        $this->ensureSocialiteIsInstalled();
        $this->ensureProviderIsConfigured($provider);

        $rateLimiter = app(RateLimiterService::class);
        $rateLimiter->hit('social_auth', $this->throttleKey($request, $provider), 300);

        return $this->driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider, AffiliateService $affiliate): RedirectResponse
    {
        $provider = $this->normalizeProvider($provider);
        $this->ensureSocialiteIsInstalled();
        $this->ensureProviderIsConfigured($provider);

        $rateLimiter = app(RateLimiterService::class);
        $throttleKey = $this->throttleKey($request, $provider);

        $result = $rateLimiter->attempt('social_auth', $throttleKey, 10, 900);

        if (! $result['allowed']) {
            throw ValidationException::withMessages([
                'email' => [translate('Too many social login attempts. Please try again later.')],
            ]);
        }

        try {
            $socialUser = $this->driver($provider)->user();
        } catch (\Throwable) {
            $rateLimiter->hit('social_auth', $throttleKey, 900);

            return redirect()->route('login')
                ->with('error', translate('Social login could not be completed. Please try again.'));
        }

        $rateLimiter->clear('social_auth', $throttleKey);

        $providerId = (string) $socialUser->getId();
        $email = $this->resolveEmail($provider, $providerId, $socialUser->getEmail());
        $name = $socialUser->getName() ?: $socialUser->getNickname() ?: Str::before($email, '@');
        $avatar = $socialUser->getAvatar();

        $user = User::query()
            ->where('oauth_provider', $provider)
            ->where('oauth_id', $providerId)
            ->first();

        if (! $user) {
            $user = User::query()->where('email', $email)->first();
        }

        if ($user) {
            $foundByEmail = $user->oauth_provider !== $provider || $user->oauth_id !== $providerId;

            // CRITICAL SECURITY: Prevent account takeover via unverified OAuth emails.
            // If the account exists but was registered with a different provider/email-password,
            // we must NOT allow login. The user must use their original login method.
            if ($foundByEmail) {
                return redirect()->route('login')
                    ->with('error', translate('An account with this email already exists. Please log in using your existing method.'));
            }

            $user->forceFill([
                'name' => $user->name ?: $name,
                'avatar' => $user->avatar ?: $avatar,
                'email_verified_at' => $user->email_verified_at ?: now(),
                'oauth_provider' => $provider,
                'oauth_id' => $providerId,
            ]);

            $user->save();
        } else {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::password(48)),
                'avatar' => $avatar,
                'oauth_provider' => $provider,
                'oauth_id' => $providerId,
                'email_verified_at' => now(),
            ]);

            $affiliate->attachReferralToUser($request, $user);
            app(NotificationEventService::class)->newUserRegistered($user);
        }

        if (! $user->is_active) {
            return redirect()->route('login')
                ->with('error', translate('Your account has been deactivated.'));
        }

        Auth::login($user, true);

        if ($user->hasTotpEnabled()) {
            $request->session()->put('user_2fa_id', $user->id);
            $request->session()->put('user_2fa_remember', true);
            Auth::logout();

            return redirect()->route('two-factor.show');
        }

        $isNewLoginIp = ! $user->loginHistory()
            ->where('ip', $request->ip())
            ->where('success', true)
            ->exists();

        $user->recordLogin($request->ip(), (string) $request->userAgent());

        if ($isNewLoginIp) {
            app(NotificationEventService::class)->newLogin(
                $user,
                $request->ip(),
                $request->header('CF-IPCity') ?: $request->header('X-Vercel-IP-City'),
                $request->header('CF-IPCountry') ?: $request->header('X-Vercel-IP-Country')
            );
        }

        $request->session()->regenerate();

        return redirect()->intended(route('user.dashboard'));
    }

    private function normalizeProvider(string $provider): string
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        return $provider;
    }

    private function ensureSocialiteIsInstalled(): void
    {
        if (! interface_exists('Laravel\\Socialite\\Contracts\\Factory')) {
            throw ValidationException::withMessages([
                'email' => [translate('Social login package is not installed. Please run composer install.')],
            ]);
        }
    }

    private function ensureProviderIsConfigured(string $provider): void
    {
        if (! $this->providerEnabled($provider) || ! $this->clientId($provider) || ! $this->clientSecret($provider)) {
            throw ValidationException::withMessages([
                'email' => [translate(':provider login is not configured.', ['provider' => Str::headline($provider)])],
            ]);
        }
    }

    private function driver(string $provider): mixed
    {
        $socialite = app('Laravel\\Socialite\\Contracts\\Factory');

        $config = [
            'client_id' => $this->clientId($provider),
            'client_secret' => $this->clientSecret($provider),
            'redirect' => route('social.callback', $provider),
        ];

        if ($provider === 'reddit') {
            $driver = $socialite->buildProvider('App\\Services\\Auth\\Socialite\\RedditProvider', $config);
        } else {
            if ($provider === 'twitter') {
                $config['oauth'] = 2;
            }

            $driverName = $provider === 'linkedin' ? 'linkedin-openid' : $provider;

            config()->set("services.{$driverName}", $config);
            $driver = $socialite->driver($driverName);
        }

        if (method_exists($driver, 'scopes')) {
            $driver->scopes($this->scopes($provider));
        }

        return $driver;
    }

    private function scopes(string $provider): array
    {
        return match ($provider) {
            'github' => ['read:user', 'user:email'],
            'reddit' => ['identity'],
            'twitter' => ['users.read', 'users.email', 'tweet.read'],
            default => ['email', 'profile'],
        };
    }

    private function providerEnabled(string $provider): bool
    {
        return (bool) settings("social_login_{$provider}_enabled", false);
    }

    private function clientId(string $provider): string
    {
        return (string) settings("social_login_{$provider}_client_id", '');
    }

    private function clientSecret(string $provider): string
    {
        return (string) settings("social_login_{$provider}_client_secret", '');
    }

    private function resolveEmail(string $provider, string $providerId, ?string $email): string
    {
        if ($email) {
            return $email;
        }

        return "{$provider}-{$providerId}@social-login.invalid";
    }

    private function throttleKey(Request $request, string $provider): string
    {
        return $provider.'|'.$request->ip();
    }
}

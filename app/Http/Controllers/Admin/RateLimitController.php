<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BannedIp;
use App\Models\UserRateLimitOverride;
use App\Services\RateLimiterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RateLimitController extends Controller
{
    public const CATEGORIES = [
        'text_gen' => 'AI Text Generation',
        'auth' => 'Login / Authentication',
        'otp' => 'OTP / 2FA Verification',
        'contact' => 'Contact Form',
        'comments' => 'Comments',
        'newsletter' => 'Newsletter',
        'public' => 'Public / Guest Tools',
        'social_auth' => 'Social Login',
    ];

    private const TIERS = ['guest', 'free_user', 'pro_user'];

    public function __construct(private RateLimiterService $rateLimiter) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Security/RateLimits', [
            'tiers' => $this->buildTierConfig(),
            'categories' => self::CATEGORIES,
            'bannedIps' => BannedIp::with('bannedBy')
                ->latest('banned_at')
                ->paginate(20),
            'overrides' => UserRateLimitOverride::with('user')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function updateTiers(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tiers' => ['required', 'array'],
            'tiers.*.category' => ['required', 'string', Rule::in(array_keys(self::CATEGORIES))],
            'tiers.*.guest_max' => ['required', 'integer', 'min:1', 'max:100000'],
            'tiers.*.guest_window' => ['required', 'integer', 'min:1', 'max:86400'],
            'tiers.*.free_max' => ['required', 'integer', 'min:1', 'max:100000'],
            'tiers.*.free_window' => ['required', 'integer', 'min:1', 'max:86400'],
            'tiers.*.pro_max' => ['required', 'integer', 'min:1', 'max:100000'],
            'tiers.*.pro_window' => ['required', 'integer', 'min:1', 'max:86400'],
        ]);

        foreach ($validated['tiers'] as $tier) {
            $category = $tier['category'];

            settings_set("rl_{$category}_guest_max", (string) $tier['guest_max'], 'integer', 'rate_limits');
            settings_set("rl_{$category}_guest_window", (string) $tier['guest_window'], 'integer', 'rate_limits');
            settings_set("rl_{$category}_free_user_max", (string) $tier['free_max'], 'integer', 'rate_limits');
            settings_set("rl_{$category}_free_user_window", (string) $tier['free_window'], 'integer', 'rate_limits');
            settings_set("rl_{$category}_pro_user_max", (string) $tier['pro_max'], 'integer', 'rate_limits');
            settings_set("rl_{$category}_pro_user_window", (string) $tier['pro_window'], 'integer', 'rate_limits');
        }

        return back()->with('success', translate('Rate limit tiers updated successfully.'));
    }

    public function banIp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip_address' => ['required', 'string', 'max:45'],
            'reason' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(array_keys(self::CATEGORIES))],
            'expires_in_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
        ]);

        $expiresInSeconds = isset($validated['expires_in_hours'])
            ? (int) $validated['expires_in_hours'] * 3600
            : null;

        $this->rateLimiter->banIp(
            $validated['ip_address'],
            $validated['reason'],
            $validated['category'],
            auth('admin')->id(),
            $expiresInSeconds
        );

        return back()->with('success', translate('IP banned successfully.'));
    }

    public function unbanIp(BannedIp $bannedIp): RedirectResponse
    {
        $this->rateLimiter->unbanIp($bannedIp->ip_address);

        return back()->with('success', translate('IP ban removed.'));
    }

    public function storeOverride(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'category' => ['required', 'string', Rule::in(array_keys(self::CATEGORIES))],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:100000'],
            'window_seconds' => ['required', 'integer', 'min:1', 'max:86400'],
            'expires_in_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
        ]);

        UserRateLimitOverride::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'category' => $validated['category'],
            ],
            [
                'max_attempts' => $validated['max_attempts'],
                'window_seconds' => $validated['window_seconds'],
                'expires_at' => isset($validated['expires_in_hours'])
                    ? now()->addHours((int) $validated['expires_in_hours'])
                    : null,
            ]
        );

        return back()->with('success', translate('Rate limit override saved.'));
    }

    public function deleteOverride(UserRateLimitOverride $override): RedirectResponse
    {
        $override->delete();

        return back()->with('success', translate('Rate limit override removed.'));
    }

    private function buildTierConfig(): array
    {
        $config = [];
        $defaults = $this->rateLimiter->getDefaults();

        foreach (array_keys(self::CATEGORIES) as $category) {
            $entry = ['category' => $category];

            foreach (self::TIERS as $tier) {
                $maxKey = "rl_{$category}_{$tier}_max";
                $windowKey = "rl_{$category}_{$tier}_window";

                $defaultTier = $defaults[$category][$tier] ?? ['max_attempts' => 10, 'window_seconds' => 60];

                $maxVal = (int) settings($maxKey);
                $windowVal = (int) settings($windowKey);

                $entry["{$tier}_max"] = $maxVal > 0 ? $maxVal : $defaultTier['max_attempts'];
                $entry["{$tier}_window"] = $windowVal > 0 ? $windowVal : $defaultTier['window_seconds'];
            }

            $config[] = $entry;
        }

        return $config;
    }
}

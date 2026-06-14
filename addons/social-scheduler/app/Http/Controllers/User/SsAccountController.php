<?php

namespace Addons\SocialScheduler\Http\Controllers\User;

use Addons\SocialScheduler\Models\SsSocialAccount;
use Addons\SocialScheduler\Services\SocialAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SsAccountController extends Controller
{
    public function index(): \Inertia\Response
    {
        $accounts = SsSocialAccount::where('user_id', auth()->id())->get()->map(fn ($a) => [
            'id' => $a->id,
            'platform' => $a->platform,
            'platform_label' => $a->platform_label,
            'platform_username' => $a->platform_username,
            'platform_name' => $a->platform_name,
            'avatar_url' => $a->avatar_url,
            'account_type' => $a->account_type,
            'is_active' => $a->is_active,
            'is_token_expired' => $a->is_token_expired,
            'follower_count' => $a->follower_count,
            'connected_at' => $a->created_at,
        ]);

        return inertia('Addons/social-scheduler/User/Accounts', [
            'accounts' => $accounts,
            'platforms_list' => [
                ['slug' => 'instagram', 'label' => 'Instagram', 'icon' => 'ti ti-brand-instagram'],
                ['slug' => 'facebook', 'label' => 'Facebook', 'icon' => 'ti ti-brand-facebook'],
                ['slug' => 'twitter', 'label' => 'X / Twitter', 'icon' => 'ti ti-brand-x'],
                ['slug' => 'linkedin', 'label' => 'LinkedIn', 'icon' => 'ti ti-brand-linkedin'],
            ],
        ]);
    }

    public function redirect(string $platform): RedirectResponse
    {
        app(SocialAccountService::class)->checkAccountLimit(auth()->user());
        $url = app(SocialAccountService::class)->getRedirectUrl($platform);

        return redirect()->away($url);
    }

    public function callback(string $platform): RedirectResponse
    {
        try {
            app(SocialAccountService::class)->handleCallback($platform, auth()->user());

            return redirect()->route('addon.social.user.accounts')
                ->with('flash', translate('Account connected successfully.'));
        } catch (\Throwable $e) {
            return redirect()->route('addon.social.user.accounts')
                ->with('error', $e->getMessage());
        }
    }

    public function disconnect(Request $request, SsSocialAccount $account): RedirectResponse
    {
        if ($account->user_id !== auth()->id()) {
            abort(403);
        }

        app(SocialAccountService::class)->disconnect($account);

        return back()->with('flash', translate('Account disconnected.'));
    }
}

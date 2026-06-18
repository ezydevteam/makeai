<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferencesController extends Controller
{
    /**
     * Available notification groups with their metadata.
     */
    private const GROUPS = [
        'billing' => [
            'icon' => 'ti ti-credit-card',
            'label' => 'Billing & Credits',
            'description' => 'Credit alerts, payment confirmations, subscription updates',
        ],
        'content' => [
            'icon' => 'ti ti-file-text',
            'label' => 'Content & Documents',
            'description' => 'Document processing, export ready notifications',
        ],
        'security' => [
            'icon' => 'ti ti-shield-lock',
            'label' => 'Security & Account',
            'description' => 'Password changes, new login alerts',
        ],
        'affiliate' => [
            'icon' => 'ti ti-affiliate',
            'label' => 'Affiliate & Rewards',
            'description' => 'Commission earned, payout notifications',
        ],
        'updates' => [
            'icon' => 'ti ti-bulb',
            'label' => 'Product Updates',
            'description' => 'Announcements, new features, tips',
        ],
        'admin' => [
            'icon' => 'ti ti-mail',
            'label' => 'Admin Messages',
            'description' => 'Direct messages from our team',
        ],
    ];

    public function index(): Response
    {
        $user = Auth::user();
        $preferences = $user->getNotificationPreferences();

        $groups = collect(self::GROUPS)->map(function ($meta, $key) use ($preferences) {
            return [
                'key' => $key,
                'icon' => $meta['icon'],
                'label' => translate($meta['label']),
                'description' => translate($meta['description']),
                'in_app' => $preferences['in_app'][$key] ?? true,
                'email' => $preferences['email'][$key] ?? true,
            ];
        })->values()->all();

        return Inertia::render('User/NotificationPreferences', [
            'groups' => $groups,
            'preferences' => $preferences,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $rules = [];
        foreach (array_keys(self::GROUPS) as $group) {
            $rules["in_app.{$group}"] = ['boolean'];
            $rules["email.{$group}"] = ['boolean'];
        }

        $validated = $request->validate($rules);

        $preferences = [
            'in_app' => $validated['in_app'] ?? [],
            'email' => $validated['email'] ?? [],
        ];

        $user->update(['notification_preferences' => $preferences]);

        return back()->with('success', translate('Notification preferences updated.'));
    }
}

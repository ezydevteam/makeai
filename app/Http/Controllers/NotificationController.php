<?php

namespace App\Http\Controllers;

use App\Services\InAppNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    private const NOTIFICATION_GROUPS = [
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

    public function index(Request $request, InAppNotificationService $notifications): Response
    {
        $status = $request->string('status')->toString();
        $status = in_array($status, ['read', 'unread'], true) ? $status : null;

        $user = $request->user();
        $preferences = $user->getNotificationPreferences();

        // Hide preference groups for features this install doesn't offer, so the
        // toggles match reality: no "Billing & Credits" without subscriptions, and
        // no "Affiliate & Rewards" when the affiliate program is disabled. Affiliate
        // has its own toggle (it can be on while subscriptions are off), so it is
        // gated independently rather than by isProAvailable().
        $hiddenGroups = [];
        if (! isProAvailable()) {
            $hiddenGroups[] = 'billing';
        }
        if (! (is_extended_license() && (bool) settings('affiliate_enabled', true))) {
            $hiddenGroups[] = 'affiliate';
        }

        $groups = collect(self::NOTIFICATION_GROUPS)
            ->except($hiddenGroups)
            ->map(function ($meta, $key) use ($preferences) {
                return [
                    'key' => $key,
                    'icon' => $meta['icon'],
                    'label' => translate($meta['label']),
                    'description' => translate($meta['description']),
                    'in_app' => $preferences['in_app'][$key] ?? true,
                    'email' => $preferences['email'][$key] ?? true,
                ];
            })->values()->all();

        return Inertia::render('User/Notifications', [
            'notificationList' => $notifications->paginate($user, $status),
            'unreadCount' => $user->unreadNotifications()->count(),
            'filters' => ['status' => $status],
            'notificationGroups' => $groups,
            'notificationPreferences' => $preferences,
        ]);
    }

    public function latest(Request $request, InAppNotificationService $notifications): JsonResponse|RedirectResponse
    {
        if ($fallback = $this->inertiaFallback($request)) {
            return $fallback;
        }

        return response()->json([
            'success' => true,
            'data' => $notifications->summary($request->user()),
            'message' => translate('Notifications loaded.'),
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $this->authorizeNotifications();
        $item = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $item->markAsRead();
        $item->forceFill(['status' => 'read'])->save();

        return response()->json([
            'success' => true,
            'data' => ['id' => $item->id],
            'message' => translate('Notification marked as read.'),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->authorizeNotifications();
        $request->user()->unreadNotifications()->update([
            'read_at' => now(),
            'status' => 'read',
        ]);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => translate('All notifications marked as read.'),
        ]);
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $this->authorizeNotifications();
        $user = $request->user();

        $rules = [];
        foreach (array_keys(self::NOTIFICATION_GROUPS) as $group) {
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

    private function authorizeNotifications(): void
    {
        if (! auth()->check()) {
            abort(403, translate('Unauthorized.'));
        }
    }

    /**
     * The notification action endpoints return JSON for the bell's fetch()/XHR
     * polling. If an Inertia navigation ever lands on one, answer with a redirect
     * back — a valid Inertia response — instead of bare JSON, which the Inertia
     * client rejects ("a plain JSON response was received"). Normal fetch polling
     * never sends the X-Inertia header, so this never fires for it.
     */
    private function inertiaFallback(Request $request): ?RedirectResponse
    {
        return $request->headers->has('X-Inertia') ? back() : null;
    }
}

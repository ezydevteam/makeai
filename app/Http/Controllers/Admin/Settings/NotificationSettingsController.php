<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NotificationSettingsRequest;
use App\Services\InAppNotificationService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotificationSettingsController extends Controller
{
    public function edit(InAppNotificationService $notifications): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Admin/Settings/Notifications', [
            'settings' => $notifications->settingsPayload(),
            'roles' => $notifications->adminRoles(),
            'recommendations' => [
                translate('Use Reverb in production when the websocket server is supervised. Polling remains the safest fallback for shared hosting demos.'),
                translate('Keep notifications enabled only when queues are running, because delivery uses the default queue.'),
            ],
        ]);
    }

    public function update(NotificationSettingsRequest $request)
    {
        $validated = $request->validated();

        settings_set('notifications_driver', $validated['notifications_driver'], 'string', 'notifications');
        settings_set('broadcasting_driver', $validated['notifications_driver'], 'string', 'notifications');

        settings_set('notifications_polling_interval', (int) $validated['notifications_polling_interval'], 'integer', 'notifications');
        settings_set('broadcasting_polling_interval_seconds', (int) ($validated['notifications_polling_interval'] / 1000), 'integer', 'notifications');

        $reverb = $validated['reverb'] ?? [];
        settings_set('notifications_reverb_app_id', $reverb['app_id'] ?? '', 'string', 'notifications');
        settings_set('broadcasting_reverb_app_id', $reverb['app_id'] ?? '', 'string', 'notifications');

        settings_set('notifications_reverb_app_key', $reverb['app_key'] ?? '', 'string', 'notifications');
        settings_set('broadcasting_reverb_app_key', $reverb['app_key'] ?? '', 'string', 'notifications');

        settings_set('notifications_reverb_host', $reverb['host'] ?? '', 'string', 'notifications');
        settings_set('broadcasting_reverb_host', $reverb['host'] ?? '', 'string', 'notifications');

        settings_set('notifications_reverb_port', (int) ($reverb['port'] ?? 8080), 'integer', 'notifications');
        settings_set('broadcasting_reverb_port', (int) ($reverb['port'] ?? 8080), 'integer', 'notifications');

        settings_set('notifications_reverb_scheme', $reverb['scheme'] ?? 'http', 'string', 'notifications');
        settings_set('broadcasting_reverb_scheme', $reverb['scheme'] ?? 'http', 'string', 'notifications');

        if (! empty($reverb['app_secret'])) {
            settings_set('notifications_reverb_app_secret', $reverb['app_secret'], 'encrypted', 'notifications');
            settings_set('broadcasting_reverb_app_secret', $reverb['app_secret'], 'encrypted', 'notifications');
        }

        $pusher = $validated['pusher'] ?? [];
        settings_set('notifications_pusher_app_id', $pusher['app_id'] ?? '', 'string', 'notifications');
        settings_set('broadcasting_pusher_app_id', $pusher['app_id'] ?? '', 'string', 'notifications');

        settings_set('notifications_pusher_key', $pusher['key'] ?? '', 'string', 'notifications');
        settings_set('broadcasting_pusher_key', $pusher['key'] ?? '', 'string', 'notifications');

        settings_set('notifications_pusher_cluster', $pusher['cluster'] ?? 'mt1', 'string', 'notifications');
        settings_set('broadcasting_pusher_cluster', $pusher['cluster'] ?? 'mt1', 'string', 'notifications');

        if (! empty($pusher['secret'])) {
            settings_set('notifications_pusher_secret', $pusher['secret'], 'encrypted', 'notifications');
            settings_set('broadcasting_pusher_secret', $pusher['secret'], 'encrypted', 'notifications');
        }

        return back()->with('success', translate('Notification settings saved.'));
    }

    public function test(InAppNotificationService $notifications): JsonResponse
    {
        $this->authorizeSettings();

        $settings = $notifications->settingsPayload();
        $driver = $settings['notifications_driver'] ?? settings('notifications_driver', 'reverb');

        if (! ($settings['notifications_enabled'] ?? $notifications->enabled())) {
            return response()->json([
                'success' => false,
                'code' => 'NOTIFICATIONS_DISABLED',
                'message' => translate('Notifications are disabled. Enable the system before testing delivery.'),
            ], 422);
        }

        if ($driver === 'polling') {
            return response()->json([
                'success' => true,
                'data' => ['driver' => 'polling'],
                'message' => translate('Polling fallback is ready. The bell refreshes automatically.'),
            ]);
        }

        $configured = $driver === 'reverb'
            ? filled($settings['reverb']['app_key']) && filled($settings['reverb']['host'])
            : filled($settings['pusher']['key']) && filled($settings['pusher']['cluster']);

        return response()->json([
            'success' => $configured,
            'data' => ['driver' => $driver],
            'message' => $configured
                ? translate('Realtime credentials are configured. Start the websocket server and queue worker to complete delivery.')
                : translate('Realtime credentials are incomplete. Polling fallback will continue to work.'),
        ], $configured ? 200 : 422);
    }

    private function authorizeSettings(): void
    {
        abort_unless(auth('admin')->user()?->hasPermission('settings.general'), 403);
    }
}

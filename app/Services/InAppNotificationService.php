<?php

namespace App\Services;

use App\Jobs\SendInAppNotification;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Notifications\InAppNotification;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class InAppNotificationService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(Model $notifiable, array $payload, bool $queue = true, ?DateTimeInterface $delayUntil = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        $payload = $this->normalizePayload($payload);

        if ($queue) {
            $dispatch = SendInAppNotification::dispatch($notifiable, $payload)->onQueue('default');

            if ($delayUntil) {
                $dispatch->delay($delayUntil);
            }

            return;
        }

        NotificationFacade::sendNow($notifiable, new InAppNotification($payload));
    }

    /**
     * @param  iterable<Model>  $notifiables
     * @param  array<string, mixed>  $payload
     */
    public function sendMany(iterable $notifiables, array $payload): void
    {
        foreach ($notifiables as $notifiable) {
            $this->send($notifiable, $payload);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function notifyAdmins(array $payload, ?string $roleSlug = null): void
    {
        $admins = Admin::query()
            ->where('is_active', true)
            ->when($roleSlug, function ($query) use ($roleSlug) {
                $query->whereHas('role', fn ($role) => $role->where('slug', $roleSlug));
            })
            ->get();

        $this->sendMany($admins, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function notifyAdminsWithPermission(string $permission, array $payload): void
    {
        $admins = Admin::query()
            ->where('is_active', true)
            ->with('role.permissions')
            ->get()
            ->filter(fn (Admin $admin) => $admin->hasPermission($permission));

        $this->sendMany($admins, $payload);
    }

    /**
     * @return LengthAwarePaginator<DatabaseNotification>
     */
    public function paginate(Model $notifiable, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        return $notifiable->notifications()
            ->when($status === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->when($status === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate($perPage)
            ->through(fn (DatabaseNotification $notification) => $this->format($notification));
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Model $notifiable): array
    {
        $broadcasting = app(BroadcastingService::class);

        if (! $this->enabled()) {
            return [
                'enabled' => false,
                'driver' => 'disabled',
                'polling_interval' => $broadcasting->pollingIntervalSeconds() * 1000,
                'unread_count' => 0,
                'items' => [],
            ];
        }

        return [
            'enabled' => true,
            'driver' => $broadcasting->resolveDriver(),
            'polling_interval' => $broadcasting->pollingIntervalSeconds() * 1000,
            'unread_count' => $notifiable->unreadNotifications()->count(),
            'items' => $notifiable->notifications()
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (DatabaseNotification $notification) => $this->format($notification))
                ->values()
                ->all(),
            'realtime' => $this->publicRealtimeConfig(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsPayload(): array
    {
        $payload = app(BroadcastingService::class)->configPayload();

        return array_merge($payload, [
            'notifications_enabled' => $this->enabled(),
            'notifications_driver' => $payload['driver'] ?? 'reverb',
            'notifications_polling_interval' => (int) settings('notifications_polling_interval', 30000),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function publicRealtimeConfig(): array
    {
        return app(BroadcastingService::class)->frontendConfig();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function adminRoles(): array
    {
        return AdminRole::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (AdminRole $role) => $role->only(['id', 'name', 'slug']))
            ->all();
    }

    public function enabled(): bool
    {
        return (bool) settings('notifications_enabled', true);
    }

    /**
     * @return array<string, mixed>
     */
    public function format(DatabaseNotification $notification): array
    {
        $data = $notification->data ?? [];

        return [
            'id' => $notification->id,
            'title' => $data['title'] ?? translate('Notification'),
            'message' => $data['message'] ?? '',
            'category' => $data['category'] ?? 'system',
            'level' => $data['level'] ?? 'info',
            'icon' => $data['icon'] ?? null,
            'action_url' => $data['action_url'] ?? null,
            'action_label' => $data['action_label'] ?? null,
            'meta' => $data['meta'] ?? [],
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
            'is_read' => $notification->read_at !== null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        return [
            'title' => trim((string) ($payload['title'] ?? translate('Notification'))),
            'message' => trim((string) ($payload['message'] ?? '')),
            'category' => (string) ($payload['category'] ?? 'system'),
            'level' => in_array($payload['level'] ?? 'info', ['info', 'success', 'warning', 'error'], true)
                ? $payload['level']
                : 'info',
            'icon' => $payload['icon'] ?? null,
            'action_url' => $payload['action_url'] ?? null,
            'action_label' => $payload['action_label'] ?? null,
            'meta' => $payload['meta'] ?? [],
        ];
    }
}

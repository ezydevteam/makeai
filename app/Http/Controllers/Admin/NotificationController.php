<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\InAppNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request, InAppNotificationService $notifications): Response
    {
        $this->authorizeView();

        $status = $request->string('status')->toString();
        $status = in_array($status, ['read', 'unread'], true) ? $status : null;

        return Inertia::render('Admin/Notifications', [
            'notifications' => $notifications->paginate(auth('admin')->user(), $status),
            'filters' => ['status' => $status],
        ]);
    }

    public function latest(Request $request, InAppNotificationService $notifications): JsonResponse|RedirectResponse
    {
        $this->authorizeView();

        // See NotificationController::inertiaFallback — keep an accidental Inertia
        // navigation from receiving bare JSON, which the Inertia client rejects.
        if ($request->headers->has('X-Inertia')) {
            return back();
        }

        return response()->json([
            'success' => true,
            'data' => $notifications->summary(auth('admin')->user()),
            'message' => translate('Notifications loaded.'),
        ]);
    }

    public function markRead(string $notification): JsonResponse
    {
        $this->authorizeView();

        $item = auth('admin')->user()
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

    public function markAllRead(): JsonResponse
    {
        $this->authorizeView();

        auth('admin')->user()->unreadNotifications()->update([
            'read_at' => now(),
            'status' => 'read',
        ]);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => translate('All notifications marked as read.'),
        ]);
    }

    private function authorizeView(): void
    {
        abort_unless(auth('admin')->user()?->hasAnyPermission(['dashboard.view', 'settings.general']), 403);
    }
}

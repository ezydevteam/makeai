<?php

namespace App\Http\Controllers;

use App\Services\InAppNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request, InAppNotificationService $notifications): Response
    {
        $status = $request->string('status')->toString();
        $status = in_array($status, ['read', 'unread'], true) ? $status : null;

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications->paginate($request->user(), $status),
            'filters' => ['status' => $status],
        ]);
    }

    public function latest(Request $request, InAppNotificationService $notifications): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $notifications->summary($request->user()),
            'message' => translate('Notifications loaded.'),
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
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
}

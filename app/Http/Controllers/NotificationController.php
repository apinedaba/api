<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use App\Services\NotificationPayload;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifiable = $request->user();
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 50);

        $notifications = $notifiable
            ->notifications()
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'data' => $notifications
                ->getCollection()
                ->map(fn(DatabaseNotification $notification) => NotificationPayload::fromDatabaseNotification($notification))
                ->values(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $notifiable->unreadNotifications()->count(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();

        if (!$notification->read_at) {
            $notification->markAsRead();
        }

        return response()->json([
            'message' => 'Notificacion marcada como leida.',
            'data' => NotificationPayload::fromDatabaseNotification($notification->fresh()),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'Todas las notificaciones fueron marcadas como leidas.',
            'unread_count' => 0,
        ]);
    }
}

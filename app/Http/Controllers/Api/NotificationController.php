<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sprint 4 — exposes the Laravel database notifications channel
 * (Notifiable::notifications) to the SPA bell icon.
 *
 * Endpoints:
 *   GET    /api/notifications              → paginated list (15 per page)
 *   GET    /api/notifications/unread-count → small JSON for the header badge
 *   POST   /api/notifications/{id}/read    → mark a single notif as read
 *   POST   /api/notifications/read-all     → mark every unread notif as read
 *   DELETE /api/notifications/{id}         → delete a single notif
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = $request->user()
            ->notifications()
            ->paginate(15)
            ->through(fn ($n) => $this->present($n));

        return response()->json($items);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notif = $request->user()->notifications()->whereKey($id)->first();
        if (!$notif) {
            return response()->json(['message' => 'Notification introuvable.'], 404);
        }
        $notif->markAsRead();
        return response()->json(['message' => 'OK', 'notification' => $this->present($notif->fresh())]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->count();
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['message' => 'OK', 'marked' => $count]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $notif = $request->user()->notifications()->whereKey($id)->first();
        if (!$notif) {
            return response()->json(['message' => 'Notification introuvable.'], 404);
        }
        $notif->delete();
        return response()->json(['message' => 'OK']);
    }

    /**
     * Flatten a DatabaseNotification into the SPA-friendly shape.
     */
    protected function present($n): array
    {
        $payload = (array) $n->data;
        return [
            'id'         => $n->id,
            'type'       => $payload['type'] ?? class_basename($n->type),
            'data'       => $payload,
            'read_at'    => $n->read_at,
            'created_at' => $n->created_at,
        ];
    }
}

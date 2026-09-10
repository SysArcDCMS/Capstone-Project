<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notification controller — capstone DFD 4.8 (Generate Engineer Notification)
 * and DFD 5.10 (Send Push Notification to Mobile Device).
 *
 * "Push" semantics for the capstone are implemented as a polling endpoint
 * that the mobile/web clients call periodically. Each row has a message and
 * an is_read flag; clients PATCH it to acknowledge.
 */
class NotificationController extends Controller
{
    /**
     * GET /api/notifications
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = Notification::where('user_id', $user->id)
            ->with('incident:id,category,severity,status')
            ->orderByDesc('created_at');

        if ($request->boolean('unread_only')) {
            $query->where('is_read', false);
        }

        $unreadCount = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        $envelope = $this->paginated($query->paginate((int) $request->query('per_page', 20)));

        return response()->json([
            'data' => $envelope['data'],
            'meta' => $envelope['meta'],
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * PATCH /api/notifications/{id}/read
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $notification) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        $notification->is_read = true;
        $notification->updated_by = $user->id;
        $notification->save();

        return response()->json(['data' => $notification]);
    }

    /**
     * POST /api/notifications/mark-all-read
     */
    public function markAllRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $count = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read'    => true,
                'updated_by' => $user->id,
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => "Marked {$count} notifications as read.",
        ]);
    }
}

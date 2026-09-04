<?php

namespace App\Http\Controllers;

use App\Models\NotificationLog;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationsController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Display a listing of the user's notifications.
     */
    public function index(Request $request)
    {
        $notifications = $this->notificationService->getHistory(
            $request->user(),
            limit: 50
        );

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * Get recent notifications for the notification bell dropdown.
     */
    public function recent(Request $request): JsonResponse
    {
        $notifications = $this->notificationService->getHistory(
            $request->user(),
            limit: 5
        );

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $this->notificationService->getUnreadCount($request->user()),
        ]);
    }

    /**
     * Mark the specified notification as read.
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $notification = NotificationLog::where('notifiable_id', $request->user()->id)
            ->where('notifiable_type', get_class($request->user()))
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'unread_count' => $this->notificationService->getUnreadCount($request->user()),
        ]);
    }
}

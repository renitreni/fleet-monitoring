<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send a notification to the user and log it.
     */
    public function send(User $user, string $type, array $data = []): NotificationLog
    {
        // Create the notification log entry
        $notification = NotificationLog::create([
            'notifiable_id' => $user->id,
            'notifiable_type' => User::class,
            'notification_type' => $type,
            'data' => $data,
            'sent_at' => now(),
        ]);

        // In a real app, this is where you'd send actual notifications
        // (email, SMS, push notifications, etc.)
        // For now, we just log to the database

        Log::info('Notification sent', [
            'user_id' => $user->id,
            'type' => $type,
            'data' => $data,
        ]);

        return $notification;
    }

    /**
     * Get notification history for a user.
     */
    public function getHistory(User $user, int $limit = 50)
    {
        return NotificationLog::where('notifiable_id', $user->id)
            ->where('notifiable_type', User::class)
            ->orderBy('sent_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get unread notification count for a user.
     */
    public function getUnreadCount(User $user): int
    {
        return NotificationLog::where('notifiable_id', $user->id)
            ->where('notifiable_type', User::class)
            ->whereNull('read_at')
            ->count();
    }
}

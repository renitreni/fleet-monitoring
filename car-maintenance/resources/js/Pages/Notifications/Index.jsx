import { useState } from 'react';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';

export default function NotificationsIndex({ notifications: initialNotifications }) {
    const [notifications, setNotifications] = useState(initialNotifications);
    const [markingIds, setMarkingIds] = useState(new Set());

    const getNotificationMessage = (notification) => {
        switch (notification.notification_type) {
            case 'oil_change_due_soon':
                return `Oil change for ${notification.data.car_make} ${notification.data.car_model} is due soon`;
            case 'oil_change_overdue':
                return `Oil change for ${notification.data.car_make} ${notification.data.car_model} is overdue!`;
            case 'car_added':
                return `Car ${notification.data.car_make} ${notification.data.car_model} added successfully`;
            case 'oil_change_recorded':
                return `Oil change recorded for ${notification.data.car_make} ${notification.data.car_model}`;
            case 'mileage_updated':
                return `Mileage updated for ${notification.data.car_make} ${notification.data.car_model}`;
            default:
                return notification.data.message || 'New notification';
        }
    };

    const formatDateTime = (dateTime) => {
        const date = new Date(dateTime);
        return date.toLocaleString();
    };

    const markAsRead = async (id) => {
        setMarkingIds((prev) => new Set(prev).add(id));

        try {
            await axios.post(`/notifications/${id}/read`);

            setNotifications((prev) =>
                prev.map((notification) =>
                    notification.id === id ? { ...notification, read_at: new Date().toISOString() } : notification
                )
            );
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        } finally {
            setMarkingIds((prev) => {
                const next = new Set(prev);
                next.delete(id);
                return next;
            });
        }
    };

    return (
        <AuthenticatedLayout
            title="Notifications"
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Notification History</h2>}
        >
            <div className="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div className="p-6">
                        {notifications.length === 0 ? (
                            <EmptyState
                                className="py-12 shadow-none"
                                icon="🔔"
                                title="No notifications"
                                description="You don't have any notifications yet."
                            />
                        ) : (
                            <div className="space-y-4">
                                {notifications.map((notification) => {
                                    const isRead = notification.read_at !== null;
                                    const isMarking = markingIds.has(notification.id);

                                    return (
                                        <div
                                            key={notification.id}
                                            className={`border-b border-gray-200 pb-4 last:border-b-0 ${
                                                isRead ? 'opacity-60' : ''
                                            }`}
                                        >
                                            <div className="flex items-start justify-between gap-4">
                                                <div className="flex-1">
                                                    <div className="flex items-center gap-2">
                                                        {!isRead && (
                                                            <span
                                                                className="inline-block h-2 w-2 rounded-full bg-blue-600"
                                                                aria-hidden="true"
                                                            />
                                                        )}
                                                        <h3
                                                            className={`text-sm font-medium ${
                                                                isRead ? 'text-gray-500 line-through' : 'text-gray-900'
                                                            }`}
                                                        >
                                                            {getNotificationMessage(notification)}
                                                        </h3>
                                                    </div>
                                                    {notification.data.details && (
                                                        <p className="mt-1 text-sm text-gray-600">
                                                            {notification.data.details}
                                                        </p>
                                                    )}
                                                    <span className="mt-1 block text-xs text-gray-500">
                                                        {formatDateTime(notification.sent_at)}
                                                    </span>
                                                </div>
                                                {!isRead && (
                                                    <button
                                                        onClick={() => markAsRead(notification.id)}
                                                        disabled={isMarking}
                                                        className="shrink-0 rounded-md bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
                                                    >
                                                        {isMarking ? 'Marking…' : 'Mark as read'}
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

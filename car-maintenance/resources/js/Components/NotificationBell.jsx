import { useState, useEffect } from 'react';
import { Link } from '@inertiajs/react';
import axios from 'axios';
import LoadingSkeleton from '@/Components/LoadingSkeleton';

export default function NotificationBell() {
    const [notifications, setNotifications] = useState([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [isOpen, setIsOpen] = useState(false);
    const [isLoading, setIsLoading] = useState(true);
    const [markingIds, setMarkingIds] = useState(new Set());

    useEffect(() => {
        loadRecentNotifications();

        // Set up polling to refresh notifications every 30 seconds
        const interval = setInterval(loadRecentNotifications, 30000);

        return () => clearInterval(interval);
    }, []);

    const loadRecentNotifications = async () => {
        try {
            setIsLoading(true);
            const response = await axios.get('/api/notifications/recent');
            setNotifications(response.data.notifications || []);
            setUnreadCount(response.data.unread_count || 0);
        } catch (error) {
            console.error('Failed to load notifications:', error);
        } finally {
            setIsLoading(false);
        }
    };

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

    const markAsRead = async (notification) => {
        if (notification.read_at || markingIds.has(notification.id)) {
            return;
        }

        setMarkingIds((current) => new Set(current).add(notification.id));

        try {
            const response = await axios.post(`/notifications/${notification.id}/read`);

            setNotifications((current) =>
                current.map((item) =>
                    item.id === notification.id ? { ...item, read_at: new Date().toISOString() } : item,
                ),
            );
            setUnreadCount(response.data.unread_count ?? 0);
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        } finally {
            setMarkingIds((current) => {
                const next = new Set(current);
                next.delete(notification.id);
                return next;
            });
        }
    };

    const formatTimeAgo = (sentAt) => {
        const now = new Date();
        const sent = new Date(sentAt);
        const diffInMs = now - sent;
        const diffInMinutes = Math.floor(diffInMs / 60000);
        const diffInHours = Math.floor(diffInMs / 3600000);
        const diffInDays = Math.floor(diffInMs / 86400000);

        if (diffInMinutes < 1) {
            return 'Just now';
        } else if (diffInMinutes < 60) {
            return `${diffInMinutes}m ago`;
        } else if (diffInHours < 24) {
            return `${diffInHours}h ago`;
        } else {
            return `${diffInDays}d ago`;
        }
    };

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                aria-label={`Notifications${unreadCount > 0 ? ` (${unreadCount} unread)` : ''}`}
                aria-expanded={isOpen}
                className="relative grid h-10 w-10 place-items-center border border-[var(--border)] bg-[var(--surface-muted)] text-[var(--text-muted)] transition hover:border-[var(--accent)] hover:text-[var(--accent)] focus:outline-none focus:ring-2 focus:ring-[var(--accent)]"
            >
                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 00-2-2H5a2 2 0 00-2 2v.341C3.595 7.218 4.148 8.39 5 9.158V11a6.002 6.002 0 004 5.659z"
                    />
                </svg>
                {unreadCount > 0 && (
                    <span className="absolute -top-1 -right-1 flex h-4 w-4">
                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                        <span className="relative inline-flex rounded-full h-4 w-4 bg-red-500 text-white text-xs items-center justify-center">
                            {unreadCount}
                        </span>
                    </span>
                )}
            </button>

            {isOpen && (
                <div className="absolute right-0 z-50 mt-2 w-80 origin-top-right border border-[var(--border)] bg-[var(--surface)] text-[var(--text)] shadow-2xl">
                    <div className="p-2">
                        <div className="flex items-center justify-between border-b border-[var(--border)] px-4 py-3">
                            <h3 className="text-xs font-black uppercase tracking-[0.14em]">Notifications</h3>
                            <Link href="/notifications" className="text-xs font-bold text-[var(--accent)]">
                                View all
                            </Link>
                        </div>
                        {isLoading ? (
                            <div className="px-4 py-4">
                                <LoadingSkeleton lines={2} />
                            </div>
                        ) : notifications.length === 0 ? (
                            <div className="px-4 py-4 text-sm text-[var(--text-muted)]">No notifications</div>
                        ) : (
                            <div className="max-h-60 overflow-y-auto">
                                {notifications.map((notification) => (
                                    <button
                                        type="button"
                                        key={notification.id}
                                        onClick={() => markAsRead(notification)}
                                        disabled={notification.read_at !== null || markingIds.has(notification.id)}
                                        className="block w-full border-b border-[var(--border)] px-4 py-3 text-left last:border-b-0 enabled:hover:bg-[var(--surface-muted)] disabled:cursor-default"
                                    >
                                        <p className={`text-sm ${notification.read_at ? 'text-[var(--text-muted)]' : 'font-semibold text-[var(--text)]'}`}>
                                            {getNotificationMessage(notification)}
                                        </p>
                                        <p className="mt-1 text-xs text-[var(--text-muted)]">
                                            {formatTimeAgo(notification.sent_at)}
                                        </p>
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

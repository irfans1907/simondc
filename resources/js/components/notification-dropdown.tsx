import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useEchoNotification } from '@laravel/echo-react';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import sharedWithMe from '@/routes/shared-with-me';
import files from '@/routes/files';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Auth } from '@/types/auth';
import { Bell, Check, Download, Share2, Info, UserX } from 'lucide-react';

type Notification = {
    id: string;
    data: {
        type: string;
        message: string;
        file_id?: number;
        file_name?: string;
        url?: string;
    };
    read_at: string | null;
    created_at: string;
};

function formatTime(isoString: string): string {
    const diff = Math.floor((Date.now() - new Date(isoString).getTime()) / 1000);

    if (diff < 60) {
        return 'Just now';
    }
    if (diff < 3600) {
        return `${Math.floor(diff / 60)}m ago`;
    }
    if (diff < 86400) {
        return `${Math.floor(diff / 3600)}h ago`;
    }

    return `${Math.floor(diff / 86400)}d ago`;
}

function getNotificationIcon(type: string) {
    switch (type) {
        case 'file_shared':
            return <Share2 className="size-4" />;
        case 'file_downloaded':
            return <Download className="size-4" />;
        case 'file_share_removed':
            return <UserX className="size-4" />;
        default:
            return <Info className="size-4" />;
    }
}

export default function NotificationDropdown() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [open, setOpen] = useState(false);
    const [hasNewNotification, setHasNewNotification] = useState(false);

    const fetchNotifications = () => {
        fetch(NotificationController.index.url(), {
            headers: { Accept: 'application/json' },
        })
            .then((res) => res.json())
            .then((data) => setNotifications(data));

        fetch(NotificationController.unreadCount.url(), {
            headers: { Accept: 'application/json' },
        })
            .then((res) => res.json())
            .then((data) => setUnreadCount(data.count));
    };

    useEffect(() => {
        fetchNotifications();
    }, []);

    useEchoNotification(
        `App.Models.User.${auth.user.id}`,
        (payload: Record<string, unknown>) => {
            const formatted: Notification = {
                id: `broadcast-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`,
                data: payload as unknown as Notification['data'],
                read_at: null,
                created_at: new Date().toISOString(),
            };
            setNotifications((prev) => [formatted, ...prev].slice(0, 20));
            setUnreadCount((prev) => prev + 1);
            setHasNewNotification(true);
        },
    );

    const handleMarkAsRead = (id: string) => {
        fetch(NotificationController.markAsRead.url(id), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                ),
            },
        }).then(() => {
            setNotifications((prev) =>
                prev.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n)),
            );
            setUnreadCount((prev) => Math.max(0, prev - 1));
        });
    };

    const handleMarkAllAsRead = () => {
        fetch(NotificationController.markAllAsRead.url(), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                ),
            },
        }).then(() => {
            setNotifications((prev) =>
                prev.map((n) => ({ ...n, read_at: n.read_at ?? new Date().toISOString() })),
            );
            setUnreadCount(0);
        });
    };

    return (
        <DropdownMenu
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (next) {
                    setHasNewNotification(false);
                    fetchNotifications();
                }
            }}
        >
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative h-9 w-9 focus-visible:ring-0"
                    data-tooltip="Notifications"
                    data-tooltip-bottom
                >
                    <Bell
                        className={`size-4.5 origin-top transition-transform ${
                            hasNewNotification && !open ? 'animate-bell-ring text-primary' : ''
                        }`}
                    />
                    {hasNewNotification && !open && (
                        <span className="absolute -top-0.5 -right-0.5 flex size-4 items-center justify-center">
                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-destructive opacity-75" />
                            <span className="relative flex size-4 items-center justify-center rounded-full bg-destructive text-[10px] font-bold text-destructive-foreground">
                                {unreadCount > 9 ? '9+' : unreadCount}
                            </span>
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                <div className="flex items-center justify-between px-4 py-2">
                    <p className="text-sm font-medium">Notifications</p>
                    {unreadCount > 0 && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 text-xs"
                            onClick={handleMarkAllAsRead}
                        >
                            <Check className="mr-1 size-3" />
                            Mark all read
                        </Button>
                    )}
                </div>
                {notifications.length === 0 ? (
                    <div className="px-4 py-8 text-center text-sm text-muted-foreground">
                        No notifications yet.
                    </div>
                ) : (
                    <div className="max-h-80 overflow-y-auto">
                        {notifications.map((notification) => (
                            <DropdownMenuItem
                                key={notification.id}
                                className={`flex cursor-pointer items-start gap-3 px-4 py-3 ${
                                    !notification.read_at ? 'bg-muted/50' : ''
                                }`}
                                onClick={() => {
                                    if (!notification.read_at) {
                                        handleMarkAsRead(notification.id);
                                    }
                                    setOpen(false);
                                    if (notification.data.type === 'system' && notification.data.url) {
                                        window.open(notification.data.url, '_blank');
                                    } else if (notification.data.type === 'file_shared' || notification.data.type === 'file_share_removed') {
                                        router.visit(sharedWithMe.index.url());
                                    } else if (notification.data.type === 'file_downloaded') {
                                        router.visit(files.index.url());
                                    }
                                }}
                            >
                                <div className="mt-0.5 text-muted-foreground">
                                    {getNotificationIcon(notification.data.type)}
                                </div>
                                <div className="flex-1 space-y-0.5">
                                    <p className="text-sm">{notification.data.message}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {formatTime(notification.created_at)}
                                    </p>
                                </div>
                                {!notification.read_at && (
                                    <div className="mt-1 size-2 shrink-0 rounded-full bg-primary" />
                                )}
                            </DropdownMenuItem>
                        ))}
                    </div>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

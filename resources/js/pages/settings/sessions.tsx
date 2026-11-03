import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import SessionSettingController from '@/actions/App/Http/Controllers/Settings/SessionSettingController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { edit } from '@/routes/session-settings';
import { Monitor, Smartphone } from 'lucide-react';

type Session = {
    id: string;
    ip_address: string;
    browser: string;
    os: string;
    last_activity: number;
    is_current: boolean;
};

type PageProps = {
    sessions: Session[];
};

function formatLastActivity(timestamp: number): string {
    const diff = Math.floor(Date.now() / 1000) - timestamp;

    if (diff < 60) {
        return 'Just now';
    }
    if (diff < 3600) {
        return `${Math.floor(diff / 60)} min ago`;
    }
    if (diff < 86400) {
        return `${Math.floor(diff / 3600)} hours ago`;
    }

    return `${Math.floor(diff / 86400)} days ago`;
}

export default function SessionSettings() {
    const { sessions } = usePage<PageProps>().props;
    const [revokeId, setRevokeId] = useState<string | null>(null);

    const handleRevoke = () => {
        if (!revokeId) return;

        router.delete(SessionSettingController.destroy.url(revokeId), {
            preserveScroll: true,
            onFinish: () => setRevokeId(null),
        });
    };

    return (
        <>
            <Head title="Session settings" />

            <h1 className="sr-only">Session settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Sessions"
                    description="Manage your active sessions and revoke access"
                />

                <div className="space-y-3">
                    {sessions.map((session) => (
                        <div
                            key={session.id}
                            className="flex items-center justify-between rounded-lg border p-4"
                        >
                            <div className="flex items-center gap-3">
                                {session.os === 'iOS' || session.os === 'Android' ? (
                                    <Smartphone className="size-8 text-muted-foreground" />
                                ) : (
                                    <Monitor className="size-8 text-muted-foreground" />
                                )}
                                <div className="space-y-0.5">
                                    <div className="flex items-center gap-2">
                                        <p className="text-sm font-medium">
                                            {session.browser} · {session.os}
                                        </p>
                                        {session.is_current && (
                                            <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                                                Current session
                                            </span>
                                        )}
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {session.ip_address} ·{' '}
                                        {formatLastActivity(session.last_activity)}
                                    </p>
                                </div>
                            </div>

                            {!session.is_current && (
                                <Dialog>
                                    <DialogTrigger asChild>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            onClick={() => setRevokeId(session.id)}
                                        >
                                            Revoke
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogTitle>Revoke this session?</DialogTitle>
                                        <DialogDescription>
                                            The user will be logged out from this device
                                            immediately.
                                        </DialogDescription>
                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button variant="secondary">Cancel</Button>
                                            </DialogClose>
                                            <Button
                                                variant="destructive"
                                                onClick={handleRevoke}
                                            >
                                                Revoke
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            )}
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}

SessionSettings.layout = {
    breadcrumbs: [
        {
            title: 'Session settings',
            href: edit(),
        },
    ],
};

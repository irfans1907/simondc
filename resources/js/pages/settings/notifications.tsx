import { Form, Head, router, usePage } from '@inertiajs/react';
import NotificationSettingController from '@/actions/App/Http/Controllers/Settings/NotificationSettingController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/notification-settings';

type NotificationType = {
    label: string;
    description: string;
};

type PageProps = {
    preferences: Record<string, boolean>;
    types: Record<string, NotificationType>;
};

export default function NotificationSettings() {
    const { preferences, types } = usePage<PageProps>().props;

    const handleToggle = (type: string, enabled: boolean) => {
        router.patch(
            NotificationSettingController.update.url(),
            { preferences: { [type]: enabled } },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Notification settings" />

            <h1 className="sr-only">Notification settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Notifications"
                    description="Manage your notification preferences"
                />

                <div className="space-y-4">
                    {Object.entries(types).map(([type, info]) => (
                        <div
                            key={type}
                            className="flex items-center justify-between rounded-lg border p-4"
                        >
                            <div className="space-y-0.5">
                                <p className="text-sm font-medium">{info.label}</p>
                                <p className="text-sm text-muted-foreground">
                                    {info.description}
                                </p>
                            </div>
                            <button
                                type="button"
                                role="switch"
                                aria-checked={preferences[type] ?? true}
                                onClick={() =>
                                    handleToggle(type, !(preferences[type] ?? true))
                                }
                                className={`relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full transition-colors ${
                                    preferences[type] ?? true
                                        ? 'bg-neutral-700 dark:bg-neutral-200'
                                        : 'bg-neutral-400 dark:bg-neutral-600'
                                }`}
                            >
                                <span
                                    className={`pointer-events-none inline-block h-4 w-4 rounded-full shadow-sm transition-transform ${
                                        preferences[type] ?? true
                                            ? 'translate-x-[18px] bg-white dark:bg-neutral-800'
                                            : 'translate-x-[2px] bg-white dark:bg-neutral-800'
                                    }`}
                                />
                            </button>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}

NotificationSettings.layout = {
    breadcrumbs: [
        {
            title: 'Notification settings',
            href: edit(),
        },
    ],
};

import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';

export default function DynamicPage({
    menu,
    canManage,
}: {
    menu: { id: number; name: string; group: string; url: string; icon: string };
    canManage: boolean;
}) {
    return (
        <>
            <Head title={menu.name} />
            <h1 className="sr-only">{menu.name}</h1>
            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title={menu.name}
                    description="This page was automatically created from the menu."
                />
                {canManage && (
                    <p className="text-sm text-muted-foreground">
                        You have <strong>manage</strong> permission on this page.
                    </p>
                )}
            </div>
        </>
    );
}

DynamicPage.layout = (props: { menu: { name: string; url: string } }) => ({
    breadcrumbs: [
        {
            title: props.menu.name,
            href: props.menu.url,
        },
    ],
});

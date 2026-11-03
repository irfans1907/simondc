import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';

export default function ReportsOverview({ menu, canManage }: { menu: { id: number; name: string; group: string; url: string; icon: string }; canManage: boolean }) {
    return (
        <>
            <Head title="Overview" />
            <h1 className="sr-only">Overview</h1>
            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="Overview"
                    description="This page was automatically created from the menu."
                />
            </div>
        </>
    );
}

ReportsOverview.layout = (props: { menu: { name: string } }) => ({
    breadcrumbs: [
        {
            title: props.menu.name,
            href: '#',
        },
    ],
});

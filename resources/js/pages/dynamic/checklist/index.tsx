import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';

export default function Checklist() {
    return (
        <>
            <Head title="Checklist" />
            <h1 className="sr-only">Checklist</h1>
            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="Checklist"
                    description="This page was automatically created from the menu."
                />
            </div>
        </>
    );
}

Checklist.layout = (props: { menu: { name: string; url: string } }) => ({
    breadcrumbs: [
        {
            title: props.menu.name,
            href: props.menu.url,
        },
    ],
});

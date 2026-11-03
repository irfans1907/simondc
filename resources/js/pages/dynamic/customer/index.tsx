import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';

export default function Customer() {
    return (
        <>
            <Head title="Customer List" />
            <h1 className="sr-only">Customer List</h1>
            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="Customer List"
                    description="This page was automatically created from the menu."
                />
            </div>
        </>
    );
}

Customer.layout = (props: { menu: { name: string; url: string } }) => ({
    breadcrumbs: [
        {
            title: props.menu.name,
            href: props.menu.url,
        },
    ],
});

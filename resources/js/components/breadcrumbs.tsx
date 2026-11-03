import { Link } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { getDraggedIds } from '@/lib/file-drag';
import { moveFiles } from '@/lib/file-move';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function Breadcrumbs({
    breadcrumbs,
}: {
    breadcrumbs: BreadcrumbItemType[];
}) {
    const [hoverIndex, setHoverIndex] = useState<number | null>(null);

    return (
        <>
            {breadcrumbs.length > 0 && (
                <Breadcrumb>
                    <BreadcrumbList>
                        {breadcrumbs.map((item, index) => {
                            const isLast = index === breadcrumbs.length - 1;

                            return (
                                <Fragment key={index}>
                                    <BreadcrumbItem>
                                        {isLast ? (
                                            <BreadcrumbLink
                                                asChild
                                                className="text-foreground hover:underline"
                                            >
                                                <Link
                                                    href={item.href}
                                                    aria-current="page"
                                                >
                                                    {item.title}
                                                </Link>
                                            </BreadcrumbLink>
                                        ) : (
                                            <BreadcrumbLink asChild>
                                                <Link
                                                    href={item.href}
                                                    data-drop-target={
                                                        item.folderId ===
                                                        undefined
                                                            ? undefined
                                                            : String(
                                                                  item.folderId,
                                                              )
                                                    }
                                                    onDragOver={(e) => {
                                                        if (
                                                            getDraggedIds() ===
                                                            null
                                                        ) {
                                                            return;
                                                        }

                                                        e.preventDefault();
                                                        e.dataTransfer.dropEffect =
                                                            'move';
                                                        setHoverIndex(index);
                                                    }}
                                                    onDragLeave={() =>
                                                        setHoverIndex((i) =>
                                                            i === index
                                                                ? null
                                                                : i,
                                                        )
                                                    }
                                                    onDrop={(e) => {
                                                        const ids =
                                                            getDraggedIds();

                                                        if (
                                                            ids === null ||
                                                            item.folderId ===
                                                                undefined
                                                        ) {
                                                            return;
                                                        }

                                                        e.preventDefault();
                                                        setHoverIndex(null);
                                                        moveFiles(
                                                            ids,
                                                            item.folderId,
                                                        );
                                                    }}
                                                    className={
                                                        hoverIndex === index
                                                            ? 'bg-accent'
                                                            : undefined
                                                    }
                                                >
                                                    {item.title}
                                                </Link>
                                            </BreadcrumbLink>
                                        )}
                                    </BreadcrumbItem>
                                    {!isLast && <BreadcrumbSeparator />}
                                </Fragment>
                            );
                        })}
                    </BreadcrumbList>
                </Breadcrumb>
            )}
        </>
    );
}

import { router } from '@inertiajs/react';
import FileController from '@/actions/App/Http/Controllers/FileController';

export function moveFiles(ids: number[], folderId: number | null): void {
    const params = new URLSearchParams(window.location.search);
    const current = params.get('folder');

    router.post(
        FileController.move.url({
            query: { folder: current ? Number(current) : undefined },
        }),
        { ids, folder_id: folderId ?? '' },
        { preserveScroll: true },
    );
}

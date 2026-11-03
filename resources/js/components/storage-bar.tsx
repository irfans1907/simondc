type StorageInfo = {
    total: number;
    used: number;
    free: number;
};

function formatBytes(bytes: number): string {
    if (bytes >= 1024 * 1024 * 1024) {
        return `${(bytes / (1024 * 1024 * 1024)).toFixed(1)} GB`;
    }

    if (bytes >= 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    if (bytes >= 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${bytes} B`;
}

export function StorageBar({ storage }: { storage: StorageInfo }) {
    const usedPercent =
        storage.total > 0
            ? Math.min(100, (storage.used / storage.total) * 100)
            : 0;

    return (
        <div className="flex items-center gap-3">
            <div className="h-2 w-40 overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700">
                <div
                    className="h-full rounded-full bg-blue-500 transition-all"
                    style={{ width: `${usedPercent}%` }}
                />
            </div>
            <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                <span className="size-3 rounded-sm bg-blue-500" />
                {formatBytes(storage.used)} used
                <span className="size-3 rounded-sm bg-neutral-300 dark:bg-neutral-600" />
                {formatBytes(storage.free)} free
            </span>
        </div>
    );
}

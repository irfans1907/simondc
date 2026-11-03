import { Form, Head, router } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Download,
    Eye,
    File as FileIcon,
    FileCode,
    FileImage,
    FileText,
    Folder,
    FolderInput,
    FolderPlus,
    Info,
    LayoutGrid,
    List,
    MoreHorizontal,
    Pencil,
    Share2,
    Trash2,
    Upload,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import FileController from '@/actions/App/Http/Controllers/FileController';
import FileShareController from '@/actions/App/Http/Controllers/FileShareController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { StorageBar } from '@/components/storage-bar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { getDraggedIds, setDraggedIds } from '@/lib/file-drag';
import { moveFiles } from '@/lib/file-move';
import {
    Attachment,
    AttachmentAction,
    AttachmentActions,
    AttachmentContent,
    AttachmentDescription,
    AttachmentMedia,
    AttachmentTitle,
} from '@/components/ui/attachment';
import { cn } from '@/lib/utils';
import { bulkDownload, download, index, preview } from '@/routes/files';
import type { File as FileModel, FolderBreadcrumb } from '@/types';

type FolderNode = { id: number; name: string; parent_id: number | null };

function formatSize(bytes: number | null): string {
    if (bytes === null || bytes === undefined) {
        return '';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function fileExtension(name: string): string {
    const dot = name.lastIndexOf('.');

    if (dot <= 0 || dot === name.length - 1) {
        return 'FILE';
    }

    return name.slice(dot + 1).toUpperCase();
}

function isImageFile(name: string): boolean {
    const ext = name.split('.').pop()?.toLowerCase();
    return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'].includes(ext ?? '');
}

function getFileIcon(name: string) {
    const ext = name.split('.').pop()?.toLowerCase();

    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'].includes(ext ?? '')) {
        return FileImage;
    }

    if (['pdf'].includes(ext ?? '')) {
        return FileText;
    }

    if (['js', 'ts', 'tsx', 'jsx', 'php', 'py', 'rb', 'go', 'rs', 'java', 'c', 'cpp', 'h'].includes(ext ?? '')) {
        return FileCode;
    }

    return FileIcon;
}

function formatDateTime(value: string): string {
    return new Date(value).toLocaleString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

function CreateFolderDialog({ parentId }: { parentId: number | null }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <FolderPlus className="size-4" />
                    New Folder
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle className="mt-3">Create folder</DialogTitle>
                <DialogDescription>
                    Enter a name for the new folder.
                </DialogDescription>

                <Form
                    {...FileController.storeFolder.form({
                        query: { parent_id: parentId ?? undefined },
                    })}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="folder-name">Folder name</Label>
                                <Input
                                    id="folder-name"
                                    name="name"
                                    className="mt-1 block w-full"
                                    placeholder="New folder"
                                    required
                                    autoComplete="off"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Create
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function UploadFileDialog({ parentId }: { parentId: number | null }) {
    const [open, setOpen] = useState(false);
    const [selectedFiles, setSelectedFiles] = useState<File[]>([]);
    const [dragging, setDragging] = useState(false);
    const [imagePreview, setImagePreview] = useState<string | undefined>(undefined);
    const inputRef = useRef<HTMLInputElement>(null);

    const syncInput = (files: File[]) => {
        const dt = new DataTransfer();
        files.forEach((file) => dt.items.add(file));

        if (inputRef.current) {
            inputRef.current.files = dt.files;
        }

        setSelectedFiles(files);
    };

    const addFiles = (incoming: File[]) => {
        const existingNames = new Set(
            selectedFiles.map((file) => file.name.toLowerCase()),
        );
        const duplicates: string[] = [];
        const next: File[] = [...selectedFiles];

        incoming.forEach((file) => {
            if (existingNames.has(file.name.toLowerCase())) {
                duplicates.push(file.name);

                return;
            }

            existingNames.add(file.name.toLowerCase());
            next.push(file);
        });

        syncInput(next);

        if (duplicates.length > 0) {
            toast.error(`${duplicates.length} duplicate file${duplicates.length > 1 ? 's' : ''} skipped`, {
                description: duplicates.join(', '),
            });
        }
    };

    const reset = () => {
        if (inputRef.current) {
            inputRef.current.value = '';
        }

        setSelectedFiles([]);
        setDragging(false);
    };

    return (
        <>
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    reset();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button>
                    <Upload className="size-4" />
                    Upload Files
                </Button>
            </DialogTrigger>
            <DialogContent className="top-0 left-0 h-[100dvh] w-[100vw] max-w-none translate-x-0 translate-y-0 grid-rows-[auto_auto_1fr] rounded-none p-6 sm:max-w-none">
                <DialogTitle className="mt-3">Upload files</DialogTitle>
                <DialogDescription>
                    Drop files here or click to browse.
                </DialogDescription>

                <Form
                    {...FileController.store.form({
                        query: { parent_id: parentId ?? undefined },
                    })}
                    onSuccess={() => {
                        setOpen(false);
                        reset();
                    }}
                    className="flex min-h-0 flex-1 flex-col gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                ref={inputRef}
                                name="files[]"
                                type="file"
                                multiple
                                className="hidden"
                                onChange={(e) => {
                                    addFiles(Array.from(e.target.files ?? []));
                                }}
                            />

                            <button
                                type="button"
                                onClick={() => inputRef.current?.click()}
                                onDragOver={(e) => {
                                    e.preventDefault();
                                    setDragging(true);
                                }}
                                onDragLeave={() => setDragging(false)}
                                onDrop={(e) => {
                                    e.preventDefault();
                                    setDragging(false);
                                    addFiles(Array.from(e.dataTransfer.files));
                                }}
                                className={cn(
                                    'flex flex-1 flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-16 text-center transition-colors',
                                    dragging
                                        ? 'border-primary bg-primary/5'
                                        : 'border-sidebar-border/70 hover:border-primary/50',
                                )}
                            >
                                <Upload className="size-8 text-muted-foreground" />
                                <p className="text-sm font-medium">
                                    {dragging
                                        ? 'Drop files to upload'
                                        : 'Drag & drop files here'}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    or click to browse
                                </p>
                            </button>

                            {selectedFiles.length > 0 && (
                                <div className="space-y-2 rounded-lg border border-sidebar-border/70 p-3">
                                    <p className="text-sm font-medium">
                                        {selectedFiles.length} file
                                        {selectedFiles.length > 1
                                            ? 's'
                                            : ''}{' '}
                                        selected
                                    </p>
                                    <div className="flex max-h-48 flex-wrap gap-3 overflow-y-auto">
                                        {selectedFiles.map((file, index) => {
                                            const Icon = getFileIcon(file.name);
                                            const isImage = isImageFile(file.name);
                                            const imgUrl = isImage ? URL.createObjectURL(file) : undefined;

                                            return (
                                                <Attachment key={`${file.name}-${index}`} size="sm" orientation="vertical" className="w-28">
                                                    <AttachmentMedia variant={isImage ? 'image' : 'icon'} className="!w-full !aspect-square !rounded-xl">
                                                        {isImage ? (
                                                            <img
                                                                src={imgUrl}
                                                                alt={file.name}
                                                                className="size-full cursor-pointer"
                                                                onClick={() => setImagePreview(imgUrl)}
                                                            />
                                                        ) : (
                                                            <Icon className="size-6" />
                                                        )}
                                                    </AttachmentMedia>
                                                    <AttachmentContent>
                                                        <AttachmentTitle className="text-center">{file.name}</AttachmentTitle>
                                                        <AttachmentDescription className="text-center">
                                                            {fileExtension(file.name)} · {formatSize(file.size)}
                                                        </AttachmentDescription>
                                                    </AttachmentContent>
                                                    <AttachmentActions className="absolute top-1 right-1">
                                                        <AttachmentAction
                                                            aria-label={`Remove ${file.name}`}
                                                            onClick={() => {
                                                                const next = selectedFiles.filter((_, i) => i !== index);
                                                                syncInput(next);
                                                            }}
                                                        >
                                                            <X className="size-3" />
                                                        </AttachmentAction>
                                                    </AttachmentActions>
                                                </Attachment>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            <InputError message={errors.files} />

                            <DialogFooter className="mt-auto gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    disabled={
                                        processing || selectedFiles.length === 0
                                    }
                                >
                                    Upload
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>

        {imagePreview && (
            <Dialog open={!!imagePreview} onOpenChange={() => setImagePreview(undefined)}>
                <DialogContent className="flex items-center justify-center border-0 bg-transparent p-0 shadow-none">
                    <DialogClose asChild className="absolute top-2 right-2 z-50">
                        <Button variant="secondary" size="icon" className="size-8 rounded-full">
                            <X className="size-4" />
                        </Button>
                    </DialogClose>
                    <img
                        src={imagePreview}
                        alt="Preview"
                        className="max-h-[85vh] max-w-[85vw] rounded-lg object-contain"
                    />
                </DialogContent>
            </Dialog>
        )}
        </>
    );
}

function UploadFolderDialog({ parentId }: { parentId: number | null }) {
    const [open, setOpen] = useState(false);
    const [selectedFiles, setSelectedFiles] = useState<File[]>([]);
    const [dragging, setDragging] = useState(false);
    const [imagePreview, setImagePreview] = useState<string | undefined>(undefined);
    const inputRef = useRef<HTMLInputElement>(null);

    const syncInput = (files: File[]) => {
        const dt = new DataTransfer();
        files.forEach((file) => dt.items.add(file));

        if (inputRef.current) {
            inputRef.current.files = dt.files;
        }

        setSelectedFiles(files);
    };

    const addFiles = (incoming: File[]) => {
        const existingPaths = new Set(
            selectedFiles.map((file) => (file as File & { webkitRelativePath: string }).webkitRelativePath || file.name.toLowerCase()),
        );
        const duplicates: string[] = [];
        const next: File[] = [...selectedFiles];

        incoming.forEach((file) => {
            const relativePath = (file as File & { webkitRelativePath: string }).webkitRelativePath || file.name;
            if (existingPaths.has(relativePath)) {
                duplicates.push(relativePath);

                return;
            }

            existingPaths.add(relativePath);
            next.push(file);
        });

        syncInput(next);

        if (duplicates.length > 0) {
            toast.error(`${duplicates.length} duplicate file${duplicates.length > 1 ? 's' : ''} skipped`, {
                description: duplicates.join(', '),
            });
        }
    };

    const reset = () => {
        if (inputRef.current) {
            inputRef.current.value = '';
        }

        setSelectedFiles([]);
        setDragging(false);
    };

    return (
        <>
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    reset();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button variant="secondary">
                    <FolderInput className="size-4" />
                    Upload Folder
                </Button>
            </DialogTrigger>
            <DialogContent className="top-0 left-0 h-[100dvh] w-[100vw] max-w-none translate-x-0 translate-y-0 grid-rows-[auto_auto_1fr] rounded-none p-6 sm:max-w-none">
                <DialogTitle className="mt-3">Upload folder</DialogTitle>
                <DialogDescription>
                    Select a folder to upload all its contents including subfolders.
                </DialogDescription>

                <Form
                    {...FileController.store.form({
                        query: { parent_id: parentId ?? undefined },
                    })}
                    onSuccess={() => {
                        setOpen(false);
                        reset();
                    }}
                    className="flex min-h-0 flex-1 flex-col gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                ref={inputRef}
                                name="files[]"
                                type="file"
                                multiple
                                /* @ts-expect-error webkitdirectory is a non-standard attribute */
                                webkitdirectory=""
                                className="hidden"
                                onChange={(e) => {
                                    addFiles(Array.from(e.target.files ?? []));
                                }}
                            />

                            {selectedFiles.map((file, index) => {
                                const relativePath = (file as File & { webkitRelativePath: string }).webkitRelativePath || file.name;

                                return (
                                    <input
                                        key={`path-${index}`}
                                        type="hidden"
                                        name="relative_paths[]"
                                        value={relativePath}
                                    />
                                );
                            })}

                            <button
                                type="button"
                                onClick={() => inputRef.current?.click()}
                                onDragOver={(e) => {
                                    e.preventDefault();
                                    setDragging(true);
                                }}
                                onDragLeave={() => setDragging(false)}
                                onDrop={(e) => {
                                    e.preventDefault();
                                    setDragging(false);
                                    addFiles(Array.from(e.dataTransfer.files));
                                }}
                                className={cn(
                                    'flex flex-1 flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-16 text-center transition-colors',
                                    dragging
                                        ? 'border-primary bg-primary/5'
                                        : 'border-sidebar-border/70 hover:border-primary/50',
                                )}
                            >
                                <FolderInput className="size-8 text-muted-foreground" />
                                <p className="text-sm font-medium">
                                    {dragging
                                        ? 'Drop folder here'
                                        : 'Click to select a folder'}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    All files and subfolders will be uploaded
                                </p>
                            </button>

                            {selectedFiles.length > 0 && (
                                <div className="space-y-2 rounded-lg border border-sidebar-border/70 p-3">
                                    <p className="text-sm font-medium">
                                        {selectedFiles.length} file{selectedFiles.length > 1 ? 's' : ''} selected
                                    </p>
                                    <div className="flex max-h-48 flex-wrap gap-3 overflow-y-auto">
                                        {selectedFiles.map((file, index) => {
                                            const Icon = getFileIcon(file.name);
                                            const isImage = isImageFile(file.name);
                                            const imgUrl = isImage ? URL.createObjectURL(file) : undefined;
                                            const relativePath = (file as File & { webkitRelativePath: string }).webkitRelativePath || file.name;
                                            const pathParts = relativePath.split('/');
                                            const displayPath = pathParts.length > 1 ? pathParts.slice(0, -1).join('/') + '/' : '';

                                            return (
                                                <Attachment key={`${file.name}-${index}`} size="sm" orientation="vertical" className="w-28">
                                                    <AttachmentMedia variant={isImage ? 'image' : 'icon'} className="!w-full !aspect-square !rounded-xl">
                                                        {isImage ? (
                                                            <img
                                                                src={imgUrl}
                                                                alt={file.name}
                                                                className="size-full cursor-pointer"
                                                                onClick={() => setImagePreview(imgUrl)}
                                                            />
                                                        ) : (
                                                            <Icon className="size-6" />
                                                        )}
                                                    </AttachmentMedia>
                                                    <AttachmentContent>
                                                        <AttachmentTitle className="text-center">{file.name}</AttachmentTitle>
                                                        <AttachmentDescription className="text-center">
                                                            {displayPath}{fileExtension(file.name)} · {formatSize(file.size)}
                                                        </AttachmentDescription>
                                                    </AttachmentContent>
                                                    <AttachmentActions className="absolute top-1 right-1">
                                                        <AttachmentAction
                                                            aria-label={`Remove ${file.name}`}
                                                            onClick={() => {
                                                                const next = selectedFiles.filter((_, i) => i !== index);
                                                                syncInput(next);
                                                            }}
                                                        >
                                                            <X className="size-3" />
                                                        </AttachmentAction>
                                                    </AttachmentActions>
                                                </Attachment>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            <InputError message={errors.files} />

                            <DialogFooter className="mt-auto gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    disabled={processing || selectedFiles.length === 0}
                                >
                                    Upload Folder
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>

        {imagePreview && (
            <Dialog open={!!imagePreview} onOpenChange={() => setImagePreview(undefined)}>
                <DialogContent className="flex items-center justify-center border-0 bg-transparent p-0 shadow-none">
                    <DialogClose asChild className="absolute top-2 right-2 z-50">
                        <Button variant="secondary" size="icon" className="size-8 rounded-full">
                            <X className="size-4" />
                        </Button>
                    </DialogClose>
                    <img
                        src={imagePreview}
                        alt="Preview"
                        className="max-h-[85vh] max-w-[85vw] rounded-lg object-contain"
                    />
                </DialogContent>
            </Dialog>
        )}
        </>
    );
}

function RenameDialog({
    file,
    trigger,
    open,
    onOpenChange,
}: {
    file: FileModel;
    trigger?: React.ReactNode;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}) {
    const [internalOpen, setInternalOpen] = useState(false);
    const isControlled = open !== undefined;
    const isOpen = isControlled ? open : internalOpen;
    const setIsOpen = (next: boolean) => {
        if (isControlled) {
            onOpenChange?.(next);
        } else {
            setInternalOpen(next);
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            {trigger ? (
                <DialogTrigger asChild>{trigger}</DialogTrigger>
            ) : (
                !isControlled && (
                    <DialogTrigger asChild>
                        <Button variant="ghost" size="sm">
                            <Pencil className="size-4" />
                        </Button>
                    </DialogTrigger>
                )
            )}
            <DialogContent>
                <DialogTitle className="mt-3">Rename</DialogTitle>
                <DialogDescription>
                    Enter a new name for {file.name}.
                </DialogDescription>

                <Form
                    {...FileController.update.form(file.id)}
                    onSuccess={() => setIsOpen(false)}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`rename-${file.id}`}>
                                    Name
                                </Label>
                                <Input
                                    id={`rename-${file.id}`}
                                    name="name"
                                    className="mt-1 block w-full"
                                    defaultValue={file.name}
                                    required
                                    autoComplete="off"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Save
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteDialog({
    file,
    trigger,
    open,
    onOpenChange,
}: {
    file: FileModel;
    trigger?: React.ReactNode;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}) {
    const [internalOpen, setInternalOpen] = useState(false);
    const isControlled = open !== undefined;
    const isOpen = isControlled ? open : internalOpen;
    const setIsOpen = (next: boolean) => {
        if (isControlled) {
            onOpenChange?.(next);
        } else {
            setInternalOpen(next);
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            {trigger ? (
                <DialogTrigger asChild>{trigger}</DialogTrigger>
            ) : (
                !isControlled && (
                    <DialogTrigger asChild>
                        <Button variant="ghost" size="sm">
                            <Trash2 className="size-4 text-destructive" />
                        </Button>
                    </DialogTrigger>
                )
            )}
            <DialogContent>
                <DialogTitle className="mt-3">
                    Delete {file.type === 'folder' ? 'folder' : 'file'}
                </DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete {file.name}? This action
                    cannot be undone.
                </DialogDescription>

                <Form
                    {...FileController.destroy.form(file.id)}
                    onSuccess={() => setIsOpen(false)}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                Delete
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function buildFolderTree(
    folders: FolderNode[],
    parentId: number | null = null,
): FolderNode[] {
    return folders
        .filter((folder) => folder.parent_id === parentId)
        .sort((a, b) =>
            a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }),
        )
        .flatMap((folder) => [folder, ...buildFolderTree(folders, folder.id)]);
}

function collectFolderIds(folders: FolderNode[], ids: number[]): number[] {
    const excluded = new Set(ids);
    let changed = true;

    while (changed) {
        changed = false;

        for (const folder of folders) {
            if (
                folder.parent_id !== null &&
                excluded.has(folder.parent_id) &&
                !excluded.has(folder.id)
            ) {
                excluded.add(folder.id);
                changed = true;
            }
        }
    }

    return [...excluded];
}

function MoveDialog({
    file,
    folders,
    parentId,
    onSuccess,
    trigger,
    open,
    onOpenChange,
    sharedFoldersWithWriteAccess,
}: {
    file: FileModel;
    folders: FolderNode[];
    parentId: number | null;
    onSuccess: () => void;
    trigger?: React.ReactNode;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    sharedFoldersWithWriteAccess?: FolderNode[];
}) {
    const [internalOpen, setInternalOpen] = useState(false);
    const [folderId, setFolderId] = useState('root');
    const isControlled = open !== undefined;
    const isOpen = isControlled ? open : internalOpen;
    const setIsOpen = (next: boolean) => {
        if (isControlled) {
            onOpenChange?.(next);
        } else {
            setInternalOpen(next);
        }
    };

    const excluded =
        file.type === 'folder' ? collectFolderIds(folders, [file.id]) : [];
    const options = buildFolderTree(folders).filter(
        (folder) => !excluded.includes(folder.id),
    );
    const sharedOptions = sharedFoldersWithWriteAccess
        ? sharedFoldersWithWriteAccess.filter((f) => f.id !== file.id)
        : [];
    const hasSharedOptions = sharedOptions.length > 0;
    const isSharedDestination = folderId.startsWith('shared-');
    const sharedFolderId = isSharedDestination ? folderId.replace('shared-', '') : null;

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            {trigger ? (
                <DialogTrigger asChild>{trigger}</DialogTrigger>
            ) : (
                !isControlled && (
                    <DialogTrigger asChild>
                        <Button
                            variant="ghost"
                            size="sm"
                            data-tooltip="Move to folder"
                        >
                            <FolderInput className="size-4" />
                        </Button>
                    </DialogTrigger>
                )
            )}
            <DialogContent>
                <DialogTitle className="mt-3">
                    Move {file.type === 'folder' ? 'folder' : 'file'}
                </DialogTitle>
                <DialogDescription>
                    Choose a destination folder for {file.name}. Select a shared folder to move it outside My Files.
                </DialogDescription>

                {isSharedDestination ? (
                    <Form
                        {...FileController.moveToShared.form({
                            query: { folder: parentId ?? undefined },
                        })}
                        onSuccess={() => {
                            setIsOpen(false);
                            onSuccess();
                        }}
                        className="space-y-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <input type="hidden" name="ids[]" value={file.id} />
                                <input
                                    type="hidden"
                                    name="folder_id"
                                    value={sharedFolderId ?? ''}
                                />

                                <div className="grid gap-2">
                                    <Label htmlFor={`move-folder-${file.id}`}>
                                        Destination
                                    </Label>
                                    <Select
                                        value={folderId}
                                        onValueChange={setFolderId}
                                    >
                                        <SelectTrigger
                                            id={`move-folder-${file.id}`}
                                            className="mt-1 w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="root">
                                                My Files (root)
                                            </SelectItem>
                                            {options.map((folder) => (
                                                <SelectItem
                                                    key={folder.id}
                                                    value={String(folder.id)}
                                                >
                                                    {folder.name}
                                                </SelectItem>
                                            ))}
                                            {hasSharedOptions && (
                                                <>
                                                    <SelectItem value="disabled" disabled className="text-xs text-muted-foreground font-semibold">
                                                        Shared Folders
                                                    </SelectItem>
                                                    {sharedOptions.map((folder) => (
                                                        <SelectItem
                                                            key={`shared-${folder.id}`}
                                                            value={`shared-${folder.id}`}
                                                        >
                                                            {folder.name}
                                                        </SelectItem>
                                                    ))}
                                                </>
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.folder_id} />
                                </div>

                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button variant="secondary">Cancel</Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        Move to Shared Folder
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                ) : (
                    <Form
                        {...FileController.move.form({
                            query: { folder: parentId ?? undefined },
                        })}
                        onSuccess={() => {
                            setIsOpen(false);
                            onSuccess();
                        }}
                        className="space-y-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <input type="hidden" name="ids[]" value={file.id} />
                                <input
                                    type="hidden"
                                    name="folder_id"
                                    value={folderId === 'root' ? '' : folderId}
                                />

                                <div className="grid gap-2">
                                    <Label htmlFor={`move-folder-${file.id}`}>
                                        Destination
                                    </Label>
                                    <Select
                                        value={folderId}
                                        onValueChange={setFolderId}
                                    >
                                        <SelectTrigger
                                            id={`move-folder-${file.id}`}
                                            className="mt-1 w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="root">
                                                My Files (root)
                                            </SelectItem>
                                            {options.map((folder) => (
                                                <SelectItem
                                                    key={folder.id}
                                                    value={String(folder.id)}
                                                >
                                                    {folder.name}
                                                </SelectItem>
                                            ))}
                                            {hasSharedOptions && (
                                                <>
                                                    <SelectItem value="disabled" disabled className="text-xs text-muted-foreground font-semibold">
                                                        Shared Folders
                                                    </SelectItem>
                                                    {sharedOptions.map((folder) => (
                                                        <SelectItem
                                                            key={`shared-${folder.id}`}
                                                            value={`shared-${folder.id}`}
                                                        >
                                                            {folder.name}
                                                        </SelectItem>
                                                    ))}
                                                </>
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.folder_id} />
                                </div>

                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button variant="secondary">Cancel</Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        {isSharedDestination ? 'Move to Shared Folder' : 'Move'}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}

function BulkMoveDialog({
    selected,
    folders,
    parentId,
    onSuccess,
    onOpenChange,
}: {
    selected: number[];
    folders: FolderNode[];
    parentId: number | null;
    onSuccess: () => void;
    onOpenChange?: (open: boolean) => void;
}) {
    const [open, setOpen] = useState(false);
    const [folderId, setFolderId] = useState('root');

    const options = buildFolderTree(folders).filter(
        (folder) => !collectFolderIds(folders, selected).includes(folder.id),
    );

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                onOpenChange?.(next);
            }}
        >
            <DialogTrigger asChild>
                <Button variant="secondary" size="sm">
                    <FolderInput className="size-4" />
                    Move Selected
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle className="mt-3">Move selected items</DialogTitle>
                <DialogDescription>
                    Choose a destination folder for {selected.length} selected
                    item(s).
                </DialogDescription>

                <Form
                    {...FileController.move.form({
                        query: { folder: parentId ?? undefined },
                    })}
                    onSuccess={() => {
                        setOpen(false);
                        onSuccess();
                    }}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            {selected.map((id) => (
                                <input
                                    key={id}
                                    type="hidden"
                                    name="ids[]"
                                    value={id}
                                />
                            ))}
                            <input
                                type="hidden"
                                name="folder_id"
                                value={folderId === 'root' ? '' : folderId}
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="bulk-move-folder">
                                    Destination
                                </Label>
                                <Select
                                    value={folderId}
                                    onValueChange={setFolderId}
                                >
                                    <SelectTrigger
                                        id="bulk-move-folder"
                                        className="mt-1 w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="root">
                                            My Files (root)
                                        </SelectItem>
                                        {options.map((folder) => (
                                            <SelectItem
                                                key={folder.id}
                                                value={String(folder.id)}
                                            >
                                                {folder.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.folder_id} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Move
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function FileBadge({
    file,
    className,
}: {
    file: FileModel;
    className?: string;
}) {
    if (file.type === 'folder') {
        return <Folder className={cn('size-5 text-amber-500', className)} />;
    }

    if (file.mime_type?.startsWith('image/')) {
        return <FileIcon className={cn('size-5 text-green-500', className)} />;
    }

    return <FileIcon className={cn('size-5 text-blue-500', className)} />;
}
function PdfThumbnail({ url, name }: { url: string; name: string }) {
    const canvasRef = useRef<HTMLCanvasElement>(null);

    useEffect(() => {
        let cancelled = false;

        const render = async () => {
            const pdfjs = await import('pdfjs-dist');
            pdfjs.GlobalWorkerOptions.workerSrc = new URL(
                'pdfjs-dist/build/pdf.worker.min.mjs',
                import.meta.url,
            ).toString();

            try {
                const pdf = await pdfjs.getDocument({
                    url,
                    disableAutoFetch: true,
                    disableStream: true,
                    verbosity: 0,
                }).promise;
                const page = await pdf.getPage(1);
                const base = page.getViewport({ scale: 1 });

                const canvas = canvasRef.current;

                if (!canvas || cancelled) {
                    return;
                }

                const rect = canvas.parentElement?.getBoundingClientRect();
                const displayWidth = rect?.width || 180;
                const dpr = window.devicePixelRatio || 1;
                const targetWidth = displayWidth * dpr;
                const scale = targetWidth / base.width;
                const viewport = page.getViewport({ scale });

                canvas.width = viewport.width;
                canvas.height = viewport.height;
                canvas.style.width = `${displayWidth}px`;
                canvas.style.height = `${displayWidth * (viewport.height / viewport.width)}px`;

                await page.render({
                    canvas,
                    viewport,
                }).promise;
            } catch {
                // Fallback handled by the parent (icon).
            }
        };

        void render();

        return () => {
            cancelled = true;
        };
    }, [url]);

    return (
        <canvas ref={canvasRef} className="mx-auto block" aria-label={name} />
    );
}
function FileActions({
    file,
    folders,
    users,
    parentId,
    canManage,
    sharedFoldersWithWriteAccess,
    variant = 'buttons',
    onTriggerClick,
    onMenuOpenChange,
}: {
    file: FileModel;
    folders: FolderNode[];
    users: { id: number; name: string }[];
    parentId: number | null;
    canManage: boolean;
    sharedFoldersWithWriteAccess?: FolderNode[];
    variant?: 'buttons' | 'dropdown';
    onTriggerClick?: () => void;
    onMenuOpenChange?: (open: boolean) => void;
}) {
    const [detailsOpen, setDetailsOpen] = useState(false);
    const [shareOpen, setShareOpen] = useState(false);
    const [moveOpen, setMoveOpen] = useState(false);
    const [renameOpen, setRenameOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);

    if (variant === 'dropdown') {
        return (
            <div onDoubleClick={(e) => e.stopPropagation()} className="ml-auto">
                <DropdownMenu
                    modal={false}
                    onOpenChange={(open) => {
                        onMenuOpenChange?.(open);

                        if (open) {
                            onTriggerClick?.();
                        }
                    }}
                >
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8 focus-visible:ring-0 focus-visible:outline-none"
                            aria-label={`Actions for ${file.name}`}
                            onPointerDown={() => {
                                onTriggerClick?.();
                            }}
                        >
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        {file.type === 'file' || file.type === 'folder' ? (
                            <DropdownMenuItem asChild>
                                <a href={download.url(file.id)}>
                                    <Download className="size-4" />
                                    Download
                                </a>
                            </DropdownMenuItem>
                        ) : null}
                        <DropdownMenuItem onSelect={() => setDetailsOpen(true)}>
                            <Info className="size-4" />
                            Details
                        </DropdownMenuItem>
                        {canManage && (
                            <>
                                <DropdownMenuItem onSelect={() => setShareOpen(true)}>
                                    <Share2 className="size-4" />
                                    Share
                                </DropdownMenuItem>
                                <DropdownMenuItem onSelect={() => setMoveOpen(true)}>
                                    <FolderInput className="size-4" />
                                    Move
                                </DropdownMenuItem>
                                <DropdownMenuItem onSelect={() => setRenameOpen(true)}>
                                    <Pencil className="size-4" />
                                    Rename
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() => setDeleteOpen(true)}
                                >
                                    <Trash2 className="size-4" />
                                    Delete
                                </DropdownMenuItem>
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
                <FileDetailsDialog
                    file={file}
                    open={detailsOpen}
                    onOpenChange={setDetailsOpen}
                />
                <ShareDialog
                    file={file}
                    users={users}
                    sharedWith={(file.shared_with as { id: number; name: string; share_id: number }[]) ?? []}
                    open={shareOpen}
                    onOpenChange={setShareOpen}
                />
                <MoveDialog
                    file={file}
                    folders={folders}
                    parentId={parentId}
                    onSuccess={() => {}}
                    open={moveOpen}
                    onOpenChange={setMoveOpen}
                    sharedFoldersWithWriteAccess={sharedFoldersWithWriteAccess}
                />
                <RenameDialog
                    file={file}
                    open={renameOpen}
                    onOpenChange={setRenameOpen}
                />
                <DeleteDialog
                    file={file}
                    open={deleteOpen}
                    onOpenChange={setDeleteOpen}
                />
            </div>
        );
    }

    return (
        <div
            className="flex items-center justify-center gap-1"
            onClick={(e) => e.stopPropagation()}
            onDoubleClick={(e) => e.stopPropagation()}
        >
            {file.type === 'file' || file.type === 'folder' ? (
                <Button variant="ghost" size="sm" asChild data-tooltip="Download">
                    <a href={download.url(file.id)}>
                        <Download className="size-4" />
                    </a>
                </Button>
            ) : null}
            <div>
                <FileDetailsDialog
                    file={file}
                    trigger={
                            <Button variant="ghost" size="sm" data-tooltip="Details" className="focus-visible:ring-0">
                                <Info className="size-4" />
                            </Button>
                    }
                />
            </div>
            {canManage && (
                <div>
                    <ShareDialog
                        file={file}
                        users={users}
                        sharedWith={(file.shared_with as { id: number; name: string; share_id: number }[]) ?? []}
                        trigger={
                            <Button variant="ghost" size="sm" data-tooltip="Share" className="focus-visible:ring-0">
                                <Share2 className="size-4" />
                            </Button>
                        }
                    />
                </div>
            )}
            <div>
                <MoveDialog
                    file={file}
                    folders={folders}
                    parentId={parentId}
                    onSuccess={() => {}}
                    sharedFoldersWithWriteAccess={sharedFoldersWithWriteAccess}
                    trigger={
                            <Button variant="ghost" size="sm" data-tooltip="Move" className="focus-visible:ring-0">
                                <FolderInput className="size-4" />
                            </Button>
                    }
                />
            </div>
            <div>
                <RenameDialog
                    file={file}
                    trigger={
                            <Button variant="ghost" size="sm" data-tooltip="Rename" className="focus-visible:ring-0">
                                <Pencil className="size-4" />
                            </Button>
                    }
                />
            </div>
            <div>
                <DeleteDialog
                    file={file}
                    trigger={
                            <Button variant="ghost" size="sm" data-tooltip="Delete" className="focus-visible:ring-0">
                                <Trash2 className="size-4 text-destructive" />
                            </Button>
                    }
                />
            </div>
        </div>
    );
}

function isPreviewable(file: FileModel): boolean {
    if (file.type !== 'file' || file.mime_type === null) {
        return false;
    }

    return (
        file.mime_type.startsWith('image/') ||
        file.mime_type === 'application/pdf' ||
        file.mime_type.startsWith('audio/') ||
        file.mime_type.startsWith('video/')
    );
}

function GridPreview({ file }: { file: FileModel }) {
    const ref = useRef<HTMLDivElement>(null);
    const needsFetch =
        file.type === 'file' &&
        (file.mime_type?.startsWith('image/') ||
            file.mime_type === 'application/pdf' ||
            file.mime_type?.startsWith('video/'));
    const [inView, setInView] = useState(!needsFetch);

    useEffect(() => {
        if (!needsFetch) {
            return;
        }

        const node = ref.current;

        if (!node) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    setInView(true);
                    observer.disconnect();
                }
            },
            { rootMargin: '400px' },
        );

        observer.observe(node);

        return () => observer.disconnect();
    }, [needsFetch]);

    let content;

    if (needsFetch && !inView) {
        content = (
            <div className="flex size-45 items-center justify-center rounded-xl border border-sidebar-border/70 bg-muted/30">
                <FileBadge file={file} className="size-9 opacity-60" />
            </div>
        );
    } else if (file.type === 'file' && file.mime_type?.startsWith('image/')) {
        content = (
            <div className="relative size-45 overflow-hidden rounded-xl border border-sidebar-border/70">
                <img
                    src={preview.url(file.id)}
                    alt={file.name}
                    loading="lazy"
                    className="size-full object-cover"
                />
                <div className="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
                    <Eye className="size-5 text-white" />
                </div>
            </div>
        );
    } else if (isPreviewable(file) && file.mime_type === 'application/pdf') {
        content = (
            <div className="relative size-45 overflow-hidden rounded-xl border border-sidebar-border/70 bg-muted/30">
                <PdfThumbnail url={preview.url(file.id)} name={file.name} />
                <div className="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
                    <Eye className="size-5 text-white" />
                </div>
            </div>
        );
    } else if (isPreviewable(file) && file.mime_type?.startsWith('video/')) {
        content = (
            <div className="relative size-45 overflow-hidden rounded-xl border border-sidebar-border/70 bg-black">
                <video
                    src={preview.url(file.id)}
                    preload="metadata"
                    muted
                    className="size-full object-cover"
                />
                <div className="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
                    <Eye className="size-5 text-white" />
                </div>
            </div>
        );
    } else if (isPreviewable(file) && file.mime_type?.startsWith('audio/')) {
        content = (
            <div className="relative flex size-45 items-center justify-center rounded-xl border border-sidebar-border/70 bg-blue-500/10">
                <FileBadge file={file} className="size-9" />
                <div className="absolute inset-0 flex items-center justify-center rounded-xl bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
                    <Eye className="size-5 text-white" />
                </div>
            </div>
        );
    } else {
        content = (
            <div
                className={cn(
                    'flex size-45 items-center justify-center rounded-xl',
                    file.type === 'folder',
                )}
            >
                <FileBadge file={file} className="size-30" />
            </div>
        );
    }

    return (
        <div ref={ref} className="mb-3 flex items-center justify-center">
            {content}
        </div>
    );
}

function BulkDeleteDialog({
    selected,
    parentId,
    onSuccess,
    onOpenChange,
}: {
    selected: number[];
    parentId: number | null;
    onSuccess: () => void;
    onOpenChange?: (open: boolean) => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                onOpenChange?.(next);
            }}
        >
            <DialogTrigger asChild>
                <Button variant="destructive" size="sm">
                    <Trash2 className="size-4" />
                    Delete Selected
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle className="mt-3">Delete selected items</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete {selected.length} selected
                    item(s)? This action cannot be undone.
                </DialogDescription>

                <Form
                    {...FileController.bulkDestroy.form({
                        query: { folder: parentId ?? undefined },
                    })}
                    onSuccess={() => {
                        setOpen(false);
                        onSuccess();
                    }}
                >
                    {({ processing }) => (
                        <>
                            {selected.map((id) => (
                                <input
                                    key={id}
                                    type="hidden"
                                    name="ids[]"
                                    value={id}
                                />
                            ))}

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    Delete
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function FilePreviewDialog({
    file,
    open,
    onOpenChange,
}: {
    file: FileModel | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const previewUrl = file ? preview.url(file.id) : '';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                onEscapeKeyDown={(event) => event.stopPropagation()}
                className="top-0 left-0 h-[100dvh] w-[100vw] max-w-none translate-x-0 translate-y-0 grid-rows-[auto_1fr] rounded-none p-6 sm:max-w-none"
            >
                <DialogTitle className="mt-3">
                    <div className="flex items-center gap-2">
                        <FileIcon className="size-4 shrink-0" />
                        <span>{file?.name}</span>
                    </div>
                </DialogTitle>

                {file && (
                    <div className="flex min-h-0 flex-1 flex-col gap-4">
                        <div className="flex min-h-0 flex-1 items-center justify-center overflow-hidden rounded-lg">
                            {file.mime_type?.startsWith('image/') ? (
                                <img
                                    src={previewUrl}
                                    alt={file.name}
                                    className="max-h-full max-w-full rounded-lg object-contain"
                                />
                            ) : file.mime_type === 'application/pdf' ? (
                                <iframe
                                    src={previewUrl}
                                    title={file.name}
                                    tabIndex={-1}
                                    onFocus={(event) =>
                                        event.currentTarget.blur()
                                    }
                                    className="h-full w-full rounded-lg border border-sidebar-border/70"
                                />
                            ) : file.mime_type?.startsWith('audio/') ? (
                                <audio
                                    controls
                                    src={previewUrl}
                                    className="w-full"
                                />
                            ) : file.mime_type?.startsWith('video/') ? (
                                <video
                                    controls
                                    src={previewUrl}
                                    className="max-h-full max-w-full rounded-lg"
                                />
                            ) : (
                                <div className="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-sidebar-border/70 px-6 py-12 text-center">
                                    <FileIcon className="size-10 text-muted-foreground" />
                                    <p className="text-sm text-muted-foreground">
                                        Preview is not available for this file
                                        type.
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

function FileDetailsDialog({
    file,
    trigger,
    open,
    onOpenChange,
}: {
    file: FileModel;
    trigger?: React.ReactNode;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}) {
    const rows: { label: string; value: string }[] = [
        {
            label: 'Type',
            value:
                file.type === 'folder'
                    ? 'Folder'
                    : `${fileExtension(file.name)} file`,
        },
        {
            label: 'Size',
            value: file.type === 'folder' ? '—' : formatSize(file.size),
        },
        {
            label: 'Created at',
            value: formatDateTime(file.created_at),
        },
        {
            label: 'Modified at',
            value: formatDateTime(file.updated_at),
        },
        {
            label: 'Owner',
            value: file.owner?.name ?? '—',
        },
    ];

    const isControlled = open !== undefined;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            {trigger ? (
                <DialogTrigger asChild>{trigger}</DialogTrigger>
            ) : (
                !isControlled && (
                    <DialogTrigger asChild>
                        <Button variant="ghost" size="sm">
                            <Info className="size-4" />
                        </Button>
                    </DialogTrigger>
                )
            )}
            <DialogContent className="sm:max-w-md">
                {' '}
                <DialogTitle className="mt-3 flex items-center gap-2">
                    {file.type === 'folder' ? (
                        <Folder className="size-4 shrink-0 text-amber-500" />
                    ) : (
                        <FileIcon className="size-4 shrink-0 text-blue-500" />
                    )}
                    <span className="whitespace-normal" style={{ overflowWrap: 'anywhere', wordBreak: 'break-word' }}>{file.name}</span>
                </DialogTitle>
                <DialogDescription>
                    Details for this {file.type}.
                </DialogDescription>
                <dl className="grid gap-3 text-sm">
                    {rows.map((row) => (
                        <div
                            key={row.label}
                            className="grid grid-cols-[80px_1fr] items-baseline gap-3"
                        >
                            <dt className="text-muted-foreground">
                                {row.label}
                            </dt>
                            <dd className="truncate text-right font-medium">
                                {row.value}
                            </dd>
                        </div>
                    ))}
                </dl>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="secondary">Close</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ShareDialog({
    file,
    users,
    sharedWith,
    trigger,
    open,
    onOpenChange,
}: {
    file: FileModel;
    users: { id: number; name: string }[];
    sharedWith: { id: number; name: string; share_id: number; permission?: string }[];
    trigger?: React.ReactNode;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}) {
    const [internalOpen, setInternalOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [selectedUsers, setSelectedUsers] = useState<number[]>([]);
    const [highlightedIndex, setHighlightedIndex] = useState(-1);
    const [permission, setPermission] = useState<'read' | 'write'>('read');
    const isControlled = open !== undefined;
    const isOpen = isControlled ? open : internalOpen;
    const setIsOpen = (next: boolean) => {
        if (isControlled) {
            onOpenChange?.(next);
        } else {
            setInternalOpen(next);
        }

        if (!next) {
            setSelectedUsers([]);
            setSearch('');
            setHighlightedIndex(-1);
            setPermission('read');
        }
    };

    const filteredUsers = users.filter(
        (user) =>
            !sharedWith.some((s) => s.id === user.id) &&
            user.name.toLowerCase().includes(search.toLowerCase()),
    );

    const visibleUsers = filteredUsers.slice(0, 5);

    const handleSearchKeyDown = (e: React.KeyboardEvent) => {
        if (!search || visibleUsers.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setHighlightedIndex((prev) =>
                prev < visibleUsers.length - 1 ? prev + 1 : 0,
            );
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setHighlightedIndex((prev) =>
                prev > 0 ? prev - 1 : visibleUsers.length - 1,
            );
        } else if (e.key === 'Enter' && highlightedIndex >= 0) {
            e.preventDefault();
            addUser(visibleUsers[highlightedIndex].id);
        } else if (e.key === 'Escape') {
            setSearch('');
            setHighlightedIndex(-1);
        }
    };

    const addUser = (userId: number) => {
        setSelectedUsers((prev) => [...prev, userId]);
        setSearch('');
    };

    const removeSelectedUser = (userId: number) => {
        setSelectedUsers((prev) => prev.filter((id) => id !== userId));
    };

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            {trigger ? (
                <DialogTrigger asChild>{trigger}</DialogTrigger>
            ) : (
                !isControlled && (
                    <DialogTrigger asChild>
                        <Button variant="ghost" size="sm">
                            <Share2 className="size-4" />
                        </Button>
                    </DialogTrigger>
                )
            )}
            <DialogContent className="sm:max-w-lg">
                <DialogTitle className="mt-3 text-lg font-semibold">
                    Share "{file.name}"
                </DialogTitle>

                <Form
                    {...FileShareController.store.form()}
                    onSuccess={() => setIsOpen(false)}
                >
                    {({ processing }) => (
                        <>
                            <input type="hidden" name="file_id" value={file.id} />
                            <input type="hidden" name="permission" value={permission} />

                            {selectedUsers.map((userId) => (
                                <input
                                    key={userId}
                                    type="hidden"
                                    name="user_ids[]"
                                    value={userId}
                                />
                            ))}

                            <div className="relative mt-2">
                                <Input
                                    placeholder="Add People..."
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                        setHighlightedIndex(-1);
                                    }}
                                    onKeyDown={handleSearchKeyDown}
                                    className="w-full"
                                    autoComplete="off"
                                />
                                {search && visibleUsers.length > 0 && (
                                    <div className="absolute top-full left-0 z-10 mt-1 w-full rounded-lg border border-sidebar-border/70 bg-background shadow-md">
                                        {visibleUsers.map((user, index) => (
                                            <button
                                                key={user.id}
                                                type="button"
                                                onClick={() => addUser(user.id)}
                                                onMouseEnter={() => setHighlightedIndex(index)}
                                                className={`flex w-full items-center gap-2 px-3 py-2 text-left text-sm ${
                                                    index === highlightedIndex
                                                        ? 'bg-muted/70'
                                                        : 'hover:bg-muted/50'
                                                }`}
                                            >
                                                <div className="flex size-7 items-center justify-center rounded-full bg-muted text-xs font-medium">
                                                    {user.name.charAt(0).toUpperCase()}
                                                </div>
                                                {user.name}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {selectedUsers.length > 0 && (
                                <div className="mt-2 flex flex-wrap gap-1">
                                    {selectedUsers.map((userId) => {
                                        const user = users.find((u) => u.id === userId);
                                        if (!user) return null;
                                        return (
                                            <span
                                                key={userId}
                                                className="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs"
                                            >
                                                {user.name}
                                                <button
                                                    type="button"
                                                    onClick={() => removeSelectedUser(userId)}
                                                    className="ml-0.5 rounded-full p-0.5 hover:bg-muted-foreground/20"
                                                >
                                                    <X className="size-3" />
                                                </button>
                                            </span>
                                        );
                                    })}
                                </div>
                            )}

                            <div className="mt-3 flex items-center gap-2">
                                <span className="text-xs text-muted-foreground">Choose Permission:</span>
                                <button
                                    type="button"
                                    onClick={() => setPermission('read')}
                                    className={`rounded-md px-2 py-1 text-xs font-medium transition-colors ${
                                        permission === 'read'
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground hover:bg-muted/80'
                                    }`}
                                >
                                    Read Only
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setPermission('write')}
                                    className={`rounded-md px-2 py-1 text-xs font-medium transition-colors ${
                                        permission === 'write'
                                            ? 'bg-green-600 text-white'
                                            : 'bg-muted text-muted-foreground hover:bg-muted/80'
                                    }`}
                                >
                                    Can Upload
                                </button>
                            </div>

                            <div className="mt-4">
                                <p className="mb-2 text-sm font-medium">People with Access</p>
                                <div className="space-y-1">
                                    <div className="flex items-center justify-between rounded-md px-2 py-1.5">
                                        <div className="flex items-center gap-2">
                                            <div className="flex size-7 items-center justify-center rounded-full bg-muted text-xs font-medium">
                                                {file.owner?.name?.charAt(0).toUpperCase() ?? '?'}
                                            </div>
                                            <div>
                                                <p className="text-sm">{file.owner?.name} (you)</p>
                                                <p className="text-xs text-muted-foreground">Owner</p>
                                            </div>
                                        </div>
                                    </div>
                                    {sharedWith.map((user) => (
                                        <div key={user.id} className="flex items-center justify-between rounded-md px-2 py-1.5 hover:bg-muted/50">
                                            <div className="flex items-center gap-2">
                                                <div className="flex size-7 items-center justify-center rounded-full bg-muted text-xs font-medium">
                                                    {user.name.charAt(0).toUpperCase()}
                                                </div>
                                                <div>
                                                    <p className="text-sm">{user.name}</p>
                                                    <p className="text-xs text-muted-foreground capitalize">{user.permission ?? 'read'}</p>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-1">
                                                {file.type === 'folder' && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-6 text-xs text-muted-foreground hover:text-foreground"
                                                        onClick={() => {
                                                            const newPermission = user.permission === 'write' ? 'read' : 'write';
                                                            router.patch(
                                                                FileShareController.updatePermission.url(user.share_id),
                                                                { permission: newPermission },
                                                                { preserveScroll: true },
                                                            );
                                                        }}
                                                    >
                                                        {user.permission === 'write' ? 'Revoke Upload' : 'Allow Upload'}
                                                    </Button>
                                                )}
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-6 text-xs text-muted-foreground hover:text-destructive"
                                                    onClick={() => {
                                                        router.delete(FileShareController.destroy.url(user.share_id), {
                                                            preserveScroll: true,
                                                        });
                                                    }}
                                                >
                                                    Remove
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <DialogFooter className="mt-4">
                                <Button
                                    type="submit"
                                    disabled={processing || selectedUsers.length === 0}
                                >
                                    {processing ? 'Sharing...' : 'Share'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function FilesIndex({
    files,
    folder,
    folders,
    users,
    storage,
    canManage,
    sharedFoldersWithWriteAccess,
}: {
    files: FileModel[];
    folder: { id: number; name: string } | null;
    folders: FolderNode[];
    users: { id: number; name: string }[];
    storage: { total: number; used: number; free: number };
    canManage: boolean;
    sharedFoldersWithWriteAccess: FolderNode[];
}) {
    const [selected, setSelected] = useState<number[]>([]);
    const [anchorIndex, setAnchorIndex] = useState<number | null>(null);
    const [previewFile, setPreviewFile] = useState<FileModel | null>(null);
    const [previewOpen, setPreviewOpen] = useState(false);
    const [dropTarget, setDropTarget] = useState<number | null>(null);
    const [sortBy, setSortBy] = useState<'name' | 'type' | 'size' | 'modified'>(
        'name',
    );
    const [sortDir, setSortDir] = useState<'asc' | 'desc'>('asc');
    const [view, setView] = useState<'list' | 'grid'>(() => {
        const saved = window.localStorage.getItem('files.view');

        return saved === 'grid' ? 'grid' : 'list';
    });
    const tableRef = useRef<HTMLDivElement>(null);
    const previewOpenRef = useRef(previewOpen);
    const filesRef = useRef(files);
    const menuOpenRef = useRef(false);
    const bulkBarRef = useRef<HTMLDivElement>(null);
    const bulkDialogOpenRef = useRef(false);

    const sortedFiles = useMemo(() => {
        const sorted = [...files];

        sorted.sort((a, b) => {
            if (a.type === 'folder' && b.type !== 'folder') {
                return -1;
            }

            if (a.type !== 'folder' && b.type === 'folder') {
                return 1;
            }

            let result = 0;

            switch (sortBy) {
                case 'name':
                    result = a.name.localeCompare(b.name, undefined, {
                        numeric: true,
                        sensitivity: 'base',
                    });
                    break;
                case 'type': {
                    const aType =
                        a.type === 'folder' ? 'Folder' : fileExtension(a.name);
                    const bType =
                        b.type === 'folder' ? 'Folder' : fileExtension(b.name);
                    result = aType.localeCompare(bType);
                    break;
                }
                case 'size':
                    result = (a.size ?? -1) - (b.size ?? -1);
                    break;
                case 'modified':
                    result =
                        new Date(a.updated_at).getTime() -
                        new Date(b.updated_at).getTime();
                    break;
            }

            return sortDir === 'asc' ? result : -result;
        });

        return sorted;
    }, [files, sortBy, sortDir]);

    useEffect(() => {
        filesRef.current = files;
    }, [files]);

    useEffect(() => {
        previewOpenRef.current = previewOpen;
    }, [previewOpen]);

    useEffect(() => {
        const handleDocumentClick = (e: MouseEvent) => {
            if (menuOpenRef.current) {
                return;
            }

            if (previewOpenRef.current) {
                return;
            }

            if (bulkDialogOpenRef.current) {
                return;
            }

            if (bulkBarRef.current?.contains(e.target as Node)) {
                return;
            }

            const target = e.target as Element | null;

            if (!target?.isConnected) {
                return;
            }

            if (
                target?.closest(
                    '[data-slot="dialog-content"], [data-slot="dropdown-menu-content"], [data-slot="dropdown-menu-trigger"], [data-slot="select-content"], [data-slot="select-trigger"]',
                )
            ) {
                return;
            }

            if (
                tableRef.current &&
                !tableRef.current.contains(e.target as Node)
            ) {
                setSelected([]);
                setAnchorIndex(null);
            }
        };

        const handleDocumentKeyDown = (e: KeyboardEvent) => {
            if (e.defaultPrevented) {
                return;
            }

            const target = e.target as HTMLElement | null;

            if (target?.closest('input, textarea, select')) {
                return;
            }

            if (previewOpenRef.current) {
                return;
            }

            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'a') {
                e.preventDefault();
                setSelected(filesRef.current.map((file) => file.id));

                return;
            }

            if (e.key === 'Escape') {
                setSelected([]);
                setAnchorIndex(null);
            }
        };

        document.addEventListener('click', handleDocumentClick);
        document.addEventListener('keydown', handleDocumentKeyDown);

        return () => {
            document.removeEventListener('click', handleDocumentClick);
            document.removeEventListener('keydown', handleDocumentKeyDown);
        };
    }, []);

    useEchoPublic('files', '.files.changed', (payload: unknown) => {
        void payload;
        setSelected([]);
        setAnchorIndex(null);
        router.reload({ only: ['files'] });
    });

    const parentId = folder?.id ?? null;

    const selectedSet = new Set(selected);

    const isSelected = (id: number) => selectedSet.has(id);

    const selectSingle = (index: number) => {
        const file = sortedFiles[index];
        setSelected([file.id]);
        setAnchorIndex(index);
    };

    const selectRange = (index: number) => {
        if (anchorIndex === null) {
            selectSingle(index);

            return;
        }

        const [start, end] = [
            Math.min(anchorIndex, index),
            Math.max(anchorIndex, index),
        ];

        setSelected(sortedFiles.slice(start, end + 1).map((file) => file.id));
    };
    const toggleSort = (key: 'name' | 'type' | 'size' | 'modified') => {
        if (sortBy === key) {
            setSortDir((current) => (current === 'asc' ? 'desc' : 'asc'));

            return;
        }

        setSortBy(key);
        setSortDir('asc');
    };

    const renderSortIcon = (key: 'name' | 'type' | 'size' | 'modified') => {
        if (sortBy !== key) {
            return <ArrowUpDown className="size-3 opacity-60" />;
        }

        return sortDir === 'asc' ? (
            <ArrowUp className="size-3" />
        ) : (
            <ArrowDown className="size-3" />
        );
    };

    const handleRowClick = (e: React.MouseEvent, index: number) => {
        if (e.ctrlKey || e.metaKey) {
            const file = sortedFiles[index];
            const next = selected.includes(file.id)
                ? selected.filter((id) => id !== file.id)
                : [...selected, file.id];
            setSelected(next);
            setAnchorIndex(index);

            return;
        }

        if (e.shiftKey) {
            selectRange(index);

            return;
        }

        selectSingle(index);
    };

    const handleRowDoubleClick = (file: FileModel) => {
        if (file.type === 'folder') {
            router.visit(index({ query: { folder: file.id } }));

            return;
        }

        setPreviewFile(file);
        setPreviewOpen(true);
    };

    const changeView = (next: 'list' | 'grid') => {
        setView(next);
        window.localStorage.setItem('files.view', next);
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.defaultPrevented) {
            return;
        }

        const target = e.target as HTMLElement;

        if (target.closest('input, textarea, select, button')) {
            return;
        }

        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'a') {
            e.preventDefault();
            setSelected(sortedFiles.map((file) => file.id));

            return;
        }

        if (e.key === 'Escape') {
            setSelected([]);
            setAnchorIndex(null);

            return;
        }

        if (e.key === 'Enter' && selected.length === 1) {
            const file = files.find((f) => f.id === selected[0]);

            if (file) {
                handleRowDoubleClick(file);
            }
        }
    };

    const handleDragStart = (e: React.DragEvent, file: FileModel) => {
        const ids = isSelected(file.id) ? selected : [file.id];
        setDraggedIds(ids);
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', String(file.id));
    };

    const handleDragEnd = () => {
        setDraggedIds(null);
        setDropTarget(null);
    };

    const handleFolderDragOver = (e: React.DragEvent, file: FileModel) => {
        const ids = getDraggedIds();

        if (ids === null || file.type !== 'folder' || ids.includes(file.id)) {
            return;
        }

        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        setDropTarget(file.id);
    };

    const handleFolderDrop = (e: React.DragEvent, file: FileModel) => {
        e.preventDefault();
        e.stopPropagation();

        const ids = getDraggedIds();
        setDraggedIds(null);
        setDropTarget(null);

        if (ids !== null && file.type === 'folder' && !ids.includes(file.id)) {
            moveFiles(ids, file.id);
        }
    };

    return (
        <>
            <Head title="My Files" />

            <h1 className="sr-only">My Files</h1>

            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="My Files"
                    description="Upload and manage your files"
                />
                <div className="mb-4 flex flex-wrap gap-2">
                    <StorageBar storage={storage} />
                </div>
                <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                    {selected.length > 0 ? (
                        <div ref={bulkBarRef} className="flex items-center gap-2">
                            <Button variant="secondary" size="sm" asChild>
                                <a
                                    href={bulkDownload.url({
                                        query: { ids: selected },
                                    })}
                                >
                                    <Download className="size-4" />
                                    Download Selected
                                </a>
                            </Button>
                            {canManage && (
                                <>
                                    <BulkMoveDialog
                                        selected={selected}
                                        folders={folders}
                                        parentId={parentId}
                                        onOpenChange={(open) => {
                                            bulkDialogOpenRef.current = open;
                                        }}
                                        onSuccess={() => {
                                            setSelected([]);
                                            setAnchorIndex(null);
                                        }}
                                    />
                                    <BulkDeleteDialog
                                        selected={selected}
                                        parentId={parentId}
                                        onOpenChange={(open) => {
                                            bulkDialogOpenRef.current = open;
                                        }}
                                        onSuccess={() => {
                                            setSelected([]);
                                    setAnchorIndex(null);
                                }}
                            />
                                </>
                            )}
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setSelected([]);
                                    setAnchorIndex(null);
                                }}
                            >
                                <X className="size-4" />
                                Clear
                            </Button>
                        </div>
                    ) : (
                        <span className="text-xs text-muted-foreground">
                            Click to select, Double-click to open, Ctrl+click to
                            multi-select, Shift+click to select range
                        </span>
                    )}

                    <div className="ml-auto flex items-center gap-2">
                        {selected.length > 0 && (
                            <Badge className="bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300">
                                {selected.length} selected
                            </Badge>
                        )}
                        <div className="flex items-center rounded-lg border border-sidebar-border/70 p-0.5">
                            <Button
                                variant={
                                    view === 'list' ? 'secondary' : 'ghost'
                                }
                                size="sm"
                                className="h-7 px-2"
                                data-tooltip="List view"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    changeView('list');
                                }}
                                aria-label="List view"
                            >
                                <List className="size-4" />
                            </Button>
                            <Button
                                variant={
                                    view === 'grid' ? 'secondary' : 'ghost'
                                }
                                size="sm"
                                className="h-7 px-2"
                                data-tooltip="Grid view"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    changeView('grid');
                                }}
                                aria-label="Grid view"
                            >
                                <LayoutGrid className="size-4" />
                            </Button>
                        </div>
                        {canManage && (
                            <>
                                <CreateFolderDialog parentId={parentId} />
                                <UploadFolderDialog parentId={parentId} />
                                <UploadFileDialog parentId={parentId} />
                            </>
                        )}
                    </div>
                </div>

                <div
                    ref={tableRef}
                    className="focus:outline-none"
                    tabIndex={0}
                    onKeyDown={handleKeyDown}
                >
                    {view === 'list' ? (
                        <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                        <th className="w-10 px-2 py-3 text-center">
                                            <Checkbox
                                                checked={
                                                    sortedFiles.length > 0 &&
                                                    selected.length ===
                                                        sortedFiles.length
                                                }
                                                onCheckedChange={(checked) => {
                                                    if (checked) {
                                                        setSelected(
                                                            sortedFiles.map(
                                                                (file) =>
                                                                    file.id,
                                                            ),
                                                        );
                                                    } else {
                                                        setSelected([]);
                                                    }
                                                }}
                                                aria-label="Select all files"
                                            />
                                        </th>
                                        <th
                                            className="cursor-pointer px-4 py-3 text-left font-medium hover:text-foreground"
                                            onClick={() => toggleSort('name')}
                                        >
                                            <span className="inline-flex items-center gap-1">
                                                Name
                                                {renderSortIcon('name')}
                                            </span>
                                        </th>
                                        <th
                                            className="cursor-pointer px-4 py-3 text-center font-medium hover:text-foreground"
                                            onClick={() => toggleSort('type')}
                                        >
                                            <span className="inline-flex items-center gap-1">
                                                Type
                                                {renderSortIcon('type')}
                                            </span>
                                        </th>
                                        <th
                                            className="cursor-pointer px-4 py-3 text-center font-medium hover:text-foreground"
                                            onClick={() => toggleSort('size')}
                                        >
                                            <span className="inline-flex items-center gap-1">
                                                Size
                                                {renderSortIcon('size')}
                                            </span>
                                        </th>
                                        <th
                                            className="cursor-pointer px-4 py-3 text-center font-medium hover:text-foreground"
                                            onClick={() =>
                                                toggleSort('modified')
                                            }
                                        >
                                            <span className="inline-flex items-center gap-1">
                                                Modified
                                                {renderSortIcon('modified')}
                                            </span>
                                        </th>
                                        <th className="px-4 py-3 text-center font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {sortedFiles.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="px-4 py-12 text-center text-muted-foreground"
                                            >
                                                No files found.
                                            </td>
                                        </tr>
                                    ) : (
                                        sortedFiles.map((file, index) => {
                                            const fileSelected = isSelected(
                                                file.id,
                                            );

                                            return (
                                                <tr
                                                    key={file.id}
                                                    draggable
                                                    onDragStart={(e) =>
                                                        handleDragStart(e, file)
                                                    }
                                                    onDragEnd={handleDragEnd}
                                                    onDragOver={(e) =>
                                                        handleFolderDragOver(
                                                            e,
                                                            file,
                                                        )
                                                    }
                                                    onDrop={(e) =>
                                                        handleFolderDrop(
                                                            e,
                                                            file,
                                                        )
                                                    }
                                                    onClick={(e) =>
                                                        handleRowClick(e, index)
                                                    }
                                                    onDoubleClick={() =>
                                                        handleRowDoubleClick(
                                                            file,
                                                        )
                                                    }
                                                    className={cn(
                                                        'cursor-pointer border-b border-sidebar-border/50 select-none last:border-b-0',
                                                        fileSelected
                                                            ? 'bg-blue-700 text-white'
                                                            : 'hover:bg-muted/50',
                                                        dropTarget ===
                                                            file.id &&
                                                            file.type ===
                                                                'folder' &&
                                                            'bg-accent outline-2 outline-primary outline-dashed',
                                                    )}
                                                >
                                                    <td className="px-2 py-3 text-center">
                                                        <Checkbox
                                                            checked={
                                                                fileSelected
                                                            }
                                                            onClick={(e) =>
                                                                e.stopPropagation()
                                                            }
                                                            onCheckedChange={() =>
                                                                handleRowClick(
                                                                    {
                                                                        ctrlKey: true,
                                                                    } as React.MouseEvent,
                                                                    index,
                                                                )
                                                            }
                                                            aria-label={`Select ${file.name}`}
                                                        />
                                                    </td>
                                                    <td className="px-4 py-3 font-medium">
                                                        <span className="inline-flex items-center gap-2">
                                                            <FileBadge
                                                                file={file}
                                                            />
                                                            {file.name}
                                                            {(file.shares_count as number) > 0 && (
                                                                <span title="Shared"><Users className="size-3.5 text-muted-foreground" /></span>
                                                            )}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3 text-center">
                                                        <Badge variant="outline">
                                                            {file.type ===
                                                            'folder'
                                                                ? 'Folder'
                                                                : fileExtension(
                                                                      file.name,
                                                                  )}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-4 py-3 text-center">
                                                        {file.type === 'folder'
                                                            ? '—'
                                                            : formatSize(
                                                                  file.size,
                                                              )}
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            'px-4 py-3 text-center',
                                                            fileSelected &&
                                                                'text-blue-100',
                                                        )}
                                                    >
                                                        {file.updated_label}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <FileActions
                                                            file={file}
                                                            folders={folders}
                                                            users={users}
                                                            parentId={parentId}
                                                            canManage={canManage}
                                                            sharedFoldersWithWriteAccess={sharedFoldersWithWriteAccess}
                                                        />
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div
                            className="rounded-xl border border-sidebar-border/70 p-3"
                            onClick={() => {
                                setSelected([]);
                                setAnchorIndex(null);
                            }}
                        >
                            {sortedFiles.length === 0 ? (
                                <div className="px-4 py-12 text-center text-muted-foreground">
                                    No files found.
                                </div>
                            ) : (
                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5">
                                    {sortedFiles.map((file, index) => {
                                        const fileSelected = isSelected(
                                            file.id,
                                        );

                                        return (
                                            <div
                                                key={file.id}
                                                draggable
                                                onDragStart={(e) =>
                                                    handleDragStart(e, file)
                                                }
                                                onDragEnd={handleDragEnd}
                                                onDragOver={(e) =>
                                                    handleFolderDragOver(
                                                        e,
                                                        file,
                                                    )
                                                }
                                                onDrop={(e) =>
                                                    handleFolderDrop(e, file)
                                                }
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    handleRowClick(e, index);
                                                }}
                                                onDoubleClick={() =>
                                                    handleRowDoubleClick(file)
                                                }
                                                className={cn(
                                                    'group relative cursor-pointer rounded-lg border p-1 transition-colors select-none',
                                                    fileSelected
                                                        ? 'border-blue-700 bg-blue-700'
                                                        : 'border-sidebar-border/70 hover:bg-muted/50',
                                                    dropTarget === file.id &&
                                                        file.type ===
                                                            'folder' &&
                                                        'outline-2 outline-primary outline-dashed',
                                                )}
                                            >
                                                <div className="flex h-10 items-start">
                                                    {(file.shares_count as number) > 0 && (
                                                        <span title="Shared" className="mt-1 ml-1"><Users className="size-4.5 text-muted-foreground" /></span>
                                                    )}
                                                    <FileActions
                                                        file={file}
                                                        folders={folders}
                                                        users={users}
                                                        parentId={parentId}
                                                        canManage={canManage}
                                                        sharedFoldersWithWriteAccess={sharedFoldersWithWriteAccess}
                                                        variant="dropdown"
                                                        onTriggerClick={() =>
                                                            selectSingle(index)
                                                        }
                                                        onMenuOpenChange={(open) => {
                                                            menuOpenRef.current = open;
                                                        }}
                                                    />
                                                </div>
                                                <GridPreview file={file} />
                                                <div
                                                    className={cn(
                                                        'mb-2 text-center text-sm font-medium truncate',
                                                        fileSelected &&
                                                            'text-white',
                                                    )}
                                                >
                                                    {file.name}
                                                </div>
                                                <div
                                                    className={cn(
                                                        'mb-2 text-center text-xs',
                                                        fileSelected
                                                            ? 'text-blue-100'
                                                            : 'text-muted-foreground',
                                                    )}
                                                >
                                                    {file.type === 'folder'
                                                        ? 'Folder'
                                                        : formatSize(file.size)}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    )}
                </div>

                <FilePreviewDialog
                    file={previewFile}
                    open={previewOpen}
                    onOpenChange={setPreviewOpen}
                />
            </div>
        </>
    );
}

FilesIndex.layout = (props: { breadcrumbs: FolderBreadcrumb[] }) => ({
    breadcrumbs: [
        {
            title: 'My Files',
            href: index(),
            folderId: null,
        },
        ...(props.breadcrumbs ?? []).map((crumb) => ({
            title: crumb.name,
            href: index({ query: { folder: crumb.id } }),
            folderId: crumb.id,
        })),
    ],
});

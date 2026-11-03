import { Form, Head, router } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Copy,
    Download,
    Eye,
    File as FileIcon,
    FileCode,
    FileImage,
    FileText,
    Folder,
    FolderPlus,
    Info,
    LayoutGrid,
    Lock,
    List,
    MoreHorizontal,
    Pencil,
    Trash2,
    Upload,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import SharedWithMeController from '@/actions/App/Http/Controllers/SharedWithMeController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import {
    Attachment,
    AttachmentAction,
    AttachmentActions,
    AttachmentContent,
    AttachmentDescription,
    AttachmentMedia,
    AttachmentTitle,
} from '@/components/ui/attachment';
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
import { cn } from '@/lib/utils';

import {
    bulkDownload as sharedBulkDownload,
    copyToMyFiles as sharedCopyToMyFiles,
    destroy as sharedDestroy,
    download as sharedDownload,
    index as sharedIndex,
    preview as sharedPreview,
    rename as sharedRename,
} from '@/routes/shared-with-me';
import type { File as FileModel, FolderBreadcrumb } from '@/types';

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
                    src={sharedPreview.url(file.id)}
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
                <PdfThumbnail url={sharedPreview.url(file.id)} name={file.name} />
                <div className="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
                    <Eye className="size-5 text-white" />
                </div>
            </div>
        );
    } else if (isPreviewable(file) && file.mime_type?.startsWith('video/')) {
        content = (
            <div className="relative size-45 overflow-hidden rounded-xl border border-sidebar-border/70 bg-black">
                <video
                    src={sharedPreview.url(file.id)}
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

function CreateFolderDialog({ parentId }: { parentId: number }) {
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
                    {...SharedWithMeController.storeFolder.form({
                        query: { parent_id: parentId },
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

function UploadFileDialog({ parentId }: { parentId: number }) {
    const [open, setOpen] = useState(false);
    const [selectedFiles, setSelectedFiles] = useState<File[]>([]);
    const [dragging, setDragging] = useState(false);
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
                    {...SharedWithMeController.store.form({
                        query: { parent_id: parentId },
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
                                        {selectedFiles.length > 1 ? 's' : ''}{' '}
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
                                                                className="size-full object-cover"
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
    );
}

function DeleteDialog({
    file,
    trigger,
}: {
    file: FileModel;
    trigger: React.ReactNode;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogTitle className="mt-3">Delete "{file.name}"?</DialogTitle>
                <DialogDescription>
                    This action cannot be undone. The {file.type} will be permanently deleted.
                </DialogDescription>
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        onClick={() => {
                            router.delete(sharedDestroy.url(file.id), {
                                preserveScroll: true,
                                onSuccess: () => setOpen(false),
                            });
                        }}
                    >
                        Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function RenameDialog({ file, onOpenChange, open }: { file: FileModel; onOpenChange: (open: boolean) => void; open: boolean }) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogTitle>Rename "{file.name}"</DialogTitle>
                <Form
                    {...sharedRename.form(file.id)}
                    onSuccess={() => {
                        toast.success('Renamed successfully.');
                        onOpenChange(false);
                    }}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2 py-2">
                                <Label htmlFor="rename-name">New name</Label>
                                <Input
                                    id="rename-name"
                                    name="name"
                                    defaultValue={file.name}
                                    autoFocus
                                    autoComplete="off"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Rename
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function CopyToMyFilesDialog({ file, onOpenChange, open }: { file: FileModel; onOpenChange: (open: boolean) => void; open: boolean }) {
    const [processing, setProcessing] = useState(false);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogTitle>Copy to My Files</DialogTitle>
                <DialogDescription>
                    A copy of "{file.name}" will be created in your root folder. The original will remain in this shared folder.
                </DialogDescription>
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button
                        disabled={processing}
                        onClick={() => {
                            setProcessing(true);
                            router.post(
                                sharedCopyToMyFiles(file.id).url,
                                {},
                                {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        toast.success('Copied to My Files.');
                                        onOpenChange(false);
                                    },
                                    onFinish: () => {
                                        setProcessing(false);
                                    },
                                },
                            );
                        }}
                    >
                        Copy
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function FileActions({
    file,
    canDelete,
    canWrite,
    onCopyToMyFiles,
    onRename,
    variant = 'buttons',
    onTriggerClick,
    onMenuOpenChange,
}: {
    file: FileModel & { can_delete?: boolean; can_write?: boolean };
    canDelete?: boolean;
    canWrite?: boolean;
    onCopyToMyFiles?: () => void;
    onRename?: () => void;
    variant?: 'buttons' | 'dropdown';
    onTriggerClick?: () => void;
    onMenuOpenChange?: (open: boolean) => void;
}) {
    const [detailsOpen, setDetailsOpen] = useState(false);
    const showDelete = canDelete || (file as FileModel & { can_delete?: boolean }).can_delete;
    const showWrite = canWrite || (file as FileModel & { can_write?: boolean }).can_write;

    if (variant === 'dropdown') {
        return (
            <div onDoubleClick={(e) => e.stopPropagation()}>
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
                        <DropdownMenuItem asChild>
                            <a href={sharedDownload.url(file.id)}>
                                <Download className="size-4" />
                                Download
                            </a>
                        </DropdownMenuItem>
                        <DropdownMenuItem onSelect={() => setDetailsOpen(true)}>
                            <Info className="size-4" />
                            Details
                        </DropdownMenuItem>
                        {showWrite && (
                            <>
                                <DropdownMenuItem onSelect={() => onRename?.()}>
                                    <Pencil className="size-4" />
                                    Rename
                                </DropdownMenuItem>
                                {file.type !== 'folder' && (
                                    <DropdownMenuItem onSelect={() => onCopyToMyFiles?.()}>
                                        <Copy className="size-4" />
                                        Copy to My Files
                                    </DropdownMenuItem>
                                )}
                            </>
                        )}
                        {showDelete && (
                            <DeleteDialog
                                file={file}
                                trigger={
                                    <DropdownMenuItem onSelect={(e) => e.preventDefault()} className="text-destructive focus:text-destructive">
                                        <Trash2 className="size-4" />
                                        Delete
                                    </DropdownMenuItem>
                                }
                            />
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
                <FileDetailsDialog
                    file={file}
                    open={detailsOpen}
                    onOpenChange={setDetailsOpen}
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
            <Button variant="ghost" size="sm" asChild data-tooltip="Download">
                <a href={sharedDownload.url(file.id)}>
                    <Download className="size-4" />
                </a>
            </Button>
            <div>
                <FileDetailsDialog file={file} />
            </div>
            {showWrite && (
                <>
                    <Button variant="ghost" size="sm" data-tooltip="Rename" onClick={() => onRename?.()}>
                        <Pencil className="size-4" />
                    </Button>
                    {file.type !== 'folder' && (
                        <Button variant="ghost" size="sm" data-tooltip="Copy to My Files" onClick={() => onCopyToMyFiles?.()}>
                            <Copy className="size-4" />
                        </Button>
                    )}
                </>
            )}
            {showDelete && (
                <DeleteDialog
                    file={file}
                    trigger={
                        <Button variant="ghost" size="sm" data-tooltip="Delete">
                            <Trash2 className="size-4 text-destructive" />
                        </Button>
                    }
                />
            )}
        </div>
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
                            <Button variant="ghost" size="sm" data-tooltip="Details">
                                <Info className="size-4" />
                            </Button>
                    </DialogTrigger>
                )
            )}
            <DialogContent className="sm:max-w-md">
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

function FilePreviewDialog({
    file,
    open,
    onOpenChange,
}: {
    file: FileModel | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const previewUrl = file ? sharedPreview.url(file.id) : '';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                onEscapeKeyDown={(event) => event.stopPropagation()}
                className="top-0 left-0 h-[100dvh] w-[100vw] max-w-none translate-x-0 translate-y-0 grid-rows-[auto_1fr] rounded-none p-6 sm:max-w-none"
            >
                <DialogTitle className="mt-3 flex items-center gap-2">
                    <FileIcon className="size-4 shrink-0" />
                    <span>{file?.name}</span>
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

export default function SharedWithMeIndex({
    files,
    folder,
    currentPermission,
}: {
    files: (FileModel & { can_delete?: boolean; can_write?: boolean })[];
    folder: { id: number; name: string; user_id?: number } | null;
    breadcrumbs: FolderBreadcrumb[];
    currentPermission: string | null;
    myFolderTree: Array<{ id: number; name: string; parent_id: number | null }>;
}) {
    const [selected, setSelected] = useState<number[]>([]);
    const [anchorIndex, setAnchorIndex] = useState<number | null>(null);
    const [previewFile, setPreviewFile] = useState<FileModel | null>(null);
    const [previewOpen, setPreviewOpen] = useState(false);
    const [sortBy, setSortBy] = useState<'name' | 'type' | 'size' | 'modified'>(
        'name',
    );
    const [sortDir, setSortDir] = useState<'asc' | 'desc'>('asc');
    const [view, setView] = useState<'list' | 'grid'>(() => {
        const saved = window.localStorage.getItem('shared-with-me.view');

        return saved === 'grid' ? 'grid' : 'list';
    });
    const [renameFile, setRenameFile] = useState<FileModel | null>(null);
    const [renameOpen, setRenameOpen] = useState(false);
    const [copyFile, setCopyFile] = useState<FileModel | null>(null);
    const [copyOpen, setCopyOpen] = useState(false);
    const tableRef = useRef<HTMLDivElement>(null);
    const previewOpenRef = useRef(previewOpen);
    const filesRef = useRef(files);
    const menuOpenRef = useRef(false);
    const bulkBarRef = useRef<HTMLDivElement>(null);

    const canWrite = currentPermission === 'write';

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
            router.visit(sharedIndex({ query: { folder: file.id } }));

            return;
        }

        setPreviewFile(file);
        setPreviewOpen(true);
    };

    const changeView = (next: 'list' | 'grid') => {
        setView(next);
        window.localStorage.setItem('shared-with-me.view', next);
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

    return (
        <>
            <Head title="Shared with Me" />

            <h1 className="sr-only">Shared with Me</h1>

            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="Shared with Me"
                    description="Files and folders shared with you by others"
                />

                <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                    {selected.length > 0 ? (
                        <div ref={bulkBarRef} className="flex items-center gap-2">
                            <Button variant="secondary" size="sm" asChild data-tooltip="Download Selected">
                                <a
                                    href={sharedBulkDownload.url({
                                        query: { ids: selected },
                                    })}
                                >
                                    <Download className="size-4" />
                                    Download Selected
                                </a>
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                data-tooltip="Clear selection"
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
                        {parentId && canWrite && (
                            <>
                                <CreateFolderDialog parentId={parentId} />
                                <UploadFileDialog parentId={parentId} />
                            </>
                        )}
                        {folder && (
                            <Badge
                                variant={canWrite ? 'default' : 'secondary'}
                                className={cn(
                                    canWrite
                                        ? 'bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300'
                                        : 'bg-muted text-muted-foreground',
                                )}
                            >
                                {canWrite ? (
                                    <>
                                        <Pencil className="mr-1 size-3" />
                                        Can Upload
                                    </>
                                ) : (
                                    <>
                                        <Lock className="mr-1 size-3" />
                                        Read Only
                                    </>
                                )}
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
                                                No shared files found.
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
                                                            canDelete={file.can_delete}
                                                            canWrite={canWrite}
                                                            onRename={() => {
                                                                setRenameFile(file);
                                                                setRenameOpen(true);
                                                            }}
                                                            onCopyToMyFiles={() => {
                                                                setCopyFile(file);
                                                                setCopyOpen(true);
                                                            }}
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
                                    No shared files found.
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
                                                )}
                                            >
                                                <div className="flex h-10 items-start justify-end">
                                                    <FileActions
                                                        file={file}
                                                        canDelete={file.can_delete}
                                                        canWrite={canWrite}
                                                        onRename={() => {
                                                            setRenameFile(file);
                                                            setRenameOpen(true);
                                                        }}
                                                        onCopyToMyFiles={() => {
                                                            setCopyFile(file);
                                                            setCopyOpen(true);
                                                        }}
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
                                                        'mb-2 truncate text-center text-sm font-medium',
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

                {renameFile && (
                    <RenameDialog
                        file={renameFile}
                        open={renameOpen}
                        onOpenChange={setRenameOpen}
                    />
                )}

                {copyFile && (
                    <CopyToMyFilesDialog
                        file={copyFile}
                        open={copyOpen}
                        onOpenChange={setCopyOpen}
                    />
                )}
            </div>
        </>
    );
}

SharedWithMeIndex.layout = (props: { breadcrumbs: FolderBreadcrumb[] }) => ({
    breadcrumbs: [
        {
            title: 'Shared with Me',
            href: sharedIndex(),
            folderId: null,
        },
        ...(props.breadcrumbs ?? []).map((crumb) => ({
            title: crumb.name,
            href: sharedIndex({ query: { folder: crumb.id } }),
            folderId: crumb.id,
        })),
    ],
});

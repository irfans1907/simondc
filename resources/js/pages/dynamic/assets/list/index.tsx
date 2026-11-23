import { Form, Head, Link, router } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import {
    Pencil,
    Plus,
    Search,
    Trash2,
    Upload,
    X,
    ChevronDown,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import AssetController from '@/actions/App/Http/Controllers/AssetController';
import AssetListController from '@/actions/App/Http/Controllers/AssetListController';
import ComboboxSelect from '@/components/combobox-select';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { displayValue } from '@/lib/format';
import { menuBreadcrumbs, showChildCrumbs } from '@/lib/menu-breadcrumbs';
import type { MenuRow } from '@/lib/menu-breadcrumbs';
import { overview as assetsOverview } from '@/routes/assets';

type SheetOption = {
    slug: string;
    label: string;
};

type SheetColumn = {
    key: string;
    label: string;
    storage: string;
    required: boolean;
    computed: boolean;
    hint: string | null;
};

type FormGroups = {
    common: string[];
    groups: { name: string; keys: string[]; keywords: string[] }[];
};

type CategoryNode = {
    id: number;
    name: string;
    children: CategoryNode[];
};

type AssetRow = {
    id: number;
    parent_asset_id: number | null;
    category_path: string[];
    managed_sheet: string | null;
    values: Record<string, string>;
};

type PaginatedAssets = {
    data: AssetRow[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type BatteryGroup = {
    key: string;
    name: string;
    brand: string | null;
    capacity: string | null;
    rows: AssetRow[];
};

type UpsGroup = {
    key: string;
    rows: AssetRow[];
};

type AssetGroup = {
    key: string;
    rows: AssetRow[];
};

type MultiRow = {
    label: string;
    keys: string[];
};

type PaginatedGroups<TGroup = BatteryGroup> = {
    data: TGroup[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type SplitTables = {
    ups: {
        label: string;
        columns: SheetColumn[];
        identity: string[];
        shared: string[];
        groups: PaginatedGroups<UpsGroup>;
    };
    battery: {
        label: string;
        columns: SheetColumn[];
        shared: string[];
        groups: PaginatedGroups;
    };
};

type CctvDeviceGroup = {
    key: string;
    rows: AssetRow[];
};

type CctvCameraGroup = {
    key: string;
    name: string;
    shared: Record<string, string>;
    rows: AssetRow[];
};

type CctvTables = {
    device: {
        label: string;
        columns: SheetColumn[];
        identity: string[];
        groups: PaginatedGroups<CctvDeviceGroup>;
    };
    camera: {
        label: string;
        columns: SheetColumn[];
        shared: string[];
        hidden: string[];
        groups: PaginatedGroups<CctvCameraGroup>;
    };
};

type SplitTableSet = SplitTables | CctvTables;

type GroupedTable = {
    label: string;
    columns: SheetColumn[];
    identity: string[];
    perRowIdentity: string[];
    groups: PaginatedGroups<AssetGroup>;
};

type UpsOption = {
    id: number;
    name: string;
    brand: string | null;
    capacity: string | null;
    category?: string | null;
};

type AssetPreset = {
    name: string;
    brand: string | null;
    capacity: string | null;
    frames: { nomor: string; tahun: string; sn: string }[];
    values?: Record<string, string>;
    category_path?: string[];
};

type Filters = {
    search: string;
    per_page: number;
};

type AssetErrors = Record<string, string | undefined>;

const perPageOptions = [5, 10, 15, 20, 25, 50] as const;

/**
 * Vertical dividers for list tables. `last:border-r-0` keeps the right edge
 * from doubling with the table wrapper border.
 */
const cellDivider = 'border-r border-sidebar-border/35 last:border-r-0';

function fieldName(column: SheetColumn): string {
    return column.storage === 'details'
        ? `details[${column.key}]`
        : column.storage;
}

function errorKey(column: SheetColumn): string {
    return column.storage === 'details'
        ? `details.${column.key}`
        : column.storage;
}

/**
 * Stored parents may point at any module row of the same UPS. When that id is
 * no longer offered, fall back to the representative option that matches the
 * battery's copied name/brand/capacity.
 */
function normalizeParentId(
    parentId: number | null,
    values: Record<string, string> | undefined,
    options: UpsOption[],
): number | null {
    if (parentId === null || options.some((option) => option.id === parentId)) {
        return parentId;
    }

    const match = options.find(
        (option) =>
            option.name.toLowerCase() === (values?.nama ?? '').toLowerCase() &&
            (option.brand ?? '').toLowerCase() ===
                (values?.merek ?? '').toLowerCase() &&
            (option.capacity ?? '') === (values?.kapasitas_kva ?? ''),
    );

    return match?.id ?? parentId;
}

function AssetFields({
    idPrefix,
    sheet,
    columns,
    categories,
    defaultCategory,
    formGroups,
    batteryKeywords,
    assetPresets,
    upsOptions,
    multiRow,
    cameraCategory,
    showCameraRows = true,
    initial,
    errors,
}: {
    idPrefix: string;
    sheet: string;
    columns: SheetColumn[];
    categories: CategoryNode[];
    defaultCategory: string | null;
    formGroups: FormGroups | null;
    batteryKeywords: string[];
    assetPresets: AssetPreset[];
    upsOptions: UpsOption[];
    multiRow?: MultiRow | null;
    cameraCategory?: string | null;
    showCameraRows?: boolean;
    initial?: {
        values: Record<string, string>;
        category_path: string[];
        parent_asset_id: number | null;
    };
    errors: AssetErrors;
}) {
    const [path, setPath] = useState<string[]>(
        initial?.category_path ?? (defaultCategory ? [defaultCategory] : ['']),
    );
    const [parentId, setParentId] = useState<number | null>(() =>
        normalizeParentId(
            initial?.parent_asset_id ?? null,
            initial?.values,
            upsOptions,
        ),
    );
    const [formValues, setFormValues] = useState<Record<string, string>>(
        initial?.values ?? {},
    );
    const [cameraRows, setCameraRows] = useState<Record<string, string>[]>([
        {},
    ]);

    const nameColumn = columns.find(
        (column) => column.storage === 'name' && !column.computed,
    );
    const nameKey = nameColumn?.key ?? 'nama';
    const hasPresetValues = assetPresets.some(
        (preset) => preset.values !== undefined,
    );
    const nameUsesCombobox =
        nameColumn !== undefined &&
        (nameColumn.key === 'nama' || hasPresetValues);

    const updateCamera = (index: number, key: string, value: string): void => {
        setCameraRows((current) =>
            current.map((row, rowIndex) =>
                rowIndex === index ? { ...row, [key]: value } : row,
            ),
        );
    };

    const addCamera = (): void => {
        setCameraRows((current) => [...current, {}]);
    };

    const removeCamera = (index: number): void => {
        setCameraRows((current) =>
            current.length === 1
                ? [{}]
                : current.filter((_, rowIndex) => rowIndex !== index),
        );
    };

    const updateValue = (key: string, value: string): void => {
        setFormValues((current) => ({ ...current, [key]: value }));
    };

    const selectedPreset = assetPresets.find(
        (preset) =>
            preset.name.toLowerCase() ===
            (formValues[nameKey] ?? '').toLowerCase(),
    );

    const handleNameChange = (value: string): void => {
        const preset = assetPresets.find(
            (item) => item.name.toLowerCase() === value.toLowerCase(),
        );
        const presetValues =
            preset?.values ??
            (preset
                ? {
                      merek: preset.brand ?? '',
                      kapasitas_kva: preset.capacity ?? '',
                  }
                : {});

        setFormValues((current) => ({
            ...current,
            ...presetValues,
            [nameKey]: value,
        }));

        if (
            preset?.category_path?.length &&
            categories.some((node) => node.name === preset.category_path?.[0])
        ) {
            setPath(preset.category_path);
        }
    };

    const handleFrameNumberChange = (value: string): void => {
        const frame = selectedPreset?.frames.find(
            (item) => item.nomor === value,
        );

        setFormValues((current) => ({
            ...current,
            frame_nomor: value,
            ...(frame ? { frame_tahun: frame.tahun, frame_sn: frame.sn } : {}),
        }));
    };

    const subCategory = path[1]?.trim() ?? '';
    const levels = path
        .map((level) => level.trim().toLowerCase())
        .filter((level) => level !== '');
    const isBattery = batteryKeywords.some((keyword) =>
        levels.some((level) => level.includes(keyword.toLowerCase())),
    );
    /*
    | A camera submitted on its own (category Kamera) is a single asset with a
    | device parent, so its camera fields move into the main form and the
    | multi-row device form is hidden.
    */
    const isCctvCamera = cameraCategory
        ? levels.includes(cameraCategory.toLowerCase())
        : false;
    const matchedGroups = formGroups?.groups.filter((group) =>
        group.keywords.some((keyword) =>
            levels.some((level) => level.includes(keyword.toLowerCase())),
        ),
    );

    let visibleKeys: Set<string> | null = null;

    if (formGroups) {
        if (subCategory === '') {
            visibleKeys = new Set(formGroups.common);
        } else if (matchedGroups && matchedGroups.length > 0) {
            visibleKeys = new Set([
                ...formGroups.common,
                ...matchedGroups.flatMap((group) => group.keys),
            ]);
        }
    }

    const visibleColumns = columns.filter((column) => {
        if (column.computed) {
            return false;
        }

        /*
        | A camera added on its own only edits its per-camera fields; the
        | asset name is derived from the alias/channel and the device
        | columns stay with the device.
        */
        if (isCctvCamera) {
            return multiRow?.keys.includes(column.key) ?? false;
        }

        if (multiRow?.keys.includes(column.key) ?? false) {
            return false;
        }

        if (
            isBattery &&
            ['nama', 'merek', 'kapasitas_kva'].includes(column.key)
        ) {
            return false;
        }

        return visibleKeys === null || visibleKeys.has(column.key);
    });

    /*
    | On EMS the device name sits next to Kategori in the form, while the
    | table keeps Lantai Perangkat as its first column.
    */
    const formColumns =
        sheet === 'ems'
            ? [
                  ...visibleColumns.filter((column) => column.key === nameKey),
                  ...visibleColumns.filter((column) => column.key !== nameKey),
              ]
            : visibleColumns;

    const cameraColumns = columns.filter(
        (column) =>
            !column.computed && (multiRow?.keys.includes(column.key) ?? false),
    );

    const levelLabels = [
        'Kategori',
        'Sub Kategori',
        'Sub Kategori 1',
        'Sub Kategori 2',
        'Sub Kategori 3',
        'Sub Kategori 4',
    ];

    const optionsAt = (index: number): string[] => {
        let nodes = categories;

        for (let i = 0; i < index; i++) {
            const node = nodes.find((candidate) => candidate.name === path[i]);

            if (!node) {
                return [];
            }

            nodes = node.children;
        }

        return nodes.map((node) => node.name);
    };

    const updateLevel = (index: number, value: string) => {
        setPath((current) => [...current.slice(0, index), value]);
    };

    const removeLevel = (index: number) => {
        setPath((current) => current.slice(0, index));
    };

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            {path.map((levelName, index) => (
                <div key={index} className="grid content-start gap-2">
                    <div className="flex h-6 items-center justify-between">
                        <Label htmlFor={`${idPrefix}-category-${index}`}>
                            {levelLabels[index] ?? `Level ${index + 1}`}
                        </Label>
                        <div className="flex items-center gap-1">
                            {index === path.length - 1 && levelName !== '' && (
                                <button
                                    type="button"
                                    onClick={() =>
                                        setPath((current) => [...current, ''])
                                    }
                                    className="inline-flex size-6 items-center justify-center rounded-md border border-transparent bg-green-600 text-white hover:bg-green-700"
                                    aria-label="Tambah sub kategori"
                                >
                                    <Plus className="size-3.5" />
                                </button>
                            )}
                            {index > 0 && (
                                <button
                                    type="button"
                                    onClick={() => removeLevel(index)}
                                    className="inline-flex size-6 items-center justify-center rounded-md border border-transparent bg-red-600 text-white hover:bg-red-700"
                                    aria-label={`Hapus level ${index + 1}`}
                                >
                                    <X className="size-3.5" />
                                </button>
                            )}
                        </div>
                    </div>
                    <ComboboxSelect
                        name="category_path[]"
                        id={`${idPrefix}-category-${index}`}
                        options={optionsAt(index)}
                        value={levelName}
                        onValueChange={(value) => updateLevel(index, value)}
                        allowCreate
                        placeholder={
                            index === 0 || path[index - 1]
                                ? 'Pilih/ketik kategori...'
                                : 'Pilih level sebelumnya dulu...'
                        }
                        searchPlaceholder="Cari atau ketik baru..."
                        emptyMessage={
                            index === 0 || path[index - 1]
                                ? 'Belum ada opsi.'
                                : 'Pilih level sebelumnya dulu...'
                        }
                        required={index === 0}
                    />
                </div>
            ))}

            <InputError
                message={errors.category_path ?? errors['category_path.0']}
            />

            {(isBattery || isCctvCamera) && (
                <div className="grid content-start gap-2 sm:col-span-2">
                    <Label htmlFor={`${idPrefix}-parent`}>
                        {isBattery ? 'UPS Induk' : 'Perangkat Induk'}
                        <span className="ml-1 text-red-600">*</span>
                    </Label>
                    <input
                        type="hidden"
                        name="parent_asset_id"
                        value={parentId ?? ''}
                    />
                    <Select
                        value={parentId ? String(parentId) : ''}
                        onValueChange={(value) => setParentId(Number(value))}
                    >
                        <SelectTrigger id={`${idPrefix}-parent`}>
                            <SelectValue
                                placeholder={
                                    isBattery
                                        ? 'Pilih UPS induk...'
                                        : 'Pilih perangkat DVR/NVR...'
                                }
                            />
                        </SelectTrigger>
                        <SelectContent>
                            {upsOptions.map((option) => (
                                <SelectItem
                                    key={option.id}
                                    value={String(option.id)}
                                >
                                    {[
                                        option.name,
                                        isBattery
                                            ? option.brand
                                            : (option.category ?? option.brand),
                                        isBattery ? option.capacity : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <p className="text-xs text-muted-foreground">
                        {isBattery
                            ? 'Nama, merek, dan kapasitas baterai mengikuti UPS induk.'
                            : 'Kamera akan terhubung ke perangkat DVR/NVR ini.'}
                    </p>
                    <InputError message={errors.parent_asset_id} />
                </div>
            )}

            {formColumns.map((column) => (
                <div
                    key={column.key}
                    className={`grid content-start gap-2 ${
                        column.storage === 'notes' ||
                        column.key === 'kapasitas_hd'
                            ? 'sm:col-span-2'
                            : ''
                    }`}
                >
                    <div className="flex h-6 items-center">
                        <Label htmlFor={`${idPrefix}-${column.key}`}>
                            {column.label}
                            {column.required && (
                                <span className="ml-1 text-red-600">*</span>
                            )}
                        </Label>
                    </div>
                    {column.storage === 'notes' ? (
                        <textarea
                            id={`${idPrefix}-${column.key}`}
                            name={fieldName(column)}
                            className="flex h-24 w-full min-w-0 resize-none rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            value={formValues[column.key] ?? ''}
                            onChange={(event) =>
                                updateValue(column.key, event.target.value)
                            }
                            autoComplete="off"
                        />
                    ) : column.key === 'kapasitas_hd' ? (
                        <textarea
                            id={`${idPrefix}-${column.key}`}
                            name={fieldName(column)}
                            className="flex h-24 w-full min-w-0 resize-none rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            value={(formValues[column.key] ?? '').replace(
                                /;\s*/g,
                                '\n',
                            )}
                            onChange={(event) =>
                                updateValue(column.key, event.target.value)
                            }
                            placeholder="Satu slot per baris, contoh: SLOT-2 : 2TB"
                            autoComplete="off"
                        />
                    ) : column.key === nameKey &&
                      nameUsesCombobox &&
                      !isCctvCamera ? (
                        <ComboboxSelect
                            id={`${idPrefix}-${column.key}`}
                            name={fieldName(column)}
                            options={assetPresets.map((preset) => preset.name)}
                            value={formValues[nameKey] ?? ''}
                            onValueChange={handleNameChange}
                            placeholder="Cari atau ketik nama baru..."
                            searchPlaceholder="Cari nama..."
                            emptyMessage="Belum ada nama aset."
                            allowCreate
                            required={column.required}
                        />
                    ) : column.key === 'frame_nomor' ? (
                        <ComboboxSelect
                            id={`${idPrefix}-${column.key}`}
                            name={fieldName(column)}
                            options={
                                selectedPreset?.frames.map(
                                    (frame) => frame.nomor,
                                ) ?? []
                            }
                            value={formValues.frame_nomor ?? ''}
                            onValueChange={handleFrameNumberChange}
                            placeholder="Cari atau ketik nomor frame..."
                            searchPlaceholder="Cari nomor frame..."
                            emptyMessage="Belum ada nomor frame."
                            allowCreate
                            required={column.required}
                        />
                    ) : [
                          'merek',
                          'kapasitas_kva',
                          'frame_tahun',
                          'frame_sn',
                      ].includes(column.key) ? (
                        <Input
                            id={`${idPrefix}-${column.key}`}
                            name={fieldName(column)}
                            className="block w-full"
                            value={formValues[column.key] ?? ''}
                            onChange={(event) =>
                                updateValue(column.key, event.target.value)
                            }
                            required={column.required}
                            autoComplete="off"
                        />
                    ) : (
                        <Input
                            id={`${idPrefix}-${column.key}`}
                            name={fieldName(column)}
                            className="block w-full"
                            value={formValues[column.key] ?? ''}
                            onChange={(event) =>
                                updateValue(column.key, event.target.value)
                            }
                            required={column.required}
                            autoComplete="off"
                        />
                    )}
                    {column.hint && (
                        <p className="text-xs text-muted-foreground">
                            {column.hint}
                        </p>
                    )}
                    <InputError message={errors[errorKey(column)]} />
                </div>
            ))}

            {showCameraRows && multiRow && !isCctvCamera && (
                <div className="space-y-4 sm:col-span-2">
                    <div className="flex items-center justify-between gap-2">
                        <h3 className="text-sm font-semibold">
                            {multiRow.label}
                        </h3>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={addCamera}
                        >
                            <Plus className="size-4" />
                            Tambah {multiRow.label}
                        </Button>
                    </div>

                    {cameraRows.map((row, index) => (
                        <div
                            key={index}
                            className="rounded-lg border border-sidebar-border/70 p-4"
                        >
                            <div className="mb-3 flex items-center justify-between gap-2">
                                <span className="text-sm font-medium">
                                    {multiRow.label} {index + 1}
                                </span>
                                {cameraRows.length > 1 && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => removeCamera(index)}
                                    >
                                        <X className="size-4" />
                                        Hapus
                                    </Button>
                                )}
                            </div>

                            <div className="grid content-start gap-4 sm:grid-cols-2">
                                {cameraColumns.map((column) => (
                                    <div
                                        key={column.key}
                                        className="grid content-start gap-2"
                                    >
                                        <div className="flex h-6 items-center">
                                            <Label
                                                htmlFor={`${idPrefix}-camera-${index}-${column.key}`}
                                            >
                                                {column.label}
                                            </Label>
                                        </div>
                                        {column.storage === 'notes' ? (
                                            <textarea
                                                id={`${idPrefix}-camera-${index}-${column.key}`}
                                                name={`cameras[${index}][${column.key}]`}
                                                className="flex h-24 w-full min-w-0 resize-none rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                value={row[column.key] ?? ''}
                                                onChange={(event) =>
                                                    updateCamera(
                                                        index,
                                                        column.key,
                                                        event.target.value,
                                                    )
                                                }
                                                autoComplete="off"
                                            />
                                        ) : (
                                            <Input
                                                id={`${idPrefix}-camera-${index}-${column.key}`}
                                                name={`cameras[${index}][${column.key}]`}
                                                className="block w-full"
                                                value={row[column.key] ?? ''}
                                                onChange={(event) =>
                                                    updateCamera(
                                                        index,
                                                        column.key,
                                                        event.target.value,
                                                    )
                                                }
                                                autoComplete="off"
                                            />
                                        )}
                                        {column.hint && (
                                            <p className="text-xs text-muted-foreground">
                                                {column.hint}
                                            </p>
                                        )}
                                        <InputError
                                            message={
                                                errors[
                                                    `cameras.${index}.${column.key}`
                                                ]
                                            }
                                        />
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}

                    <InputError message={errors.cameras} />
                </div>
            )}
        </div>
    );
}

function CreateAssetDialog({
    sheet,
    columns,
    categories,
    defaultCategory,
    formGroups,
    batteryKeywords,
    assetPresets,
    upsOptions,
    multiRow,
    cameraCategory,
}: {
    sheet: string;
    columns: SheetColumn[];
    categories: CategoryNode[];
    defaultCategory: string | null;
    formGroups: FormGroups | null;
    batteryKeywords: string[];
    assetPresets: AssetPreset[];
    upsOptions: UpsOption[];
    multiRow: MultiRow | null;
    cameraCategory: string | null;
}) {
    const [open, setOpen] = useState(false);
    const [formKey, setFormKey] = useState(0);
    const formTarget = multiRow
        ? AssetController.storeBulk.form()
        : AssetController.store.form();

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus className="size-4" />
                    Tambah Aset
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogTitle>Tambah Aset</DialogTitle>
                <DialogDescription>
                    Isi kolom sesuai sheet yang sedang aktif.
                </DialogDescription>

                <Form
                    {...formTarget}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        setFormKey((key) => key + 1);
                    }}
                    resetOnSuccess
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <input type="hidden" name="sheet" value={sheet} />
                            <AssetFields
                                key={formKey}
                                idPrefix="create"
                                sheet={sheet}
                                columns={columns}
                                categories={categories}
                                defaultCategory={defaultCategory}
                                formGroups={formGroups}
                                batteryKeywords={batteryKeywords}
                                assetPresets={assetPresets}
                                upsOptions={upsOptions}
                                multiRow={multiRow}
                                cameraCategory={cameraCategory}
                                errors={errors as AssetErrors}
                            />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Batal</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Simpan
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function EditAssetDialog({
    asset,
    sheet,
    columns,
    categories,
    formGroups,
    batteryKeywords,
    assetPresets,
    upsOptions,
    multiRow,
    cameraCategory,
}: {
    asset: AssetRow;
    sheet: string;
    columns: SheetColumn[];
    categories: CategoryNode[];
    formGroups: FormGroups | null;
    batteryKeywords: string[];
    assetPresets: AssetPreset[];
    upsOptions: UpsOption[];
    multiRow: MultiRow | null;
    cameraCategory: string | null;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Pencil className="size-4" />
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogTitle>Edit Aset</DialogTitle>
                <DialogDescription>
                    Ubah data sesuai sheet yang sedang aktif.
                </DialogDescription>

                <Form
                    {...AssetController.update.form(asset.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <input type="hidden" name="sheet" value={sheet} />
                            <AssetFields
                                idPrefix={`edit-${asset.id}`}
                                sheet={sheet}
                                columns={columns}
                                categories={categories}
                                defaultCategory={null}
                                formGroups={formGroups}
                                batteryKeywords={batteryKeywords}
                                assetPresets={assetPresets}
                                upsOptions={upsOptions}
                                multiRow={multiRow}
                                showCameraRows={false}
                                cameraCategory={cameraCategory}
                                initial={{
                                    values: asset.values,
                                    category_path: asset.category_path,
                                    parent_asset_id: asset.parent_asset_id,
                                }}
                                errors={errors as AssetErrors}
                            />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Batal</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Simpan
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteAssetDialog({
    asset,
    sheet,
}: {
    asset: AssetRow;
    sheet: string;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="destructive" size="sm">
                    <Trash2 className="size-4" />
                    Hapus
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Hapus Aset</DialogTitle>
                <DialogDescription>
                    Yakin ingin menghapus aset ini? Tindakan ini tidak bisa
                    dibatalkan.
                </DialogDescription>

                <Form
                    {...AssetController.destroy.form(asset.id, {
                        query: { sheet },
                    })}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Batal</Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                Hapus
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function SheetSelector({
    sheets,
    activeSheet,
}: {
    sheets: SheetOption[];
    activeSheet: string;
}) {
    function changeSheet(nextSheet: string): void {
        router.get(
            AssetListController.index.url(),
            { sheet: nextSheet },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    }

    return (
        <div className="flex items-center gap-2">
            <Select value={activeSheet} onValueChange={changeSheet}>
                <SelectTrigger
                    id="asset-list-sheet"
                    className="h-10 min-w-[150px]"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {sheets.map((sheetOption) => (
                        <SelectItem
                            key={sheetOption.slug}
                            value={sheetOption.slug}
                            className="py-2"
                        >
                            {sheetOption.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

function AssetSearch({
    sheet,
    search,
    perPage,
}: {
    sheet: string;
    search: string;
    perPage: number;
}) {
    const [value, setValue] = useState(search);

    const applyFilter = useCallback(
        (nextSearch: string) => {
            router.get(
                AssetListController.index.url(),
                {
                    sheet,
                    search: nextSearch || undefined,
                    per_page: perPage,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['assets', 'filters'],
                },
            );
        },
        [sheet, perPage],
    );

    useEffect(() => {
        const timeout = setTimeout(() => {
            const query = value.trim();

            if (query === search) {
                return;
            }

            applyFilter(query);
        }, 300);

        return () => clearTimeout(timeout);
    }, [value, search, applyFilter]);

    return (
        <div className="relative w-full sm:max-w-xs">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                id="asset-list-search"
                name="search"
                value={value}
                onChange={(event) => setValue(event.target.value)}
                onKeyDown={(event) => {
                    if (event.key === 'Escape') {
                        setValue('');
                    }
                }}
                placeholder="Cari nama, merek, type, alias, kategori..."
                className="pr-9 pl-9"
            />
            {value && (
                <button
                    type="button"
                    onClick={() => setValue('')}
                    className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    aria-label="Hapus pencarian"
                >
                    <X className="size-4" />
                </button>
            )}
        </div>
    );
}

function PerPageSelector({
    sheet,
    search,
    perPage,
}: {
    sheet: string;
    search: string;
    perPage: number;
}) {
    function changePerPage(value: string) {
        router.get(
            AssetListController.index.url(),
            {
                sheet,
                search: search || undefined,
                per_page: value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['assets', 'filters'],
            },
        );
    }

    return (
        <div className="flex items-center gap-2 text-sm">
            <span className="text-muted-foreground">Tampilkan</span>
            <Select value={String(perPage)} onValueChange={changePerPage}>
                <SelectTrigger className="h-9 w-[72px]">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent side="top">
                    {perPageOptions.map((option) => (
                        <SelectItem key={option} value={String(option)}>
                            {option}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <span className="text-muted-foreground">per halaman</span>
        </div>
    );
}

function Pagination({
    assets,
    sheet,
    search,
    perPage,
    showPerPage = true,
    unit = 'aset',
}: {
    assets:
        | PaginatedAssets
        | PaginatedGroups<BatteryGroup>
        | PaginatedGroups<UpsGroup>;
    sheet: string;
    search: string;
    perPage: number;
    showPerPage?: boolean;
    unit?: string;
}) {
    return (
        <div className="mt-4 grid items-center gap-3 text-sm sm:grid-cols-3">
            <div>
                {showPerPage && (
                    <PerPageSelector
                        sheet={sheet}
                        search={search}
                        perPage={perPage}
                    />
                )}
            </div>

            <span className="text-center text-muted-foreground">
                Total{' '}
                <span className="font-medium text-foreground">
                    {assets.total}
                </span>{' '}
                {unit}
            </span>

            <div className="flex flex-wrap items-center justify-center gap-4 sm:justify-end">
                <div className="flex items-center gap-1">
                    {assets.links.map((link, index) => {
                        if (link.url === null) {
                            return (
                                <span
                                    key={index}
                                    className="px-3 py-1.5 text-muted-foreground"
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            );
                        }

                        return (
                            <Link
                                key={index}
                                href={link.url}
                                preserveScroll
                                className={`rounded-md px-3 py-1.5 ${
                                    link.active
                                        ? 'bg-primary font-medium text-primary-foreground'
                                        : 'hover:bg-accent hover:text-accent-foreground'
                                }`}
                                dangerouslySetInnerHTML={{
                                    __html: link.label,
                                }}
                            />
                        );
                    })}
                </div>
            </div>
        </div>
    );
}

function CellValue({ columnKey, value }: { columnKey: string; value: string }) {
    const text = displayValue(value);

    if (text === '-') {
        return <>-</>;
    }

    if (columnKey === 'kategori') {
        return (
            <div className="flex flex-col items-center gap-1">
                {text.split(' > ').map((segment, index) => (
                    <span key={`${segment}-${index}`} className="contents">
                        {index > 0 && (
                            <ChevronDown className="size-3 shrink-0 text-muted-foreground" />
                        )}
                        <Badge variant="secondary">{segment}</Badge>
                    </span>
                ))}
            </div>
        );
    }

    if (columnKey === 'keterangan') {
        return (
            <div className="flex flex-col items-center gap-1">
                {text.split('\n').map((line, index) => (
                    <Badge key={`${line}-${index}`} variant="outline">
                        {line}
                    </Badge>
                ))}
            </div>
        );
    }

    if (
        ['kapasitas_hd', 'kapasitas'].includes(columnKey) &&
        /[;\n]/.test(text)
    ) {
        return (
            <div className="flex flex-col items-center gap-0.5">
                {text
                    .split(/[;\n]+/)
                    .map((line) => line.trim())
                    .filter((line) => line !== '')
                    .map((line, index) => (
                        <span key={`${line}-${index}`}>{line}</span>
                    ))}
            </div>
        );
    }

    return <>{text}</>;
}

function sharedValue(group: BatteryGroup, key: string): string {
    if (key === 'nama') {
        return group.name;
    }

    if (key === 'merek') {
        return group.brand ?? '';
    }

    if (key === 'kapasitas_kva') {
        return group.capacity ?? '';
    }

    return '';
}

/**
 * Split rows into consecutive buckets that share the same values for the
 * given columns. Used to nest frame rows inside one UPS row group.
 */
function groupRowsBy(rows: AssetRow[], keys: string[]): AssetRow[][] {
    const buckets = new Map<string, AssetRow[]>();

    rows.forEach((row) => {
        const key = keys
            .map((column) => row.values[column] ?? '')
            .join('\u001F');
        const bucket = buckets.get(key);

        if (bucket) {
            bucket.push(row);
        } else {
            buckets.set(key, [row]);
        }
    });

    return [...buckets.values()];
}

function AssetActions({
    asset,
    sheet,
    columns,
    categories,
    formGroups,
    batteryKeywords,
    assetPresets,
    upsOptions,
    multiRow,
    cameraCategory,
    canManage,
}: {
    asset: AssetRow;
    sheet: string;
    columns: SheetColumn[];
    categories: CategoryNode[];
    formGroups: FormGroups | null;
    batteryKeywords: string[];
    assetPresets: AssetPreset[];
    upsOptions: UpsOption[];
    multiRow: MultiRow | null;
    cameraCategory: string | null;
    canManage: boolean;
}) {
    if (!canManage) {
        return null;
    }

    return (
        <td className={`${cellDivider} px-4 py-3`}>
            {asset.managed_sheet ? (
                <span className="text-xs whitespace-nowrap text-muted-foreground">
                    Kelola di {asset.managed_sheet}
                </span>
            ) : (
                <div className="flex items-center justify-center gap-2">
                    <EditAssetDialog
                        asset={asset}
                        sheet={sheet}
                        columns={columns}
                        categories={categories}
                        formGroups={formGroups}
                        batteryKeywords={batteryKeywords}
                        assetPresets={assetPresets}
                        upsOptions={upsOptions}
                        multiRow={multiRow}
                        cameraCategory={cameraCategory}
                    />
                    <DeleteAssetDialog asset={asset} sheet={sheet} />
                </div>
            )}
        </td>
    );
}

function SplitTablesView({
    tables,
    sheet,
    columns,
    categories,
    formGroups,
    batteryKeywords,
    assetPresets,
    upsOptions,
    multiRow,
    cameraCategory,
    filters,
    canManage,
}: {
    tables: SplitTables;
    sheet: string;
    columns: SheetColumn[];
    categories: CategoryNode[];
    formGroups: FormGroups | null;
    batteryKeywords: string[];
    assetPresets: AssetPreset[];
    upsOptions: UpsOption[];
    multiRow: MultiRow | null;
    cameraCategory: string | null;
    filters: Filters;
    canManage: boolean;
}) {
    const upsColumns = tables.ups.columns;
    const upsIdentity = tables.ups.identity;
    const upsShared = tables.ups.shared;
    const upsOtherColumns = upsColumns.filter(
        (column) =>
            !upsIdentity.includes(column.key) &&
            !upsShared.includes(column.key),
    );
    const batteryColumns = tables.battery.columns;
    const shared = tables.battery.shared;
    const otherColumns = batteryColumns.filter(
        (column) => !shared.includes(column.key),
    );

    return (
        <div className="space-y-8">
            <section>
                <h2 className="mb-2 text-base font-semibold">
                    {tables.ups.label}
                </h2>
                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                {upsColumns.map((column) => (
                                    <th
                                        key={column.key}
                                        className={`${cellDivider} px-4 py-3 text-center font-medium whitespace-nowrap`}
                                    >
                                        {column.label}
                                    </th>
                                ))}
                                {canManage && (
                                    <th
                                        className={`${cellDivider} px-4 py-3 text-center font-medium`}
                                    >
                                        Aksi
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {tables.ups.groups.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={
                                            upsColumns.length +
                                            (canManage ? 1 : 0)
                                        }
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        Belum ada data UPS.
                                    </td>
                                </tr>
                            ) : (
                                tables.ups.groups.data.flatMap((group) => {
                                    const frameGroups = groupRowsBy(
                                        group.rows,
                                        upsShared,
                                    );
                                    let offset = 0;

                                    return frameGroups.flatMap((frameRows) => {
                                        const frameStart = offset;
                                        offset += frameRows.length;

                                        return frameRows.map(
                                            (asset, frameIndex) => (
                                                <tr
                                                    key={asset.id}
                                                    className="border-b border-sidebar-border/50 last:border-b-0"
                                                >
                                                    {frameStart === 0 &&
                                                        frameIndex === 0 &&
                                                        upsIdentity.map(
                                                            (key) => (
                                                                <td
                                                                    key={key}
                                                                    rowSpan={
                                                                        group
                                                                            .rows
                                                                            .length
                                                                    }
                                                                    className={`${cellDivider} px-4 py-3 text-center align-middle whitespace-nowrap`}
                                                                >
                                                                    <CellValue
                                                                        columnKey={
                                                                            key
                                                                        }
                                                                        value={
                                                                            asset
                                                                                .values[
                                                                                key
                                                                            ] ??
                                                                            ''
                                                                        }
                                                                    />
                                                                </td>
                                                            ),
                                                        )}
                                                    {frameIndex === 0 &&
                                                        upsShared.map((key) => (
                                                            <td
                                                                key={key}
                                                                rowSpan={
                                                                    frameRows.length
                                                                }
                                                                className={`${cellDivider} px-4 py-3 text-center align-middle whitespace-nowrap`}
                                                            >
                                                                <CellValue
                                                                    columnKey={
                                                                        key
                                                                    }
                                                                    value={
                                                                        asset
                                                                            .values[
                                                                            key
                                                                        ] ?? ''
                                                                    }
                                                                />
                                                            </td>
                                                        ))}
                                                    {upsOtherColumns.map(
                                                        (column) => (
                                                            <td
                                                                key={column.key}
                                                                className={`${cellDivider} px-4 py-3 text-center ${
                                                                    column.key ===
                                                                    'keterangan'
                                                                        ? 'min-w-[180px]'
                                                                        : 'whitespace-nowrap'
                                                                }`}
                                                            >
                                                                <CellValue
                                                                    columnKey={
                                                                        column.key
                                                                    }
                                                                    value={
                                                                        asset
                                                                            .values[
                                                                            column
                                                                                .key
                                                                        ] ?? ''
                                                                    }
                                                                />
                                                            </td>
                                                        ),
                                                    )}
                                                    <AssetActions
                                                        asset={asset}
                                                        sheet={sheet}
                                                        columns={columns}
                                                        categories={categories}
                                                        formGroups={formGroups}
                                                        batteryKeywords={
                                                            batteryKeywords
                                                        }
                                                        assetPresets={
                                                            assetPresets
                                                        }
                                                        upsOptions={upsOptions}
                                                        multiRow={multiRow}
                                                        cameraCategory={
                                                            cameraCategory
                                                        }
                                                        canManage={canManage}
                                                    />
                                                </tr>
                                            ),
                                        );
                                    });
                                })
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination
                    assets={tables.ups.groups}
                    sheet={sheet}
                    search={filters.search}
                    perPage={filters.per_page}
                    unit="grup"
                />
            </section>

            <section>
                <h2 className="mb-2 text-base font-semibold">
                    {tables.battery.label}
                </h2>
                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                {batteryColumns.map((column) => (
                                    <th
                                        key={column.key}
                                        className={`${cellDivider} px-4 py-3 text-center font-medium whitespace-nowrap`}
                                    >
                                        {column.label}
                                    </th>
                                ))}
                                {canManage && (
                                    <th
                                        className={`${cellDivider} px-4 py-3 text-center font-medium`}
                                    >
                                        Aksi
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {tables.battery.groups.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={
                                            batteryColumns.length +
                                            (canManage ? 1 : 0)
                                        }
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        Belum ada data baterai.
                                    </td>
                                </tr>
                            ) : (
                                tables.battery.groups.data.flatMap((group) =>
                                    group.rows.map((row, index) => (
                                        <tr
                                            key={row.id}
                                            className="border-b border-sidebar-border/50 last:border-b-0"
                                        >
                                            {index === 0 &&
                                                shared.map((key) => (
                                                    <td
                                                        key={key}
                                                        rowSpan={
                                                            group.rows.length
                                                        }
                                                        className={`${cellDivider} px-4 py-3 text-center align-middle whitespace-nowrap`}
                                                    >
                                                        {displayValue(
                                                            sharedValue(
                                                                group,
                                                                key,
                                                            ),
                                                        )}
                                                    </td>
                                                ))}
                                            {otherColumns.map((column) => (
                                                <td
                                                    key={column.key}
                                                    className={`${cellDivider} px-4 py-3 text-center ${
                                                        column.key ===
                                                        'keterangan'
                                                            ? 'min-w-[180px]'
                                                            : 'whitespace-nowrap'
                                                    }`}
                                                >
                                                    <CellValue
                                                        columnKey={column.key}
                                                        value={
                                                            row.values[
                                                                column.key
                                                            ] ?? ''
                                                        }
                                                    />
                                                </td>
                                            ))}
                                            <AssetActions
                                                asset={row}
                                                sheet={sheet}
                                                columns={columns}
                                                categories={categories}
                                                formGroups={formGroups}
                                                batteryKeywords={
                                                    batteryKeywords
                                                }
                                                assetPresets={assetPresets}
                                                upsOptions={upsOptions}
                                                multiRow={multiRow}
                                                cameraCategory={cameraCategory}
                                                canManage={canManage}
                                            />
                                        </tr>
                                    )),
                                )
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination
                    assets={tables.battery.groups}
                    sheet={sheet}
                    search={filters.search}
                    perPage={filters.per_page}
                    showPerPage={false}
                    unit="grup"
                />
            </section>
        </div>
    );
}

function CctvTablesView({
    tables,
    sheet,
    columns,
    categories,
    formGroups,
    batteryKeywords,
    assetPresets,
    upsOptions,
    multiRow,
    cameraCategory,
    filters,
    canManage,
}: {
    tables: CctvTables;
    sheet: string;
    columns: SheetColumn[];
    categories: CategoryNode[];
    formGroups: FormGroups | null;
    batteryKeywords: string[];
    assetPresets: AssetPreset[];
    upsOptions: UpsOption[];
    multiRow: MultiRow | null;
    cameraCategory: string | null;
    filters: Filters;
    canManage: boolean;
}) {
    const deviceColumns = tables.device.columns;
    const cameraColumns = tables.camera.columns.filter(
        (column) => !tables.camera.hidden.includes(column.key),
    );
    const shared = tables.camera.shared.filter(
        (key) => !tables.camera.hidden.includes(key),
    );
    const otherColumns = cameraColumns.filter(
        (column) => !shared.includes(column.key),
    );
    /*
    | The alias is the camera identity shown in the table. When it is empty
    | fall back to the channel number and then the asset name so no row
    | loses its label.
    */
    const cameraCellValue = (asset: AssetRow, key: string): string => {
        if (key === 'alias_kamera') {
            return (
                asset.values.alias_kamera ||
                asset.values.camera_no ||
                asset.values.nama ||
                ''
            );
        }

        return asset.values[key] ?? '';
    };

    return (
        <div className="space-y-8">
            <section>
                <h2 className="mb-2 text-base font-semibold">
                    {tables.device.label}
                </h2>
                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                {deviceColumns.map((column) => (
                                    <th
                                        key={column.key}
                                        className={`${cellDivider} px-4 py-3 text-center font-medium whitespace-nowrap`}
                                    >
                                        {column.label}
                                    </th>
                                ))}
                                {canManage && (
                                    <th
                                        className={`${cellDivider} px-4 py-3 text-center font-medium`}
                                    >
                                        Aksi
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {tables.device.groups.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={
                                            deviceColumns.length +
                                            (canManage ? 1 : 0)
                                        }
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        Belum ada data perangkat CCTV.
                                    </td>
                                </tr>
                            ) : (
                                tables.device.groups.data.flatMap((group) =>
                                    group.rows.map((asset, rowIndex) => (
                                        <tr
                                            key={asset.id}
                                            className="border-b border-sidebar-border/50 last:border-b-0"
                                        >
                                            {deviceColumns.map((column) => {
                                                if (
                                                    !tables.device.identity.includes(
                                                        column.key,
                                                    )
                                                ) {
                                                    return (
                                                        <td
                                                            key={column.key}
                                                            className={`${cellDivider} px-4 py-3 text-center ${
                                                                column.key ===
                                                                'keterangan'
                                                                    ? 'min-w-[180px]'
                                                                    : 'whitespace-nowrap'
                                                            }`}
                                                        >
                                                            <CellValue
                                                                columnKey={
                                                                    column.key
                                                                }
                                                                value={
                                                                    asset
                                                                        .values[
                                                                        column
                                                                            .key
                                                                    ] ?? ''
                                                                }
                                                            />
                                                        </td>
                                                    );
                                                }

                                                if (rowIndex > 0) {
                                                    return null;
                                                }

                                                return (
                                                    <td
                                                        key={column.key}
                                                        rowSpan={
                                                            group.rows.length
                                                        }
                                                        className={`${cellDivider} px-4 py-3 text-center align-middle whitespace-nowrap`}
                                                    >
                                                        <CellValue
                                                            columnKey={
                                                                column.key
                                                            }
                                                            value={
                                                                asset.values[
                                                                    column.key
                                                                ] ?? ''
                                                            }
                                                        />
                                                    </td>
                                                );
                                            })}
                                            <AssetActions
                                                asset={asset}
                                                sheet={sheet}
                                                columns={columns}
                                                categories={categories}
                                                formGroups={formGroups}
                                                batteryKeywords={
                                                    batteryKeywords
                                                }
                                                assetPresets={assetPresets}
                                                upsOptions={upsOptions}
                                                multiRow={multiRow}
                                                cameraCategory={cameraCategory}
                                                canManage={canManage}
                                            />
                                        </tr>
                                    )),
                                )
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination
                    assets={tables.device.groups}
                    sheet={sheet}
                    search={filters.search}
                    perPage={filters.per_page}
                    unit="perangkat"
                />
            </section>

            <section>
                <h2 className="mb-2 text-base font-semibold">
                    {tables.camera.label}
                </h2>
                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                {cameraColumns.map((column) => (
                                    <th
                                        key={column.key}
                                        className={`${cellDivider} px-4 py-3 text-center font-medium whitespace-nowrap`}
                                    >
                                        {column.label}
                                    </th>
                                ))}
                                {canManage && (
                                    <th
                                        className={`${cellDivider} px-4 py-3 text-center font-medium`}
                                    >
                                        Aksi
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {tables.camera.groups.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={
                                            cameraColumns.length +
                                            (canManage ? 1 : 0)
                                        }
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        Belum ada data kamera.
                                    </td>
                                </tr>
                            ) : (
                                tables.camera.groups.data.flatMap((group) =>
                                    group.rows.map((asset, rowIndex) => (
                                        <tr
                                            key={asset.id}
                                            className="border-b border-sidebar-border/50 last:border-b-0"
                                        >
                                            {rowIndex === 0 &&
                                                shared.map((key) => (
                                                    <td
                                                        key={key}
                                                        rowSpan={
                                                            group.rows.length
                                                        }
                                                        className={`${cellDivider} px-4 py-3 text-center align-middle whitespace-nowrap`}
                                                    >
                                                        <CellValue
                                                            columnKey={key}
                                                            value={
                                                                group.shared[
                                                                    key
                                                                ] ?? ''
                                                            }
                                                        />
                                                    </td>
                                                ))}
                                            {otherColumns.map((column) => (
                                                <td
                                                    key={column.key}
                                                    className={`${cellDivider} px-4 py-3 text-center ${
                                                        column.key ===
                                                        'keterangan'
                                                            ? 'min-w-[180px]'
                                                            : 'whitespace-nowrap'
                                                    }`}
                                                >
                                                    <CellValue
                                                        columnKey={column.key}
                                                        value={cameraCellValue(
                                                            asset,
                                                            column.key,
                                                        )}
                                                    />
                                                </td>
                                            ))}
                                            <AssetActions
                                                asset={asset}
                                                sheet={sheet}
                                                columns={columns}
                                                categories={categories}
                                                formGroups={formGroups}
                                                batteryKeywords={
                                                    batteryKeywords
                                                }
                                                assetPresets={assetPresets}
                                                upsOptions={upsOptions}
                                                multiRow={multiRow}
                                                cameraCategory={cameraCategory}
                                                canManage={canManage}
                                            />
                                        </tr>
                                    )),
                                )
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination
                    assets={tables.camera.groups}
                    sheet={sheet}
                    search={filters.search}
                    perPage={filters.per_page}
                    showPerPage={false}
                    unit="grup"
                />
            </section>
        </div>
    );
}

function GroupedTable({
    table,
    sheet,
    categories,
    formGroups,
    batteryKeywords,
    assetPresets,
    upsOptions,
    multiRow,
    cameraCategory,
    filters,
    canManage,
}: {
    table: GroupedTable;
    sheet: string;
    categories: CategoryNode[];
    formGroups: FormGroups | null;
    batteryKeywords: string[];
    assetPresets: AssetPreset[];
    upsOptions: UpsOption[];
    multiRow: MultiRow | null;
    cameraCategory: string | null;
    filters: Filters;
    canManage: boolean;
}) {
    return (
        <section>
            <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                <table className="w-full text-left text-sm">
                    <thead>
                        <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                            {table.columns.map((column) => (
                                <th
                                    key={column.key}
                                    className={`${cellDivider} px-4 py-3 text-center font-medium whitespace-nowrap`}
                                >
                                    {column.label}
                                </th>
                            ))}
                            {canManage && (
                                <th
                                    className={`${cellDivider} px-4 py-3 text-center font-medium`}
                                >
                                    Aksi
                                </th>
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {table.groups.data.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={
                                        table.columns.length +
                                        (canManage ? 1 : 0)
                                    }
                                    className="px-4 py-12 text-center text-muted-foreground"
                                >
                                    Belum ada data pada sheet ini.
                                </td>
                            </tr>
                        ) : (
                            table.groups.data.flatMap((group) =>
                                group.rows.map((asset, rowIndex) => (
                                    <tr
                                        key={asset.id}
                                        className="border-b border-sidebar-border/50 last:border-b-0"
                                    >
                                        {table.columns.map((column) => {
                                            const isPerRowIdentity =
                                                table.perRowIdentity?.includes(
                                                    column.key,
                                                ) ?? false;

                                            if (
                                                !table.identity.includes(
                                                    column.key,
                                                ) ||
                                                isPerRowIdentity
                                            ) {
                                                return (
                                                    <td
                                                        key={column.key}
                                                        className={`${cellDivider} px-4 py-3 text-center ${
                                                            column.key ===
                                                            'keterangan'
                                                                ? 'min-w-[180px]'
                                                                : 'whitespace-nowrap'
                                                        }`}
                                                    >
                                                        <CellValue
                                                            columnKey={
                                                                column.key
                                                            }
                                                            value={
                                                                asset.values[
                                                                    column.key
                                                                ] ?? ''
                                                            }
                                                        />
                                                    </td>
                                                );
                                            }

                                            if (rowIndex > 0) {
                                                return null;
                                            }

                                            return (
                                                <td
                                                    key={column.key}
                                                    rowSpan={group.rows.length}
                                                    className={`${cellDivider} px-4 py-3 text-center align-middle whitespace-nowrap`}
                                                >
                                                    <CellValue
                                                        columnKey={column.key}
                                                        value={
                                                            asset.values[
                                                                column.key
                                                            ] ?? ''
                                                        }
                                                    />
                                                </td>
                                            );
                                        })}
                                        <AssetActions
                                            asset={asset}
                                            sheet={sheet}
                                            columns={table.columns}
                                            categories={categories}
                                            formGroups={formGroups}
                                            batteryKeywords={batteryKeywords}
                                            assetPresets={assetPresets}
                                            upsOptions={upsOptions}
                                            multiRow={multiRow}
                                            cameraCategory={cameraCategory}
                                            canManage={canManage}
                                        />
                                    </tr>
                                )),
                            )
                        )}
                    </tbody>
                </table>
            </div>
            <Pagination
                assets={table.groups}
                sheet={sheet}
                search={filters.search}
                perPage={filters.per_page}
                unit="grup"
            />
        </section>
    );
}

export default function AssetList({
    sheets,
    activeSheet,
    columns,
    formGroups,
    batteryKeywords,
    assetPresets,
    upsOptions,
    tables,
    groupedTable,
    assets,
    categories,
    defaultCategory,
    cameraCategory,
    multiRow,
    filters,
    canManage,
}: {
    sheets: SheetOption[];
    activeSheet: string;
    columns: SheetColumn[];
    formGroups: FormGroups | null;
    batteryKeywords: string[];
    assetPresets: AssetPreset[];
    upsOptions: UpsOption[];
    tables: SplitTableSet | null;
    groupedTable: GroupedTable | null;
    assets: PaginatedAssets | null;
    categories: CategoryNode[];
    defaultCategory: string | null;
    cameraCategory: string | null;
    multiRow: MultiRow | null;
    filters: Filters;
    canManage: boolean;
}) {
    useEchoPublic('assets', '.assets.changed', () => {
        router.reload({ only: ['assets', 'tables', 'groupedTable'] });
    });

    return (
        <>
            <Head title="Asset List" />

            <h1 className="sr-only">Asset List</h1>

            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="Asset List"
                    description="Kelola aset sesuai kategori. Kolom mengikuti kategori yang dipilih."
                    className="mb-4"
                />

                <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <AssetSearch
                        sheet={activeSheet}
                        search={filters.search}
                        perPage={filters.per_page}
                    />

                    <div className="flex flex-wrap items-center justify-end gap-2 sm:ml-auto sm:flex-nowrap">
                        <SheetSelector
                            sheets={sheets}
                            activeSheet={activeSheet}
                        />
                        {canManage && (
                            <Button
                                variant="default"
                                className="bg-green-600 text-white hover:bg-green-700"
                                asChild
                            >
                                <a
                                    href={AssetController.exportMethod.url({
                                        query: {
                                            sheet: activeSheet,
                                            search: filters.search || undefined,
                                        },
                                    })}
                                >
                                    <Upload className="size-4" />
                                    Export
                                </a>
                            </Button>
                        )}
                        {canManage && (
                            <CreateAssetDialog
                                key={activeSheet}
                                sheet={activeSheet}
                                columns={columns}
                                categories={categories}
                                defaultCategory={defaultCategory}
                                formGroups={formGroups}
                                batteryKeywords={batteryKeywords}
                                assetPresets={assetPresets}
                                upsOptions={upsOptions}
                                multiRow={multiRow}
                                cameraCategory={cameraCategory}
                            />
                        )}
                    </div>
                </div>

                {tables ? (
                    'device' in tables ? (
                        <CctvTablesView
                            tables={tables}
                            sheet={activeSheet}
                            columns={columns}
                            categories={categories}
                            formGroups={formGroups}
                            batteryKeywords={batteryKeywords}
                            assetPresets={assetPresets}
                            upsOptions={upsOptions}
                            multiRow={multiRow}
                            cameraCategory={cameraCategory}
                            filters={filters}
                            canManage={canManage}
                        />
                    ) : (
                        <SplitTablesView
                            tables={tables}
                            sheet={activeSheet}
                            columns={columns}
                            categories={categories}
                            formGroups={formGroups}
                            batteryKeywords={batteryKeywords}
                            assetPresets={assetPresets}
                            upsOptions={upsOptions}
                            multiRow={multiRow}
                            cameraCategory={cameraCategory}
                            filters={filters}
                            canManage={canManage}
                        />
                    )
                ) : groupedTable ? (
                    <GroupedTable
                        table={groupedTable}
                        sheet={activeSheet}
                        categories={categories}
                        formGroups={formGroups}
                        batteryKeywords={batteryKeywords}
                        assetPresets={assetPresets}
                        upsOptions={upsOptions}
                        multiRow={multiRow}
                        cameraCategory={cameraCategory}
                        filters={filters}
                        canManage={canManage}
                    />
                ) : (
                    assets && (
                        <>
                            <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                                <table className="w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                            {columns.map((column) => (
                                                <th
                                                    key={column.key}
                                                    className={`${cellDivider} px-4 py-3 text-center font-medium whitespace-nowrap`}
                                                >
                                                    {column.label}
                                                </th>
                                            ))}
                                            {canManage && (
                                                <th
                                                    className={`${cellDivider} px-4 py-3 text-center font-medium`}
                                                >
                                                    Aksi
                                                </th>
                                            )}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {assets.data.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={
                                                        columns.length +
                                                        (canManage ? 1 : 0)
                                                    }
                                                    className="px-4 py-12 text-center text-muted-foreground"
                                                >
                                                    Belum ada data pada sheet
                                                    ini.
                                                </td>
                                            </tr>
                                        ) : (
                                            assets.data.map((asset) => (
                                                <tr
                                                    key={asset.id}
                                                    className="border-b border-sidebar-border/50 last:border-b-0"
                                                >
                                                    {columns.map((column) => (
                                                        <td
                                                            key={column.key}
                                                            className={`${cellDivider} px-4 py-3 text-center ${
                                                                column.key ===
                                                                'keterangan'
                                                                    ? 'min-w-[180px]'
                                                                    : 'whitespace-nowrap'
                                                            }`}
                                                        >
                                                            <CellValue
                                                                columnKey={
                                                                    column.key
                                                                }
                                                                value={
                                                                    asset
                                                                        .values[
                                                                        column
                                                                            .key
                                                                    ] ?? ''
                                                                }
                                                            />
                                                        </td>
                                                    ))}
                                                    <AssetActions
                                                        asset={asset}
                                                        sheet={activeSheet}
                                                        columns={columns}
                                                        categories={categories}
                                                        formGroups={formGroups}
                                                        batteryKeywords={
                                                            batteryKeywords
                                                        }
                                                        assetPresets={
                                                            assetPresets
                                                        }
                                                        upsOptions={upsOptions}
                                                        multiRow={multiRow}
                                                        cameraCategory={
                                                            cameraCategory
                                                        }
                                                        canManage={canManage}
                                                    />
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            <Pagination
                                assets={assets}
                                sheet={activeSheet}
                                search={filters.search}
                                perPage={filters.per_page}
                            />
                        </>
                    )
                )}
            </div>
        </>
    );
}

AssetList.layout = (props: {
    menus?: MenuRow[];
    menu?: { name: string; url: string };
    activeChild?: { name: string; url: string };
}) => ({
    breadcrumbs: showChildCrumbs(props) ??
        menuBreadcrumbs(props.menus, AssetListController.index.url()) ?? [
            { title: 'Asset Management', href: assetsOverview() },
            { title: 'Asset List', href: AssetListController.index.url() },
        ],
});

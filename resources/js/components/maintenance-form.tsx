import { Form, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import MaintenanceController from '@/actions/App/Http/Controllers/MaintenanceController';
import InputError from '@/components/input-error';
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

export type AssetOption = {
    id: number;
    name: string;
};

export type MaintenanceRow = {
    id: number;
    maintenance_date: string;
    end_date: string | null;
    status: string;
    result: string | null;
    evidence_photo: string | null;
    document: string | null;
    asset?: { id: number; name: string } | null;
    troubleshoot?: {
        id: number;
        maintenance_id: number | null;
        result: string;
    } | null;
};

export type FormErrors = Partial<Record<string, string | undefined>>;

export const resultOptions = ['Normal', 'Bermasalah'] as const;

/**
 * Manual workflow stages: Done is NEVER picked by hand — it always
 * follows a Normal result (see statusForResult below), so the select
 * only offers Scheduled / On Progress.
 */
export const statusOptions = ['Scheduled', 'On Progress'] as const;

/**
 * The status follows the recorded result: Normal finishes the record
 * (Done), Bermasalah keeps it On Progress until its ticket is closed;
 * without a result the chosen stage applies (a Done request without a
 * result is rejected server-side). Mirrors
 * MaintenanceController::statusForResult().
 */
export const statusForResult = (result: string, requested: string): string => {
    if (result === 'Normal') {
        return 'Done';
    }

    if (result === 'Bermasalah') {
        return 'On Progress';
    }

    return requested === 'Done' ? 'On Progress' : requested;
};

/**
 * Render a Y-m-d value as dd/mm/yyyy (en-GB), the format used across
 * the project (Activity Log, Files). The T00:00:00 suffix pins parsing
 * to local midnight so a date-only string never shifts a day.
 */
export function formatDate(value: string): string {
    return new Date(`${value}T00:00:00`).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

/**
 * Badge colours by result: green for Normal, rose for anything broken
 * (Bermasalah / Masih Bermasalah), slate as the fallback.
 */
export const resultBadgeClassFor = (result: string): string => {
    if (result === 'Normal') {
        return 'border-green-600/50 bg-green-600/10 text-green-600 dark:text-green-400';
    }

    if (result === 'Bermasalah' || result === 'Masih Bermasalah') {
        return 'border-rose-600/50 bg-rose-600/10 text-rose-600 dark:text-rose-400';
    }

    return 'border-slate-600/50 bg-slate-600/10 text-slate-600 dark:text-slate-400';
};

/**
 * Badge colours by workflow status: slate for Scheduled, amber for On
 * Progress, green for Done.
 */
export const statusBadgeClassFor = (status: string): string => {
    if (status === 'Scheduled') {
        return 'border-slate-600/50 bg-slate-600/10 text-slate-600 dark:text-slate-400';
    }

    if (status === 'On Progress') {
        return 'border-amber-600/50 bg-amber-600/10 text-amber-600 dark:text-amber-400';
    }

    if (status === 'Done') {
        return 'border-green-600/50 bg-green-600/10 text-green-600 dark:text-green-400';
    }

    return 'border-slate-600/50 bg-slate-600/10 text-slate-600 dark:text-slate-400';
};

export function MaintenanceFields({
    idPrefix,
    maintenance,
    assetOptions,
    errors,
    lockAsset = false,
    initialAssetId,
}: {
    idPrefix: string;
    maintenance?: MaintenanceRow;
    assetOptions: AssetOption[];
    errors: FormErrors;
    lockAsset?: boolean;
    initialAssetId?: number;
}) {
    const [assetId, setAssetId] = useState(
        initialAssetId != null
            ? String(initialAssetId)
            : maintenance?.asset?.id
              ? String(maintenance.asset.id)
              : '',
    );
    const [status, setStatus] = useState(maintenance?.status ?? 'Scheduled');
    const [result, setResult] = useState(maintenance?.result ?? '');

    // While the ticket is Unresolved only the Troubleshoot page may
    // change the outcome — closing the ticket there turns this result
    // to Normal automatically.
    const ticketUnresolved =
        maintenance?.troubleshoot != null &&
        maintenance.troubleshoot.result !== 'Normal';

    const effectiveStatus = statusForResult(result, status);
    const statusLocked = result !== '';
    const resultLocked =
        ticketUnresolved || effectiveStatus === 'Scheduled';

    const resultHint = () => {
        if (ticketUnresolved) {
            return 'Tutup tiket di Troubleshoot.';
        }

        if (effectiveStatus === 'Scheduled') {
            return 'Diisi setelah pengerjaan selesai.';
        }

        return 'Jika Bermasalah tiket gangguan dibuat otomatis';
    };

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-asset_id`}>Nama Perangkat</Label>
                <Select
                    value={assetId}
                    onValueChange={setAssetId}
                    disabled={lockAsset}
                >
                    <SelectTrigger id={`${idPrefix}-asset_id`} className="w-full">
                        <SelectValue placeholder="Pilih nama perangkat" />
                    </SelectTrigger>
                    <SelectContent>
                        {assetOptions.map((option) => (
                            <SelectItem key={option.id} value={String(option.id)}>
                                {option.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <input type="hidden" name="asset_id" value={assetId} />
                <InputError message={errors.asset_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-maintenance_date`}>
                    Tanggal Mulai Pengerjaan
                </Label>
                <Input
                    id={`${idPrefix}-maintenance_date`}
                    name="maintenance_date"
                    type="date"
                    className="mt-0 block w-full"
                    defaultValue={maintenance?.maintenance_date ?? ''}
                    required
                />
                <InputError message={errors.maintenance_date} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-end_date`}>Tanggal Beres Pengerjaan</Label>
                <Input
                    id={`${idPrefix}-end_date`}
                    name="end_date"
                    type="date"
                    className="mt-0 block w-full"
                    defaultValue={maintenance?.end_date ?? ''}
                />
                <p className="text-muted-foreground text-xs">
                    Diisi saat pengerjaan selesai.
                </p>
                <InputError message={errors.end_date} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-status`}>Status</Label>
                <Select
                    value={effectiveStatus}
                    onValueChange={setStatus}
                    disabled={statusLocked}
                >
                    <SelectTrigger id={`${idPrefix}-status`} className="w-full">
                        <SelectValue placeholder="Pilih status">
                            {effectiveStatus}
                        </SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                        {statusOptions.map((option) => (
                            <SelectItem key={option} value={option}>
                                {option}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <input type="hidden" name="status" value={effectiveStatus} />
                <p className="text-muted-foreground text-xs">
                    {statusLocked
                        ? 'Status mengikuti hasil yang dipilih.'
                        : 'Status akan otomatis menjadi Done jika hasil Normal.'}
                </p>
                <InputError message={errors.status} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-result`}>Hasil</Label>
                <Select
                    value={result}
                    onValueChange={setResult}
                    disabled={resultLocked}
                >
                    <SelectTrigger id={`${idPrefix}-result`} className="w-full">
                        <SelectValue placeholder="Pilih hasil" />
                    </SelectTrigger>
                    <SelectContent>
                        {resultOptions.map((option) => (
                            <SelectItem key={option} value={option}>
                                {option}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <input type="hidden" name="result" value={result} />
                <p className="text-muted-foreground text-xs">
                    {resultHint()}
                </p>
                <InputError message={errors.result} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-evidence_photo`}>Foto Eviden</Label>
                <Input
                    id={`${idPrefix}-evidence_photo`}
                    name="evidence_photo"
                    type="file"
                    accept="image/*"
                    className="mt-0 block w-full"
                />
                <p className="text-muted-foreground text-xs">
                    {maintenance?.evidence_photo
                        ? 'Kosongkan untuk mempertahankan foto saat ini.'
                        : 'Format gambar, maksimal 4 MB.'}
                </p>
                <InputError message={errors.evidence_photo} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-document`}>Upload Dokumen</Label>
                <Input
                    id={`${idPrefix}-document`}
                    name="document"
                    type="file"
                    className="mt-0 block w-full"
                />
                <p className="text-muted-foreground text-xs">
                    {maintenance?.document
                        ? 'Kosongkan untuk mempertahankan dokumen saat ini.'
                        : 'Maksimal 10 MB.'}
                </p>
                <InputError message={errors.document} />
            </div>
        </div>
    );
}

export function CreateMaintenanceDialog({
    assetOptions,
    initialAssetId,
    lockAsset = false,
}: {
    assetOptions: AssetOption[];
    initialAssetId?: number;
    lockAsset?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [formKey, setFormKey] = useState(0);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus className="size-4" />
                    Tambah Maintenance
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogTitle>Create maintenance</DialogTitle>
                <DialogDescription>
                    Record an asset maintenance. If the result is
                    &quot;Bermasalah&quot;, a trouble ticket will be created on
                    the Troubleshoot page automatically — it will be closed
                    from there, which turns this record back to Normal.
                </DialogDescription>

                <Form
                    {...MaintenanceController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        setFormKey((key) => key + 1);
                        router.reload({ only: ['maintenances'] });
                    }}
                    resetOnSuccess
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <MaintenanceFields
                                key={formKey}
                                idPrefix="create"
                                assetOptions={assetOptions}
                                errors={errors as FormErrors}
                                initialAssetId={initialAssetId}
                                lockAsset={lockAsset}
                            />

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

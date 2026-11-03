import { Form, Head, router } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import { Plus, Settings2, Trash2 } from 'lucide-react';
import { useState } from 'react';
import PanelPowerController from '@/actions/App/Http/Controllers/PanelPowerController';
import Heading from '@/components/heading';
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
import { menuBreadcrumbs, showChildCrumbs } from '@/lib/menu-breadcrumbs';
import type { MenuRow } from '@/lib/menu-breadcrumbs';
import { panelpowerlist } from '@/routes/assets';

type PanelMcb = {
    id: number;
    asset_id: number;
    mcb_number: string;
};

type Panel = {
    id: number;
    name: string;
    floor: string;
    room: string;
    capacity: string;
    panel_mcbs: PanelMcb[];
};

const tableHeaders = [
    'No',
    'Nama Panel',
    'Lantai',
    'Ruang',
    'Kapasitas',
    'Jumlah MCB',
    'Aksi',
] as const;

function ManageMcbsDialog({ panel }: { panel: Panel }) {
    const [open, setOpen] = useState(false);
    const [formKey, setFormKey] = useState(0);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Settings2 className="size-4" />
                    Kelola MCB
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogTitle>Kelola MCB — {panel.name}</DialogTitle>
                <DialogDescription>
                    Setiap panel punya nomor MCB sendiri; nomor harus unik di
                    dalam panel. Tambah atau hapus MCB di sini.
                </DialogDescription>

                <Form
                    {...PanelPowerController.storeMcb.form(panel.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setFormKey((key) => key + 1)}
                    resetOnSuccess
                    className="space-y-2"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="flex gap-2">
                                <Input
                                    key={formKey}
                                    name="mcb_number"
                                    placeholder="Mis. MCB-01"
                                    className="flex-1"
                                    autoComplete="off"
                                    required
                                />
                                <Button type="submit" disabled={processing}>
                                    <Plus className="size-4" />
                                    Tambah
                                </Button>
                            </div>
                            <InputError message={errors.mcb_number} />
                        </>
                    )}
                </Form>

                <div className="border-sidebar-border/70 border-t pt-4">
                    <Form
                        {...PanelPowerController.storeMcbs.form(panel.id)}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="space-y-2"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="flex gap-2">
                                    <Input
                                        key={`batch-${formKey}`}
                                        name="count"
                                        type="number"
                                        min={1}
                                        max={50}
                                        placeholder="Jumlah"
                                        className="flex-1"
                                        autoComplete="off"
                                        required
                                    />
                                    <Button
                                        type="submit"
                                        variant="secondary"
                                        disabled={processing}
                                    >
                                        <Plus className="size-4" />
                                        Tambah Banyak
                                    </Button>
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    Nomor otomatis lanjutan dari MCB tertinggi
                                    (MCB-NN).
                                </p>
                                <InputError message={errors.count} />
                            </>
                        )}
                    </Form>
                </div>

                <div className="max-h-64 space-y-2 overflow-y-auto">
                    {panel.panel_mcbs.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            Belum ada MCB di panel ini.
                        </p>
                    ) : (
                        panel.panel_mcbs.map((mcb) => (
                            <div
                                key={mcb.id}
                                className="flex items-center justify-between rounded-md border border-sidebar-border/70 px-3 py-2"
                            >
                                <span className="text-sm">{mcb.mcb_number}</span>
                                <Form
                                    {...PanelPowerController.destroyMcb.form({
                                        asset: panel.id,
                                        panelMcb: mcb.id,
                                    })}
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="ghost"
                                            size="sm"
                                            disabled={processing}
                                            aria-label={`Hapus MCB ${mcb.mcb_number}`}
                                        >
                                            <Trash2 className="text-destructive size-4" />
                                        </Button>
                                    )}
                                </Form>
                            </div>
                        ))
                    )}
                </div>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Tutup</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function PanelPowerIndex({
    panels,
    canManage,
}: {
    panels: Panel[];
    canManage: boolean;
}) {
    useEchoPublic('assets', '.assets.changed', () => {
        router.reload();
    });

    return (
        <>
            <Head title="Panel Power List" />

            <h1 className="sr-only">Panel Power List</h1>

            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="Panel Power List"
                    description="Manage the MCB slots of each PDU panel. Add or edit panels in Asset Management."
                />

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                {tableHeaders.map((header) => (
                                    <th
                                        key={header}
                                        className={`px-4 py-3 text-center font-medium ${
                                            header === 'No' ? 'w-14' : ''
                                        }`}
                                    >
                                        {header}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {panels.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={tableHeaders.length}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        Belum ada aset panel. Tambahkan aset
                                        dengan kategori Panel Power di
                                        Asset Management.
                                    </td>
                                </tr>
                            ) : (
                                panels.map((panel, index) => (
                                    <tr
                                        key={panel.id}
                                        className="border-b border-sidebar-border/50 last:border-b-0"
                                    >
                                        <td className="px-4 py-3 text-center text-muted-foreground">
                                            {index + 1}
                                        </td>
                                        <td className="px-4 py-3 text-center font-medium">
                                            {panel.name}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {panel.floor}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {panel.room}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {panel.capacity}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {panel.panel_mcbs.length}
                                        </td>
                                        <td className="px-4 py-3">
                                            {canManage && (
                                                <div className="flex items-center justify-center gap-2">
                                                    <ManageMcbsDialog
                                                        panel={panel}
                                                    />
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

PanelPowerIndex.layout = (props: {
    menus?: MenuRow[];
    menu?: { name: string; url: string };
    activeChild?: { name: string; url: string };
}) => ({
    breadcrumbs:
        showChildCrumbs(props) ??
        menuBreadcrumbs(props.menus, panelpowerlist.url()) ?? [
            { title: 'Asset Management', href: panelpowerlist() },
        ],
});

export type AssetDescriptionSource = {
    brand: string;
    model: string;
    serial_number: string;
    capacity: string;
    manufacture_year: string;
    operation_year: string;
    quantity: string;
    floor: string;
    room: string;
};

/**
 * Compose the device description from the asset's own attributes so the
 * column always mirrors the asset form; empty parts are dropped and a
 * fully empty asset falls back to "-".
 */
export function assetDescription(asset: AssetDescriptionSource): string {
    return (
        [
            [asset.brand, asset.model].filter(Boolean).join(' '),
            asset.serial_number ? `SN ${asset.serial_number}` : '',
            asset.capacity ? `Kapasitas ${asset.capacity}` : '',
            asset.manufacture_year
                ? `Manufaktur ${asset.manufacture_year}`
                : '',
            asset.operation_year ? `Operasi ${asset.operation_year}` : '',
            asset.quantity ? `Jumlah ${asset.quantity}` : '',
            asset.floor ? `Lt ${asset.floor}` : '',
            asset.room ? `Ruang ${asset.room}` : '',
        ]
            .filter(Boolean)
            .join(' — ') || '-'
    );
}

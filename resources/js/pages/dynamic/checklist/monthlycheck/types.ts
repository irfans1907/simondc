export type PowerPdu = {
    id: number;
    floor: number;
    code: string;
    name: string;
    rows: PowerLoadRow[];
};

export type PowerLoadRow = {
    id: number;
    customer: string | null;
    rack: string | null;
    rack_type: string | null;
    mcb: string | null;
    capacity_amp: number | null;
    phase: string | null;
    service: string | null;
    sort_order: number;
    readings_by_month: Record<number, number | null>;
};

export type PowerPanel = {
    id: number;
    panel_group: string;
    name: string;
    capacity: number | null;
    unit: string | null;
    note: string | null;
    sort_order: number;
};

export type PowerPanelReading = {
    amp_r: number | null;
    amp_s: number | null;
    amp_t: number | null;
    note: string | null;
};

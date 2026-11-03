export type ActivityLogEntry = {
    id: number;
    log_name: string | null;
    description: string;
    event: string | null;
    causer: { id: number; name: string; email: string } | null;
    created_at: string;
    created_at_diff?: string;
    [key: string]: unknown;
};
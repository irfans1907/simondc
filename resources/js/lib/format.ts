/**
 * Display helper for table cells and inline values.
 *
 * Empty values and dash-like strings (`-`, `–`, `—`) all render as the short
 * hyphen so placeholder dashes stay visually uniform across the application.
 */
export function displayValue(
    value: string | null | undefined,
    fallback = '-',
): string {
    const trimmed = (value ?? '').trim();

    if (
        trimmed === '' ||
        trimmed === '-' ||
        trimmed === '–' ||
        trimmed === '—'
    ) {
        return fallback;
    }

    return value as string;
}

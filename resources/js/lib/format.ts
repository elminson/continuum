const compact = new Intl.NumberFormat('en-US', {
    notation: 'compact',
    maximumFractionDigits: 1,
});
const full = new Intl.NumberFormat('en-US');

export function formatNumber(
    value: number | null | undefined,
    digits = 0,
): string {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return '—';
    }

    return new Intl.NumberFormat('en-US', {
        maximumFractionDigits: digits,
        minimumFractionDigits: 0,
    }).format(value);
}

export function formatCompact(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return '—';
    }

    return Math.abs(value) >= 10000
        ? compact.format(value)
        : full.format(value);
}

export function formatPercent(
    value: number | null | undefined,
    digits = 0,
): string {
    if (value === null || value === undefined) {
        return '—';
    }

    return `${(value * 100).toFixed(digits)}%`;
}

export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const [year, month, day] = value.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day)).toLocaleDateString(
        'en-US',
        { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'UTC' },
    );
}

export function formatPeriod(value: string, period: string): string {
    if (period === 'month') {
        const [year, month] = value.split('-').map(Number);

        return new Date(Date.UTC(year, month - 1, 1)).toLocaleDateString(
            'en-US',
            { year: 'numeric', month: 'short', timeZone: 'UTC' },
        );
    }

    if (period === 'week') {
        return `Week of ${formatDate(value)}`;
    }

    return formatDate(value);
}

export const INSTRUMENT_COLORS = {
    modis: '#38bdf8',
    viirs: '#f97316',
    harmonized: '#a3e635',
    naive: '#f43f5e',
} as const;

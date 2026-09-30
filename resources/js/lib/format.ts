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

export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'UTC',
        timeZoneName: 'short',
    });
}

export function formatRelative(
    value: string | null | undefined,
    now: Date = new Date(),
): string {
    if (!value) {
        return '—';
    }

    const diff = (new Date(value).getTime() - now.getTime()) / 1000;
    const abs = Math.abs(diff);
    const units: [number, Intl.RelativeTimeFormatUnit][] = [
        [60, 'second'],
        [3600, 'minute'],
        [86400, 'hour'],
        [604800, 'day'],
        [2629800, 'week'],
        [31557600, 'month'],
    ];

    if (abs < 45) {
        return 'just now';
    }

    const formatter = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

    for (let index = 0; index < units.length; index++) {
        const [limit, unit] = units[index];

        if (abs < limit) {
            const divisor = index === 0 ? 1 : units[index - 1][0];

            return formatter.format(Math.round(diff / divisor), unit);
        }
    }

    return formatter.format(Math.round(diff / 31557600), 'year');
}

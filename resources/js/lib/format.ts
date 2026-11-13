import { intlLocale, t } from '@/lib/i18n';

export function formatNumber(
    value: number | null | undefined,
    digits = 0,
): string {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return '—';
    }

    return new Intl.NumberFormat(intlLocale(), {
        maximumFractionDigits: digits,
        minimumFractionDigits: 0,
    }).format(value);
}

export function formatCompact(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return '—';
    }

    return Math.abs(value) >= 10000
        ? new Intl.NumberFormat(intlLocale(), {
              notation: 'compact',
              maximumFractionDigits: 1,
          }).format(value)
        : new Intl.NumberFormat(intlLocale()).format(value);
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
        intlLocale(),
        { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'UTC' },
    );
}

export function formatPeriod(value: string, period: string): string {
    if (period === 'month') {
        const [year, month] = value.split('-').map(Number);

        return new Date(Date.UTC(year, month - 1, 1)).toLocaleDateString(
            intlLocale(),
            { year: 'numeric', month: 'short', timeZone: 'UTC' },
        );
    }

    if (period === 'week') {
        return t('Week of :date', { date: formatDate(value) });
    }

    return formatDate(value);
}

export const INSTRUMENT_COLORS = {
    modis: '#3b82f6',
    viirs: '#f97316',
    harmonized: '#4ade80',
    naive: '#cc79a7',
} as const;

export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(intlLocale(), {
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
        return t('just now');
    }

    const formatter = new Intl.RelativeTimeFormat(intlLocale(), {
        numeric: 'auto',
    });

    for (let index = 0; index < units.length; index++) {
        const [limit, unit] = units[index];

        if (abs < limit) {
            const divisor = index === 0 ? 1 : units[index - 1][0];

            return formatter.format(Math.round(diff / divisor), unit);
        }
    }

    return formatter.format(Math.round(diff / 31557600), 'year');
}

<script setup lang="ts">
import { computed } from 'vue';
import VChart from 'vue-echarts';
import { useChartTheme } from '@/lib/charts';
import { formatNumber, formatPeriod } from '@/lib/format';
import type { SeriesPoint } from '@/types/continuum';

const props = withDefaults(
    defineProps<{
        points: SeriesPoint[];
        period: string;
        metric?: 'harmonized' | 'hotspots';
    }>(),
    { metric: 'harmonized' },
);

// Sequential ramps whose lightness rises monotonically, so they stay readable
// for every colour-vision deficiency: ColorBrewer YlOrRd on light, inferno on dark.
const RAMP_LIGHT = ['#ffffcc', '#fed976', '#fd8d3c', '#e31a1c', '#800026'];
const RAMP_DARK = [
    '#1b0c41',
    '#4a0c6b',
    '#781c6d',
    '#a52c60',
    '#cf4446',
    '#ed6925',
    '#fb9b06',
    '#f7d13d',
    '#fcffa4',
];

function valueOf(point: SeriesPoint): number | null {
    if (props.metric === 'hotspots') {
        return point.modis || point.viirs
            ? (point.modis?.detections ?? 0) + (point.viirs?.detections ?? 0)
            : null;
    }

    return point.harmonized;
}

const theme = useChartTheme();

const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

function isoWeek(date: Date): number {
    const d = new Date(
        Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate()),
    );
    const day = d.getUTCDay() || 7;
    d.setUTCDate(d.getUTCDate() + 4 - day);
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));

    return Math.min(
        52,
        Math.ceil(((d.getTime() - yearStart.getTime()) / 86400000 + 1) / 7),
    );
}

const layout = computed(() => {
    const rows: string[] = [];
    const rowIndex = new Map<string, number>();
    const data: {
        value: [number, number, number | null];
        itemStyle?: Record<string, unknown>;
    }[] = [];
    let columns: string[] = [];

    if (props.period === 'month') {
        columns = MONTHS;
    } else if (props.period === 'week') {
        columns = Array.from(
            { length: 52 },
            (_, i) => `W${String(i + 1).padStart(2, '0')}`,
        );
    } else {
        columns = Array.from({ length: 31 }, (_, i) => String(i + 1));
    }

    for (const point of props.points) {
        const [y, m, d] = point.period_start.split('-').map(Number);
        const rowKey =
            props.period === 'day' ? point.period_start.slice(0, 7) : String(y);

        if (!rowIndex.has(rowKey)) {
            rowIndex.set(rowKey, rows.length);
            rows.push(rowKey);
        }

        const column =
            props.period === 'month'
                ? m - 1
                : props.period === 'week'
                  ? isoWeek(new Date(Date.UTC(y, m - 1, d))) - 1
                  : d - 1;

        data.push({
            value: [column, rowIndex.get(rowKey) as number, valueOf(point)],
            itemStyle:
                props.metric === 'harmonized' && point.estimated
                    ? {
                          borderColor: theme.isDark.value
                              ? '#fafafa'
                              : '#18181b',
                          borderWidth: 1,
                          borderType: 'dashed',
                      }
                    : undefined,
        });
    }

    return { rows, columns, data };
});

const option = computed(() => {
    const values = layout.value.data
        .map((d) => d.value[2])
        .filter((v): v is number => v !== null);
    const max = values.length > 0 ? Math.max(...values, 1) : 1;

    return {
        animation: false,
        textStyle: { color: theme.text.value },
        tooltip: {
            ...theme.tooltip.value,
            formatter: (params: { dataIndex: number }) => {
                const point = props.points[params.dataIndex];

                const hotspots = `MODIS ${formatNumber(point.modis?.detections)} · VIIRS ${formatNumber(point.viirs?.detections)} hotspots`;

                return `<strong>${formatPeriod(point.period_start, props.period)}</strong><br>${formatNumber(point.harmonized)} harmonized fire cell-days${point.estimated ? ' <em>(estimated from ' + point.source?.toUpperCase() + ')</em>' : ''}<br>${hotspots}`;
            },
        },
        grid: { left: 64, right: 16, top: 10, bottom: 84 },
        xAxis: {
            type: 'category',
            data: layout.value.columns,
            splitArea: { show: false },
            axisLabel: {
                color: theme.muted.value,
                interval: props.period === 'week' ? 3 : 0,
            },
        },
        yAxis: {
            type: 'category',
            data: layout.value.rows,
            inverse: true,
            axisLabel: { color: theme.muted.value },
        },
        visualMap: {
            min: 0,
            max,
            calculable: true,
            orient: 'horizontal',
            left: 'center',
            bottom: 0,
            textStyle: { color: theme.muted.value },
            inRange: {
                color: theme.isDark.value ? RAMP_DARK : RAMP_LIGHT,
            },
        },
        series: [
            {
                type: 'heatmap',
                data: layout.value.data,
                emphasis: {
                    itemStyle: {
                        shadowBlur: 8,
                        shadowColor: 'rgba(0,0,0,0.5)',
                    },
                },
            },
        ],
    };
});

const height = computed(
    () => `${Math.max(180, 70 + layout.value.rows.length * 22)}px`,
);
</script>

<template>
    <div>
        <VChart
            :option="option"
            autoresize
            class="w-full"
            :style="{ height }"
        />
        <p class="mt-1 text-[11px] text-muted-foreground">
            Rows are {{ period === 'day' ? 'months' : 'years' }}.
            <template v-if="metric === 'harmonized'"
                >Dashed cells are estimated from the other sensor.</template
            >
            <template v-else
                >Raw MODIS + VIIRS pixels: VIIRS reports several per MODIS
                pixel, so counts jump in 2012.</template
            >
        </p>
    </div>
</template>

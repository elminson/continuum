<script setup lang="ts">
import { computed } from 'vue';
import VChart from 'vue-echarts';
import { useChartTheme } from '@/lib/charts';
import { INSTRUMENT_COLORS, formatNumber, formatPeriod } from '@/lib/format';
import type { SeriesPoint } from '@/types/continuum';

const props = withDefaults(
    defineProps<{
        points: SeriesPoint[];
        period: string;
        reference: string;
        showNaive?: boolean;
        showNative?: boolean;
        metric?: 'cell_days' | 'frp_sum';
    }>(),
    { showNaive: false, showNative: true, metric: 'cell_days' },
);

const theme = useChartTheme();

const option = computed(() => {
    const x = props.points.map((point) => point.period_start);
    const harmonized = props.points.map((point) => point.harmonized);
    const low = props.points.map((point) => point.low);
    const bandHeight = props.points.map((point) =>
        point.low === null || point.high === null
            ? null
            : point.high - point.low,
    );
    const estimatedMarks = props.points
        .map((point, index) => ({ point, index }))
        .filter(({ point }) => point.estimated)
        .map(({ index }) => index);

    const series: Record<string, unknown>[] = [
        {
            name: 'CI (low)',
            type: 'line',
            data: low,
            stack: 'ci',
            lineStyle: { opacity: 0 },
            symbol: 'none',
            silent: true,
            tooltip: { show: false },
        },
        {
            name: '95% interval',
            type: 'line',
            data: bandHeight,
            stack: 'ci',
            lineStyle: { opacity: 0 },
            areaStyle: { color: INSTRUMENT_COLORS.harmonized, opacity: 0.18 },
            symbol: 'none',
            silent: true,
            tooltip: { show: false },
        },
        {
            name: `Harmonized (${props.reference.toUpperCase()}-equivalent)`,
            type: 'line',
            data: harmonized,
            color: INSTRUMENT_COLORS.harmonized,
            symbol: 'circle',
            symbolSize: 4,
            showSymbol: props.points.length < 120,
            lineStyle: { width: 2.5 },
            markArea:
                estimatedMarks.length > 0
                    ? {
                          silent: true,
                          itemStyle: {
                              color: INSTRUMENT_COLORS.harmonized,
                              opacity: 0.05,
                          },
                          data: [
                              [
                                  { xAxis: x[estimatedMarks[0]] },
                                  {
                                      xAxis: x[
                                          estimatedMarks[
                                              estimatedMarks.length - 1
                                          ]
                                      ],
                                  },
                              ],
                          ],
                      }
                    : undefined,
        },
    ];

    if (props.showNative) {
        series.push(
            {
                name: 'MODIS native',
                type: 'line',
                data: props.points.map((point) =>
                    props.metric === 'frp_sum'
                        ? (point.modis?.frp_sum ?? null)
                        : (point.modis?.cell_days ?? null),
                ),
                color: INSTRUMENT_COLORS.modis,
                symbol: 'none',
                lineStyle: { width: 1.2, type: 'dashed' },
            },
            {
                name: 'VIIRS native',
                type: 'line',
                data: props.points.map((point) =>
                    props.metric === 'frp_sum'
                        ? (point.viirs?.frp_sum ?? null)
                        : (point.viirs?.cell_days ?? null),
                ),
                color: INSTRUMENT_COLORS.viirs,
                symbol: 'none',
                lineStyle: { width: 1.2, type: 'dashed' },
            },
        );
    }

    if (props.showNaive) {
        series.push({
            name: 'Naive merge (what the raw archive says)',
            type: 'line',
            data: props.points.map((point) => point.naive),
            color: INSTRUMENT_COLORS.naive,
            symbol: 'none',
            lineStyle: { width: 2 },
            z: 5,
        });
    }

    return {
        animation: false,
        textStyle: { color: theme.text.value },
        grid: {
            left: 56,
            right: 16,
            top: 40,
            bottom: props.points.length > 60 ? 56 : 32,
        },
        legend: {
            top: 0,
            textStyle: { color: theme.text.value },
            data: series
                .map((s) => s.name as string)
                .filter((n) => !n.startsWith('CI') && n !== '95% interval'),
        },
        tooltip: {
            trigger: 'axis',
            ...theme.tooltip.value,
            formatter: (params: { dataIndex: number }[]) => {
                const point = props.points[params[0].dataIndex];
                const rows = [
                    `<strong>${formatPeriod(point.period_start, props.period)}</strong>`,
                    `Harmonized: <b>${formatNumber(point.harmonized, 0)}</b> cell-days${point.estimated ? ` <em>(estimated from ${point.source?.toUpperCase()}, ×${point.factor?.toFixed(3)})</em>` : ''}`,
                ];

                if (
                    point.low !== null &&
                    point.high !== null &&
                    point.estimated
                ) {
                    rows.push(
                        `95% interval: ${formatNumber(point.low, 0)} – ${formatNumber(point.high, 0)}`,
                    );
                }

                rows.push(
                    `MODIS: ${point.modis ? `${formatNumber(point.modis.cell_days)} cell-days · ${formatNumber(point.modis.detections)} px · ${formatNumber(point.modis.frp_sum)} MW` : 'no coverage'}`,
                );
                rows.push(
                    `VIIRS: ${point.viirs ? `${formatNumber(point.viirs.cell_days)} cell-days · ${formatNumber(point.viirs.detections)} px · ${formatNumber(point.viirs.frp_sum)} MW` : 'no coverage'}`,
                );

                if (props.showNaive) {
                    rows.push(`Naive merge: ${formatNumber(point.naive)}`);
                }

                return rows.join('<br>');
            },
        },
        xAxis: {
            type: 'category',
            data: x,
            axisLabel: {
                color: theme.muted.value,
                formatter: (value: string) =>
                    props.period === 'month' ? value.slice(0, 7) : value,
            },
            axisLine: { lineStyle: { color: theme.grid.value } },
        },
        yAxis: {
            type: 'value',
            name: props.metric === 'frp_sum' ? 'MW' : 'active 1 km cell-days',
            nameTextStyle: {
                color: theme.muted.value,
                align: 'left',
                padding: [0, 0, 6, -48],
            },
            axisLabel: { color: theme.muted.value },
            splitLine: { lineStyle: { color: theme.grid.value } },
        },
        dataZoom:
            props.points.length > 60
                ? [
                      {
                          type: 'slider',
                          height: 18,
                          bottom: 8,
                          borderColor: theme.grid.value,
                      },
                  ]
                : undefined,
        series,
    };
});
</script>

<template>
    <VChart
        :option="option"
        autoresize
        class="w-full"
        :style="{ height: '320px' }"
    />
</template>

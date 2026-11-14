<script setup lang="ts">
import { computed } from 'vue';
import VChart from 'vue-echarts';
import { useChartTheme } from '@/lib/charts';
import { INSTRUMENT_COLORS, formatNumber } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { RetirementPoint } from '@/types/continuum';

const props = defineProps<{ points: RetirementPoint[] }>();

const theme = useChartTheme();

const option = computed(() => {
    const x = props.points.map((p) => p.period_start.slice(0, 7));
    const first = props.points.findIndex((p) => p.retired);

    return {
        animation: false,
        textStyle: { color: theme.text.value },
        grid: { left: 56, right: 16, top: 40, bottom: 56 },
        legend: {
            top: 0,
            textStyle: { color: theme.text.value },
            data: [t('MODIS (real)'), t('VIIRS only → MODIS scale')],
        },
        tooltip: {
            trigger: 'axis',
            ...theme.tooltip.value,
            formatter: (params: { dataIndex: number }[]) => {
                const p = props.points[params[0].dataIndex];
                const rows = [
                    `<strong>${p.period_start.slice(0, 7)}</strong>`,
                    `${t('MODIS (real)')}: <b>${formatNumber(p.modis)}</b> ${t('cell-days')}`,
                ];

                if (p.estimate !== null) {
                    rows.push(
                        `${t('VIIRS only → MODIS scale')}: <b>${formatNumber(p.estimate)}</b> (${formatNumber(p.low)} – ${formatNumber(p.high)})`,
                        `${t('VIIRS native')}: ${formatNumber(p.viirs)}`,
                    );
                }

                return rows.join('<br>');
            },
        },
        xAxis: {
            type: 'category',
            data: x,
            axisLabel: { color: theme.muted.value },
            axisLine: { lineStyle: { color: theme.grid.value } },
        },
        yAxis: {
            type: 'value',
            name: t('cell-days / month'),
            axisLabel: {
                color: theme.muted.value,
                formatter: (value: number) => formatNumber(value),
            },
            splitLine: { lineStyle: { color: theme.grid.value } },
        },
        dataZoom: [
            { type: 'inside' },
            { type: 'slider', height: 18, bottom: 8 },
        ],
        series: [
            {
                name: 'low',
                type: 'line',
                data: props.points.map((p) => p.low),
                stack: 'ci',
                lineStyle: { opacity: 0 },
                symbol: 'none',
                silent: true,
            },
            {
                name: 'band',
                type: 'line',
                data: props.points.map((p) =>
                    p.low === null || p.high === null ? null : p.high - p.low,
                ),
                stack: 'ci',
                lineStyle: { opacity: 0 },
                areaStyle: {
                    color: INSTRUMENT_COLORS.harmonized,
                    opacity: 0.2,
                },
                symbol: 'none',
                silent: true,
            },
            {
                name: t('MODIS (real)'),
                type: 'line',
                data: props.points.map((p) => p.modis),
                color: INSTRUMENT_COLORS.modis,
                symbol: 'none',
                lineStyle: { width: 2 },
                markLine:
                    first >= 0
                        ? {
                              silent: true,
                              symbol: 'none',
                              lineStyle: {
                                  color: theme.muted.value,
                                  type: 'dashed',
                              },
                              label: {
                                  formatter: t('MODIS off (simulated)'),
                                  color: theme.text.value,
                              },
                              data: [{ xAxis: x[first] }],
                          }
                        : undefined,
            },
            {
                name: t('VIIRS only → MODIS scale'),
                type: 'line',
                data: props.points.map((p) => p.estimate),
                color: INSTRUMENT_COLORS.harmonized,
                symbol: 'none',
                lineStyle: { width: 2.5 },
            },
        ],
    };
});
</script>

<template>
    <VChart
        :option="option"
        autoresize
        class="w-full"
        :style="{ height: '340px' }"
    />
</template>

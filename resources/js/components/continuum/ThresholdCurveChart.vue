<script setup lang="ts">
import { computed } from 'vue';
import VChart from 'vue-echarts';
import { useChartTheme } from '@/lib/charts';
import { INSTRUMENT_COLORS, formatNumber } from '@/lib/format';
import type { ThresholdPoint } from '@/types/continuum';

const props = defineProps<{ curve: ThresholdPoint[]; chosen: number | null }>();

const theme = useChartTheme();

const option = computed(() => ({
    animation: false,
    textStyle: { color: theme.text.value },
    grid: { left: 64, right: 24, top: 56, bottom: 48 },
    legend: { top: 0, textStyle: { color: theme.text.value } },
    tooltip: {
        trigger: 'axis',
        ...theme.tooltip.value,
        formatter: (params: { dataIndex: number }[]) => {
            const point = props.curve[params[0].dataIndex];

            return `<strong>τ ≥ ${point.tau} MW</strong><br>MAE ${formatNumber(point.mae, 1)} · bias ${formatNumber(point.bias, 1)}<br>Σ predicted ${formatNumber(point.predicted)} vs Σ MODIS ${formatNumber(point.observed)}`;
        },
    },
    xAxis: {
        type: 'category',
        data: props.curve.map((p) => p.tau),
        name: 'FRP visibility threshold τ (MW, cell-day ΣFRP)',
        nameLocation: 'middle',
        nameGap: 28,
        axisLabel: { color: theme.muted.value },
    },
    yAxis: {
        type: 'value',
        name: 'daily error (cell-days)',
        nameTextStyle: { color: theme.muted.value, align: 'left' },
        axisLabel: { color: theme.muted.value },
        splitLine: { lineStyle: { color: theme.grid.value } },
    },
    series: [
        {
            name: 'MAE vs MODIS',
            type: 'line',
            data: props.curve.map((p) => p.mae),
            color: INSTRUMENT_COLORS.harmonized,
            symbol: 'circle',
            lineStyle: { width: 2.5 },
            markLine:
                props.chosen !== null
                    ? {
                          silent: true,
                          symbol: 'none',
                          lineStyle: {
                              color: INSTRUMENT_COLORS.modis,
                              type: 'dashed',
                          },
                          data: [
                              {
                                  xAxis: String(props.chosen),
                                  label: {
                                      formatter: `chosen τ = ${props.chosen} MW`,
                                      color: theme.text.value,
                                  },
                              },
                          ],
                      }
                    : undefined,
        },
        {
            name: 'Bias (predicted − MODIS)',
            type: 'line',
            data: props.curve.map((p) => p.bias),
            color: INSTRUMENT_COLORS.viirs,
            symbol: 'none',
            lineStyle: { width: 1.5, type: 'dashed' },
        },
    ],
}));
</script>

<template>
    <VChart
        :option="option"
        autoresize
        class="w-full"
        :style="{ height: '288px' }"
    />
</template>

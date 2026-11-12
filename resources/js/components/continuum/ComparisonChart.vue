<script setup lang="ts">
import { computed } from 'vue';
import VChart from 'vue-echarts';
import { useChartTheme } from '@/lib/charts';
import { INSTRUMENT_COLORS, formatNumber } from '@/lib/format';
import type { PeriodComparison } from '@/types/continuum';

const props = defineProps<{ comparison: PeriodComparison }>();

const theme = useChartTheme();

const option = computed(() => {
    const current = Number(props.comparison.to.slice(0, 4));
    const bars = [
        ...props.comparison.history.map((h) => ({
            year: h.year,
            value: h.value,
            current: false,
        })),
        { year: current, value: props.comparison.current, current: true },
    ];

    return {
        animation: false,
        textStyle: { color: theme.text.value },
        grid: { left: 48, right: 16, top: 16, bottom: 28 },
        tooltip: {
            ...theme.tooltip.value,
            trigger: 'axis',
            formatter: (params: { dataIndex: number }[]) => {
                const bar = bars[params[0].dataIndex];

                return `<strong>${bar.year}</strong>${bar.current ? ' (selected)' : ''}<br>${formatNumber(bar.value)} fire cell-days`;
            },
        },
        xAxis: {
            type: 'category',
            data: bars.map((b) => String(b.year)),
            axisLabel: { color: theme.muted.value },
            axisLine: { lineStyle: { color: theme.grid.value } },
        },
        yAxis: {
            type: 'value',
            axisLabel: { color: theme.muted.value },
            splitLine: { lineStyle: { color: theme.grid.value } },
        },
        series: [
            {
                type: 'bar',
                data: bars.map((b) => ({
                    value: b.value,
                    itemStyle: {
                        color: b.current
                            ? INSTRUMENT_COLORS.viirs
                            : theme.isDark.value
                              ? '#52525b'
                              : '#d4d4d8',
                    },
                })),
                markLine:
                    props.comparison.median === null
                        ? undefined
                        : {
                              silent: true,
                              symbol: 'none',
                              lineStyle: {
                                  color: theme.text.value,
                                  type: 'dashed',
                              },
                              label: {
                                  color: theme.text.value,
                                  formatter: 'median',
                                  position: 'insideEndTop',
                              },
                              data: [{ yAxis: props.comparison.median }],
                          },
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
        :style="{ height: '220px' }"
    />
</template>

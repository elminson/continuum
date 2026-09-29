<script setup lang="ts">
import { computed } from 'vue';
import VChart from 'vue-echarts';
import { useChartTheme } from '@/lib/charts';
import { INSTRUMENT_COLORS, formatNumber } from '@/lib/format';
import type { OverlapPair } from '@/types/continuum';

const props = defineProps<{ pairs: OverlapPair[]; ratio: number | null }>();

const theme = useChartTheme();

const option = computed(() => {
    const max = Math.max(
        1,
        ...props.pairs.map((p) => Math.max(p.modis, p.viirs)),
    );

    return {
        animation: false,
        textStyle: { color: theme.text.value },
        grid: { left: 64, right: 24, top: 32, bottom: 48 },
        legend: { top: 0, textStyle: { color: theme.text.value } },
        tooltip: {
            ...theme.tooltip.value,
            formatter: (params: { data: [number, number, string] }) =>
                `<strong>${params.data[2]}</strong><br>VIIRS ${formatNumber(params.data[0])} · MODIS ${formatNumber(params.data[1])} cell-days`,
        },
        xAxis: {
            type: 'value',
            name: 'VIIRS active 1 km cell-days',
            nameLocation: 'middle',
            nameGap: 28,
            max,
            axisLabel: { color: theme.muted.value },
            splitLine: { lineStyle: { color: theme.grid.value } },
        },
        yAxis: {
            type: 'value',
            name: 'MODIS active 1 km cell-days',
            nameLocation: 'middle',
            nameGap: 48,
            max,
            axisLabel: { color: theme.muted.value },
            splitLine: { lineStyle: { color: theme.grid.value } },
        },
        series: [
            {
                name: 'Overlap days',
                type: 'scatter',
                data: props.pairs.map((p) => [p.viirs, p.modis, p.date]),
                color: INSTRUMENT_COLORS.harmonized,
                symbolSize: 9,
            },
            {
                name: '1:1 (naive merge)',
                type: 'line',
                data: [
                    [0, 0],
                    [max, max],
                ],
                color: INSTRUMENT_COLORS.naive,
                symbol: 'none',
                lineStyle: { type: 'dashed', width: 1.5 },
            },
            ...(props.ratio
                ? [
                      {
                          name: `Fitted ratio × ${props.ratio.toFixed(3)}`,
                          type: 'line',
                          data: [
                              [0, 0],
                              [max, max * props.ratio],
                          ],
                          color: INSTRUMENT_COLORS.modis,
                          symbol: 'none',
                          lineStyle: { width: 2 },
                      },
                  ]
                : []),
        ],
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

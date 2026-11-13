<script setup lang="ts">
import { computed } from 'vue';
import VChart from 'vue-echarts';
import { useChartTheme } from '@/lib/charts';
import { INSTRUMENT_COLORS, formatNumber } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { DetectionCurve } from '@/types/continuum';

const props = defineProps<{ curve: DetectionCurve; chosen: number | null }>();

const theme = useChartTheme();

const option = computed(() => {
    const bins = props.curve.bins.filter((b) => b.viirs > 0);

    return {
        animation: false,
        textStyle: { color: theme.text.value },
        grid: { left: 56, right: 64, top: 56, bottom: 48 },
        legend: { top: 0, textStyle: { color: theme.text.value } },
        tooltip: {
            trigger: 'axis',
            ...theme.tooltip.value,
            formatter: (params: { dataIndex: number }[]) => {
                const bin = bins[params[0].dataIndex];

                return `<strong>ΣFRP ${bin.frp_from}${bin.frp_to ? `–${bin.frp_to}` : '+'} MW</strong><br>${t(':viirs VIIRS cell-days · :modis also seen by MODIS', { viirs: formatNumber(bin.viirs), modis: formatNumber(bin.seen_by_modis) })}<br>${t('P(MODIS detects)')} = ${bin.probability === null ? '—' : (bin.probability * 100).toFixed(0) + '%'}`;
            },
        },
        xAxis: {
            type: 'category',
            data: bins.map((b) => `${b.frp_from}`),
            name: t('VIIRS cell-day ΣFRP bin (MW, lower edge)'),
            nameLocation: 'middle',
            nameGap: 28,
            axisLabel: { color: theme.muted.value },
        },
        yAxis: [
            {
                type: 'value',
                name: t('P(seen by MODIS)'),
                min: 0,
                max: 1,
                axisLabel: {
                    color: theme.muted.value,
                    formatter: (v: number) => `${Math.round(v * 100)}%`,
                },
                splitLine: { lineStyle: { color: theme.grid.value } },
            },
            {
                type: 'value',
                name: t('VIIRS cell-days'),
                axisLabel: { color: theme.muted.value },
                splitLine: { show: false },
            },
        ],
        series: [
            {
                name: t('VIIRS cell-days in bin'),
                type: 'bar',
                yAxisIndex: 1,
                data: bins.map((b) => b.viirs),
                color: INSTRUMENT_COLORS.viirs,
                itemStyle: { opacity: 0.35 },
            },
            {
                name: t('Probability MODIS also detected the cell'),
                type: 'line',
                data: bins.map((b) => b.probability),
                color: INSTRUMENT_COLORS.modis,
                symbol: 'circle',
                symbolSize: 7,
                lineStyle: { width: 2.5 },
                markLine:
                    props.chosen !== null
                        ? {
                              silent: true,
                              symbol: 'none',
                              lineStyle: {
                                  color: INSTRUMENT_COLORS.harmonized,
                                  type: 'dashed',
                              },
                              data: [
                                  {
                                      xAxis: String(props.chosen),
                                      label: {
                                          formatter: `τ = ${props.chosen} MW`,
                                          color: theme.text.value,
                                      },
                                  },
                              ],
                          }
                        : undefined,
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
        :style="{ height: '288px' }"
    />
</template>

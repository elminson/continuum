import {
    BarChart,
    HeatmapChart,
    LineChart,
    ScatterChart,
} from 'echarts/charts';
import {
    DataZoomComponent,
    GridComponent,
    LegendComponent,
    MarkAreaComponent,
    MarkLineComponent,
    TooltipComponent,
    VisualMapComponent,
} from 'echarts/components';
import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { computed } from 'vue';
import { useAppearance } from '@/composables/useAppearance';

use([
    CanvasRenderer,
    LineChart,
    BarChart,
    HeatmapChart,
    ScatterChart,
    GridComponent,
    TooltipComponent,
    LegendComponent,
    VisualMapComponent,
    DataZoomComponent,
    MarkLineComponent,
    MarkAreaComponent,
]);

export function useChartTheme() {
    const { resolvedAppearance } = useAppearance();
    const isDark = computed(() => resolvedAppearance.value === 'dark');

    return {
        isDark,
        text: computed(() => (isDark.value ? '#d4d4d8' : '#3f3f46')),
        muted: computed(() => (isDark.value ? '#71717a' : '#a1a1aa')),
        grid: computed(() => (isDark.value ? '#27272a' : '#e4e4e7')),
        tooltip: computed(() => ({
            backgroundColor: isDark.value ? '#18181b' : '#ffffff',
            borderColor: isDark.value ? '#3f3f46' : '#e4e4e7',
            textStyle: { color: isDark.value ? '#e4e4e7' : '#18181b' },
        })),
    };
}

<script setup lang="ts">
import { Deferred, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { ArrowLeft, CalendarRange, FlaskConical, Sparkles } from '@lucide/vue';
import type { FeatureCollection } from 'geojson';
import { computed, defineAsyncComponent } from 'vue';
import VChart from 'vue-echarts';
import SectionCard from '@/components/continuum/SectionCard.vue';
import SeverityBadge from '@/components/continuum/SeverityBadge.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useChartTheme } from '@/lib/charts';
import { t } from '@/lib/i18n';
import {
    INSTRUMENT_COLORS,
    formatDate,
    formatNumber,
    formatPercent,
} from '@/lib/format';
import { home } from '@/routes';
import { index as alertsIndex, show as alertShow } from '@/routes/alerts';
import { lab, show as regionShow } from '@/routes/regions';
import type { FireAlert, Region } from '@/types/continuum';

const AlertFootprintMap = defineAsyncComponent(
    () => import('@/components/continuum/AlertFootprintMap.vue'),
);

type Detail = {
    from: string;
    to: string;
    footprint: FeatureCollection & {
        cells: number;
        truncated: boolean;
        bounds: [number, number, number, number] | null;
    };
    daily: { date: string; modis: number; viirs: number }[];
    history: {
        year: number;
        period_start: string;
        value: number | null;
        estimated: boolean;
        current: boolean;
    }[];
};

const props = defineProps<{
    alert: FireAlert;
    region: Region;
    detail?: Detail;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Overview', href: home() },
        { title: 'Unusual periods', href: alertsIndex() },
        {
            title: props.alert.message.split(':')[0],
            href: alertShow(props.alert.id),
        },
    ],
});

const theme = useChartTheme();
const shortName = computed(() => props.region.name.split(' (')[0]);
const title = computed(() => props.alert.message.split(':')[0]);
const multiple = computed(() =>
    props.alert.expected > 0
        ? props.alert.observed / props.alert.expected
        : null,
);
const scale = computed(() =>
    props.alert.reference === 'modis'
        ? t('MODIS-equivalent')
        : t('VIIRS-equivalent'),
);

// Phrases that name the period ("week", "month") are separate keys so each
// language can agree in gender; unknown periods fall back to the English.
function periodText(
    key: string,
    replacements: Record<string, string | number> = {},
): string {
    return t(key.replace(':period', props.alert.period), replacements);
}

// Peak day of the period, used for the satellite image under the map.
const peakDay = computed(() => {
    const days = props.detail?.daily ?? [];

    if (days.length === 0) {
        return props.alert.period_start;
    }

    return days.reduce((best, day) =>
        day.modis + day.viirs > best.modis + best.viirs ? day : best,
    ).date;
});

const CAUSE: Record<string, string> = {
    seasonal: 'A stronger usual season',
    outbreak: 'An exceptional outbreak',
    data_gap: 'Too little history to judge',
    sensor: 'A change of satellite coverage',
};

const dailyOption = computed(() => {
    const days = props.detail?.daily ?? [];

    return {
        animation: false,
        textStyle: { color: theme.text.value },
        grid: { left: 48, right: 16, top: 36, bottom: 32 },
        legend: { top: 0, textStyle: { color: theme.text.value } },
        tooltip: { trigger: 'axis', ...theme.tooltip.value },
        xAxis: {
            type: 'category',
            data: days.map((d) => formatDate(d.date).replace(/, \d{4}$/, '')),
            axisLabel: { color: theme.muted.value },
        },
        yAxis: {
            type: 'value',
            name: t('active 1 km cells'),
            nameTextStyle: { color: theme.muted.value, align: 'left' },
            axisLabel: { color: theme.muted.value },
            splitLine: { lineStyle: { color: theme.grid.value } },
        },
        series: [
            {
                name: 'MODIS',
                type: 'bar',
                data: days.map((d) => d.modis),
                color: INSTRUMENT_COLORS.modis,
            },
            {
                name: 'VIIRS',
                type: 'bar',
                data: days.map((d) => d.viirs),
                color: INSTRUMENT_COLORS.viirs,
            },
        ],
    };
});

const historyOption = computed(() => {
    const years = props.detail?.history ?? [];

    return {
        animation: false,
        textStyle: { color: theme.text.value },
        grid: { left: 56, right: 16, top: 24, bottom: 32 },
        tooltip: {
            trigger: 'axis',
            ...theme.tooltip.value,
            formatter: (params: { dataIndex: number }[]) => {
                const year = years[params[0].dataIndex];

                return `<strong>${year.year}</strong><br>${year.value === null ? t('no observations') : `${formatNumber(year.value)} ${t(':scale cell-days', { scale: scale.value })}`}${year.estimated ? `<br><em>${t('transferred from the other sensor')}</em>` : ''}`;
            },
        },
        xAxis: {
            type: 'category',
            data: years.map((y) => y.year),
            axisLabel: { color: theme.muted.value },
        },
        yAxis: {
            type: 'value',
            axisLabel: { color: theme.muted.value },
            splitLine: { lineStyle: { color: theme.grid.value } },
        },
        series: [
            {
                type: 'bar',
                data: years.map((y) => ({
                    value: y.value,
                    itemStyle: {
                        color: y.current
                            ? '#ef4444'
                            : y.estimated
                              ? theme.muted.value
                              : INSTRUMENT_COLORS.harmonized,
                    },
                })),
                markLine: {
                    silent: true,
                    symbol: 'none',
                    lineStyle: { color: theme.text.value, type: 'dashed' },
                    label: {
                        color: theme.text.value,
                        formatter: t('median :value', {
                            value: formatNumber(props.alert.expected),
                        }),
                    },
                    data: [{ yAxis: props.alert.expected }],
                },
            },
        ],
    };
});
</script>

<template>
    <Head :title="`${title} · ${shortName}`" />

    <div class="flex flex-col gap-4 p-4">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <SeverityBadge :severity="alert.severity" />
                    <Link
                        :href="regionShow(region.slug)"
                        class="font-medium hover:underline"
                        :style="{ color: region.color }"
                        >{{ region.name }}</Link
                    >
                </div>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight">
                    {{ title }}
                </h1>
                <p class="mt-1 max-w-3xl text-sm text-muted-foreground">
                    {{ alert.message }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <Link :href="alertsIndex()"
                        ><ArrowLeft class="size-4" />
                        {{ $t('All periods') }}</Link
                    >
                </Button>
                <Button variant="outline" as-child>
                    <Link
                        :href="
                            regionShow(region.slug, {
                                query: { period: alert.period },
                            })
                        "
                        ><CalendarRange class="size-4" />
                        {{ $t('Calendar') }}</Link
                    >
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="lab(region.slug)"
                        ><FlaskConical class="size-4" />
                        {{ $t('Calibration') }}</Link
                    >
                </Button>
            </div>
        </header>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
            >
                <p class="text-xs text-muted-foreground">
                    {{ $t('Observed') }}
                </p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ formatNumber(alert.observed) }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ $t(':scale cell-days', { scale }) }}
                </p>
            </div>
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
            >
                <p class="text-xs text-muted-foreground">{{ $t('Typical') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ formatNumber(alert.expected) }}
                    <span class="text-base text-muted-foreground"
                        >± {{ formatNumber(alert.spread) }}</span
                    >
                </p>
                <p class="text-xs text-muted-foreground">
                    {{
                        $t('median of :count other years', {
                            count: alert.climatology_years,
                        })
                    }}
                </p>
            </div>
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
            >
                <p class="text-xs text-muted-foreground">
                    {{ $t('Departure') }}
                </p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{
                        multiple === null
                            ? $t('n/a')
                            : `×${formatNumber(multiple, 1)}`
                    }}
                </p>
                <p class="text-xs text-muted-foreground">
                    z = {{ formatNumber(alert.z_score, 1) }},
                    {{
                        $t(':valueth percentile', {
                            value: formatNumber(alert.percentile, 0),
                        })
                    }}
                </p>
            </div>
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
            >
                <p class="text-xs text-muted-foreground">{{ $t('Where') }}</p>
                <Deferred data="detail">
                    <template #fallback
                        ><Skeleton class="mt-2 h-7 w-20"
                    /></template>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ formatNumber(detail?.footprint.cells ?? 0) }}
                    </p>
                </Deferred>
                <p class="text-xs text-muted-foreground">
                    {{ $t('1 km cells with fire') }}
                </p>
            </div>
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
            >
                <p class="text-xs text-muted-foreground">{{ $t('When') }}</p>
                <Deferred data="detail">
                    <template #fallback
                        ><Skeleton class="mt-2 h-7 w-24"
                    /></template>
                    <p class="mt-1 text-lg font-semibold">
                        {{ formatDate(peakDay) }}
                    </p>
                </Deferred>
                <p class="text-xs text-muted-foreground">
                    {{ periodText('busiest day of the :period') }}
                </p>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <SectionCard
                :title="$t('Where the fire was')"
                :description="
                    detail
                        ? `${$t('Every active 1 km cell from :from to :to, over the VIIRS image of the busiest day.', { from: formatDate(detail.from), to: formatDate(detail.to) })}${detail.footprint.truncated ? ` ${$t('Showing the strongest cells only.')}` : ''}`
                        : $t('Loading the period')
                "
            >
                <Deferred data="detail">
                    <template #fallback>
                        <Skeleton class="h-[460px] w-full rounded-xl" />
                    </template>
                    <AlertFootprintMap
                        v-if="detail"
                        :footprint="detail.footprint"
                        :bounds="detail.footprint.bounds"
                        :region-box="region.bbox"
                        :imagery-date="peakDay"
                    />
                </Deferred>
            </SectionCard>

            <div class="flex flex-col gap-4">
                <SectionCard v-if="alert.triage" :title="$t('AI reading')">
                    <template #actions>
                        <span
                            class="inline-flex items-center gap-1 text-xs text-lime-700 dark:text-lime-400"
                        >
                            <Sparkles class="size-3.5" /> {{ $t('advisory') }}
                        </span>
                    </template>
                    <p class="text-lg font-semibold">
                        {{
                            $t(CAUSE[alert.triage.cause] ?? alert.triage.cause)
                        }}
                    </p>
                    <p class="mt-2 flex flex-wrap gap-2 text-sm">
                        <span
                            class="rounded-md px-2 py-0.5 font-medium"
                            :class="
                                alert.triage.attention === 'act_now'
                                    ? 'bg-red-500/15 text-red-700 dark:text-red-300'
                                    : 'bg-muted'
                            "
                            >{{ alert.triage.attention_label }}</span
                        >
                        <span class="rounded-md bg-muted px-2 py-0.5">{{
                            alert.triage.baseline_reliable
                                ? $t('Baseline reliable')
                                : $t('Baseline too thin')
                        }}</span>
                        <span class="rounded-md bg-muted px-2 py-0.5">{{
                            $t('Artifact risk :value', {
                                value: formatPercent(
                                    alert.triage.artifact_probability,
                                ),
                            })
                        }}</span>
                    </p>
                    <p
                        v-if="alert.triage.rationale"
                        class="mt-3 text-sm text-muted-foreground"
                    >
                        {{ alert.triage.rationale }}
                    </p>
                    <p class="mt-3 text-xs text-muted-foreground">
                        {{
                            $t(
                                ':model, self-hosted. The statistics flagged this period; the reading only explains it.',
                                { model: alert.triage.model },
                            )
                        }}
                    </p>
                </SectionCard>

                <SectionCard :title="$t('How it is measured')">
                    <ul class="space-y-2 text-sm text-muted-foreground">
                        <li>
                            {{
                                $t(
                                    'Unit: active 1 km cells per day, both satellites of each instrument merged, harmonized to the :scale scale with the :method transfer.',
                                    { scale, method: alert.method },
                                )
                            }}
                        </li>
                        <li>
                            {{
                                periodText(
                                    'Compared with the same :period in :count other years using the median and the robust spread (MAD).',
                                    { count: alert.climatology_years },
                                )
                            }}
                        </li>
                        <li>
                            {{
                                periodText(
                                    'Only years observed for at least half the :period count toward the baseline.',
                                )
                            }}
                        </li>
                    </ul>
                </SectionCard>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <SectionCard
                :title="$t('Day by day')"
                :description="
                    $t(
                        'Active 1 km cells each sensor saw on each day of the period',
                    )
                "
            >
                <Deferred data="detail">
                    <template #fallback
                        ><Skeleton class="h-[260px] w-full"
                    /></template>
                    <VChart
                        :option="dailyOption"
                        autoresize
                        style="height: 260px"
                    />
                </Deferred>
            </SectionCard>
            <SectionCard
                :title="periodText('The same :period, every year')"
                :description="
                    $t(
                        'Harmonized value per year; this year in red, grey bars transferred from the other sensor',
                    )
                "
            >
                <Deferred data="detail">
                    <template #fallback
                        ><Skeleton class="h-[260px] w-full"
                    /></template>
                    <VChart
                        :option="historyOption"
                        autoresize
                        style="height: 260px"
                    />
                </Deferred>
            </SectionCard>
        </div>
    </div>
</template>

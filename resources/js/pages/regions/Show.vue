<script setup lang="ts">
import { Deferred, Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import {
    Download,
    FileSpreadsheet,
    FlaskConical,
    History,
    Pencil,
    RefreshCw,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AlertList from '@/components/continuum/AlertList.vue';
import CalendarHeatmap from '@/components/continuum/CalendarHeatmap.vue';
import ComparisonChart from '@/components/continuum/ComparisonChart.vue';
import FactorTable from '@/components/continuum/FactorTable.vue';
import FireMap from '@/components/continuum/FireMap.vue';
import HarmonizedSeriesChart from '@/components/continuum/HarmonizedSeriesChart.vue';
import KpiTile from '@/components/continuum/KpiTile.vue';
import ReceiptPanel from '@/components/continuum/ReceiptPanel.vue';
import SeasonBar from '@/components/continuum/SeasonBar.vue';
import SectionCard from '@/components/continuum/SectionCard.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { formatCompact, formatDate, formatNumber } from '@/lib/format';
import { t } from '@/lib/i18n';
import { home, method as methodPage } from '@/routes';
import {
    exportMethod as exportCalendar,
    hotspots as exportHotspots,
} from '@/routes/api/v1/regions';
import { backfill, edit, harmonize, index, lab, show } from '@/routes/regions';
import type {
    CriticalPeriods,
    Factor,
    FireAlert,
    PeriodComparison,
    Receipt,
    Region,
    SelectOption,
    Series,
} from '@/types/continuum';

const props = defineProps<{
    region: Region;
    filters: {
        period: string;
        method: string;
        reference: string;
        from: string | null;
        to: string | null;
    };
    options: {
        periods: SelectOption[];
        methods: SelectOption[];
        references: SelectOption[];
    };
    series: Series;
    comparison: PeriodComparison | null;
    critical: CriticalPeriods;
    alerts: FireAlert[];
    factors: Factor[];
    receipt?: Receipt;
    backfill: {
        id: string;
        total: number;
        done: number;
        failed: number;
        started_at: string;
    } | null;
    canBackfill: boolean;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Overview', href: home() },
        { title: 'Areas of interest', href: index() },
        {
            title: props.region.name,
            href: show(props.region.slug),
        },
    ],
});

const period = ref(props.filters.period);
const method = ref(props.filters.method);
const reference = ref(props.filters.reference);
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');
const calendarMetric = ref<'harmonized' | 'hotspots'>('harmonized');
const showNaive = ref(false);
const showNative = ref(true);
const mapMode = ref<'both' | 'modis' | 'viirs'>('both');
const mapDate = ref<string | null>(
    props.region.summary?.last_active_date ??
        props.region.summary?.last_date ??
        null,
);

function query(): Record<string, string> {
    const params: Record<string, string> = {
        period: period.value,
        method: method.value,
        reference: reference.value,
    };

    if (from.value && to.value && from.value <= to.value) {
        params.from = from.value;
        params.to = to.value;
    }

    return params;
}

function reload(): void {
    router.get(show(props.region.slug).url, query(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['series', 'critical', 'comparison', 'filters', 'receipt'],
    });
}

watch([period, method, reference], reload);
watch([from, to], ([f, t]) => {
    if ((f && t && f <= t) || (!f && !t)) {
        reload();
    }
});

const lastDate = computed(() => props.region.summary?.last_date ?? null);

function shift(date: string, days: number): string {
    const d = new Date(`${date}T00:00:00Z`);
    d.setUTCDate(d.getUTCDate() + days);

    return d.toISOString().slice(0, 10);
}

const presets = computed(() => {
    const last = lastDate.value;

    if (!last) {
        return [];
    }

    return [
        { label: t('30 days'), from: shift(last, -29), to: last },
        { label: t('12 months'), from: shift(last, -364), to: last },
        {
            label: `${last.slice(0, 4)}`,
            from: `${last.slice(0, 4)}-01-01`,
            to: last,
        },
        { label: t('All years'), from: '', to: '' },
    ];
});

function applyPreset(preset: { from: string; to: string }): void {
    from.value = preset.from;
    to.value = preset.to;
}

const rangeLabel = computed(() =>
    props.comparison
        ? `${formatDate(props.comparison.from)} – ${formatDate(props.comparison.to)}`
        : '',
);

const cellDescription = computed(
    () =>
        ({
            day: t('Each cell is one day'),
            week: t('Each cell is one week'),
            month: t('Each cell is one month'),
        })[props.series.period] ?? `Each cell is one ${props.series.period}`,
);

const fileStem = computed(
    () =>
        `continuum_${props.region.slug}${props.filters.from ? `_${props.filters.from}_${props.filters.to}` : ''}`,
);

const exportUrl = computed(() =>
    exportCalendar.url(props.region.slug, { query: query() }),
);

const hotspotsUrl = computed(() =>
    exportHotspots.url(props.region.slug, {
        query: props.comparison
            ? { from: props.comparison.from, to: props.comparison.to }
            : {},
    }),
);

const methodOption = computed(() =>
    props.options.methods.find((m) => m.value === props.series.method),
);

const change = computed(() => {
    const value = props.comparison?.change;

    if (value === null || value === undefined) {
        return null;
    }

    const percent = Math.round(value * 100);

    return {
        text: `${percent > 0 ? '+' : ''}${percent}%`,
        color:
            percent >= 25 ? '#e69f00' : percent <= -25 ? '#3b82f6' : undefined,
    };
});
</script>

<template>
    <Head :title="region.name" />

    <div class="flex flex-col gap-4 p-4">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="flex items-center gap-2 text-xl font-semibold">
                    <span
                        class="inline-block size-3 rounded-full"
                        :style="{ backgroundColor: region.color }"
                    />
                    {{ region.name }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ region.description }}
                    <span class="whitespace-nowrap"
                        >· {{ formatNumber(region.area_km2) }} km² ·
                        {{ $t('box') }} {{ region.bbox.join(', ') }}</span
                    >
                    <span
                        v-if="region.summary?.first_date"
                        class="whitespace-nowrap"
                    >
                        ·
                        {{
                            $t('data :from → :to', {
                                from: formatDate(region.summary.first_date),
                                to: formatDate(region.summary.last_date),
                            })
                        }}</span
                    >
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span
                    v-if="props.backfill"
                    class="inline-flex items-center gap-2 rounded-md border border-orange-500/40 bg-orange-500/10 px-2.5 py-1 text-xs text-orange-600 dark:text-orange-400"
                    :title="
                        $t('Started :time', {
                            time: props.backfill.started_at,
                        })
                    "
                >
                    <span
                        class="size-2 animate-pulse rounded-full bg-orange-500"
                    />
                    {{
                        $t('Fetching FIRMS history · :done / :total windows', {
                            done: props.backfill.done,
                            total: props.backfill.total,
                        })
                    }}
                    <span v-if="props.backfill.failed > 0"
                        >·
                        {{
                            $t(':count failed', {
                                count: props.backfill.failed,
                            })
                        }}</span
                    >
                </span>
                <Button
                    v-else-if="canBackfill"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link
                        :href="backfill(region.slug)"
                        method="post"
                        as="button"
                        ><History class="size-4" />
                        {{ $t('Fetch full history') }}</Link
                    >
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="lab(region.slug)"
                        ><FlaskConical class="size-4" />
                        {{ $t('Harmonization lab') }}</Link
                    >
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <a :href="hotspotsUrl"
                        ><FileSpreadsheet class="size-4" />
                        {{ $t('Hotspots CSV') }}</a
                    >
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <a :href="exportUrl"
                        ><Download class="size-4" /> {{ $t('Calendar CSV') }}</a
                    >
                </Button>
                <template v-if="region.is_owner">
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="edit(region.slug)"
                            ><Pencil class="size-4" /> {{ $t('Edit') }}</Link
                        >
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <Link
                            :href="harmonize(region.slug)"
                            method="post"
                            as="button"
                            ><RefreshCw class="size-4" />
                            {{ $t('Re-run') }}</Link
                        >
                    </Button>
                </template>
            </div>
        </header>

        <div
            class="flex flex-wrap items-end gap-3 rounded-xl border border-sidebar-border/70 bg-card p-3 dark:border-sidebar-border"
        >
            <label class="grid gap-1 text-xs">
                <span class="text-muted-foreground">{{ $t('From') }}</span>
                <input
                    v-model="from"
                    type="date"
                    :min="region.summary?.first_date ?? undefined"
                    :max="to || (lastDate ?? undefined)"
                    class="h-8 rounded-md border border-input bg-background px-2 text-sm"
                />
            </label>
            <label class="grid gap-1 text-xs">
                <span class="text-muted-foreground">{{ $t('To') }}</span>
                <input
                    v-model="to"
                    type="date"
                    :min="from || (region.summary?.first_date ?? undefined)"
                    :max="lastDate ?? undefined"
                    class="h-8 rounded-md border border-input bg-background px-2 text-sm"
                />
            </label>
            <div
                class="flex overflow-hidden rounded-md border border-border text-xs"
            >
                <button
                    v-for="preset in presets"
                    :key="preset.label"
                    type="button"
                    class="h-8 px-2.5"
                    :class="
                        from === preset.from && to === preset.to
                            ? 'bg-foreground text-background'
                            : 'hover:bg-muted'
                    "
                    @click="applyPreset(preset)"
                >
                    {{ preset.label }}
                </button>
            </div>
            <label class="grid gap-1 text-xs">
                <span class="text-muted-foreground">{{ $t('Group by') }}</span>
                <select
                    v-model="period"
                    class="h-8 rounded-md border border-input bg-background px-2 text-sm"
                >
                    <option
                        v-for="o in options.periods"
                        :key="o.value"
                        :value="o.value"
                    >
                        {{ o.label }}
                    </option>
                </select>
            </label>
            <details class="group basis-full text-xs">
                <summary
                    class="cursor-pointer text-muted-foreground select-none hover:text-foreground"
                >
                    {{ $t('Harmonization options') }}
                </summary>
                <div class="mt-2 flex flex-wrap items-end gap-3">
                    <label class="grid gap-1">
                        <span class="text-muted-foreground">{{
                            $t('Transfer method')
                        }}</span>
                        <select
                            v-model="method"
                            class="h-8 rounded-md border border-input bg-background px-2 text-sm"
                        >
                            <option
                                v-for="o in options.methods"
                                :key="o.value"
                                :value="o.value"
                            >
                                {{ o.label }}
                            </option>
                        </select>
                    </label>
                    <label class="grid gap-1">
                        <span class="text-muted-foreground">{{
                            $t('Scale')
                        }}</span>
                        <select
                            v-model="reference"
                            class="h-8 rounded-md border border-input bg-background px-2 text-sm"
                        >
                            <option
                                v-for="o in options.references"
                                :key="o.value"
                                :value="o.value"
                            >
                                {{ o.label }}
                            </option>
                        </select>
                    </label>
                    <label class="flex items-center gap-2"
                        ><input
                            v-model="showNative"
                            type="checkbox"
                            class="size-3.5"
                        />
                        {{ $t('Native MODIS / VIIRS') }}</label
                    >
                    <label class="flex items-center gap-2"
                        ><input
                            v-model="showNaive"
                            type="checkbox"
                            class="size-3.5"
                        />
                        {{ $t('Naive merge') }}</label
                    >
                    <p
                        v-if="methodOption?.description"
                        class="basis-full text-muted-foreground"
                    >
                        {{ methodOption.description }}
                    </p>
                </div>
            </details>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <KpiTile
                :label="$t('Hotspots')"
                :value="formatCompact(comparison?.hotspots ?? null)"
                :hint="rangeLabel"
            />
            <KpiTile
                :label="$t('Fire activity')"
                :value="formatCompact(comparison?.current ?? null)"
                :hint="$t('harmonized cell-days, any sensor')"
                accent="#4ade80"
            />
            <KpiTile
                :label="$t('Historical median')"
                :value="formatCompact(comparison?.median ?? null)"
                :hint="
                    comparison?.reason === 'range_too_long'
                        ? $t('pick a range of up to one year')
                        : comparison?.years
                          ? $t('same dates, :count earlier years', {
                                count: comparison.years,
                            })
                          : $t('no earlier years yet')
                "
            />
            <KpiTile
                :label="$t('Versus median')"
                :value="change?.text ?? '—'"
                :accent="change?.color"
                :hint="
                    comparison?.percentile !== null &&
                    comparison?.percentile !== undefined
                        ? $t('higher than :percent% of earlier years', {
                              percent: Math.round(comparison.percentile),
                          })
                        : ''
                "
            />
        </div>

        <SectionCard
            v-if="comparison && comparison.history.length > 0"
            :title="$t('Same dates in earlier years')"
            :description="
                $t(':range against every earlier year of the record', {
                    range: rangeLabel,
                })
            "
            :download="`${fileStem}_vs_median`"
        >
            <ComparisonChart :comparison="comparison" />
        </SectionCard>

        <SectionCard
            :title="$t('Harmonized burning activity')"
            :download="`${fileStem}_series`"
            :description="
                $t(
                    'Active 1 km cell-days per :period; shaded band is the 95% transfer interval on estimated periods.',
                    { period: $t(series.period) },
                )
            "
        >
            <HarmonizedSeriesChart
                :points="series.points"
                :period="series.period"
                :reference="series.reference"
                :show-naive="showNaive"
                :show-native="showNative"
            />
            <p
                v-if="showNaive"
                class="mt-2 rounded-md bg-muted p-2 text-xs text-muted-foreground"
            >
                {{
                    $t(
                        'The naive merge concatenates whichever sensor is newest. Where VIIRS starts (2012) it jumps by the sensitivity gap, not because burning changed.',
                    )
                }}
            </p>
        </SectionCard>

        <div class="grid gap-4 xl:grid-cols-2">
            <SectionCard
                :title="$t('Fire activity calendar')"
                :description="cellDescription"
                :download="`${fileStem}_calendar_${calendarMetric}`"
                :download-note="
                    calendarMetric === 'hotspots'
                        ? $t('raw hotspots')
                        : $t('harmonized cell-days')
                "
            >
                <template #actions>
                    <div
                        class="flex overflow-hidden rounded-md border border-border text-xs"
                    >
                        <button
                            v-for="metric in [
                                'harmonized',
                                'hotspots',
                            ] as const"
                            :key="metric"
                            type="button"
                            class="px-2.5 py-1"
                            :class="
                                calendarMetric === metric
                                    ? 'bg-foreground text-background'
                                    : 'hover:bg-muted'
                            "
                            @click="calendarMetric = metric"
                        >
                            {{
                                metric === 'harmonized'
                                    ? $t('Harmonized')
                                    : $t('Hotspots')
                            }}
                        </button>
                    </div>
                </template>
                <CalendarHeatmap
                    :points="series.points"
                    :period="series.period"
                    :metric="calendarMetric"
                />
            </SectionCard>
            <SectionCard
                :title="$t('Critical periods')"
                :description="
                    $t(
                        'Climatological fire season from the harmonized monthly record',
                    )
                "
            >
                <SeasonBar :critical="critical" />
            </SectionCard>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <SectionCard
                class="xl:col-span-2"
                :title="$t('Where it burned')"
                :description="
                    $t('Both sensors on the same 1 km grid for one day')
                "
                :download="`${fileStem}_map_${mapDate}`"
                :download-note="mapDate ? formatDate(mapDate) : ''"
            >
                <template #actions>
                    <input
                        v-model="mapDate"
                        type="date"
                        :min="region.summary?.first_date ?? undefined"
                        :max="region.summary?.last_date ?? undefined"
                        class="h-8 rounded-md border border-input bg-background px-2 text-xs"
                    />
                    <div
                        class="flex overflow-hidden rounded-md border border-border text-xs"
                    >
                        <button
                            v-for="mode in ['both', 'modis', 'viirs'] as const"
                            :key="mode"
                            class="px-2.5 py-1 capitalize"
                            :class="
                                mapMode === mode
                                    ? 'bg-foreground text-background'
                                    : 'hover:bg-muted'
                            "
                            @click="mapMode = mode"
                        >
                            {{ mode === 'both' ? $t('Both') : mode }}
                        </button>
                    </div>
                </template>
                <FireMap
                    :bbox="region.bbox"
                    :date="mapDate"
                    :mode="mapMode"
                    height="h-[460px]"
                />
            </SectionCard>
            <SectionCard
                :title="$t('Unusual periods')"
                :description="
                    $t('Robust anomaly screening on the harmonized series')
                "
            >
                <AlertList :alerts="alerts" />
            </SectionCard>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <SectionCard
                :title="$t('Transfer functions')"
                :description="
                    $t(
                        'Fitted on the days both instruments observed this area; validated on held-out days',
                    )
                "
            >
                <FactorTable :factors="factors" />
            </SectionCard>
            <SectionCard
                :title="$t('Method receipt')"
                :description="
                    $t('Provenance shipped with every export and API response')
                "
            >
                <Deferred data="receipt">
                    <template #fallback>
                        <div class="space-y-2">
                            <Skeleton class="h-4 w-3/4" /><Skeleton
                                class="h-4 w-1/2"
                            /><Skeleton class="h-4 w-2/3" />
                        </div>
                    </template>
                    <ReceiptPanel v-if="receipt" :receipt="receipt" />
                </Deferred>
            </SectionCard>
        </div>

        <p class="text-xs text-muted-foreground">
            {{ $t('Data') }}: NASA FIRMS · MODIS Collection 6.1 (Terra, Aqua) ·
            VIIRS 375 m (S-NPP, NOAA-20, NOAA-21) ·
            <Link :href="methodPage()" class="underline">{{
                $t('How it works')
            }}</Link>
        </p>
    </div>
</template>

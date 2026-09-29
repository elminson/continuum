<script setup lang="ts">
import { Deferred, Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import {
    Download,
    FlaskConical,
    History,
    Pencil,
    RefreshCw,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AlertList from '@/components/continuum/AlertList.vue';
import CalendarHeatmap from '@/components/continuum/CalendarHeatmap.vue';
import FactorTable from '@/components/continuum/FactorTable.vue';
import FireMap from '@/components/continuum/FireMap.vue';
import HarmonizedSeriesChart from '@/components/continuum/HarmonizedSeriesChart.vue';
import KpiTile from '@/components/continuum/KpiTile.vue';
import ReceiptPanel from '@/components/continuum/ReceiptPanel.vue';
import SeasonBar from '@/components/continuum/SeasonBar.vue';
import SectionCard from '@/components/continuum/SectionCard.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import {
    formatCompact,
    formatDate,
    formatNumber,
    formatPercent,
} from '@/lib/format';
import { home } from '@/routes';
import { exportMethod as exportCalendar } from '@/routes/api/v1/regions';
import { backfill, edit, harmonize, index, lab, show } from '@/routes/regions';
import type {
    CriticalPeriods,
    Factor,
    FireAlert,
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
const showNaive = ref(false);
const showNative = ref(true);
const mapMode = ref<'both' | 'modis' | 'viirs'>('both');
const mapDate = ref<string | null>(
    props.region.summary?.last_active_date ??
        props.region.summary?.last_date ??
        null,
);

watch([period, method, reference], () => {
    router.get(
        show(props.region.slug).url,
        {
            period: period.value,
            method: method.value,
            reference: reference.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['series', 'critical', 'filters', 'receipt'],
        },
    );
});

const totals = computed(() => {
    const points = props.series.points;
    const harmonized = points.reduce((sum, p) => sum + (p.harmonized ?? 0), 0);
    const estimated = points.filter((p) => p.estimated).length;
    const modis = points.reduce((sum, p) => sum + (p.modis?.cell_days ?? 0), 0);
    const viirs = points.reduce((sum, p) => sum + (p.viirs?.cell_days ?? 0), 0);
    const frp = points.reduce(
        (sum, p) => sum + (p.modis?.frp_sum ?? 0) + (p.viirs?.frp_sum ?? 0),
        0,
    );

    return { harmonized, estimated, modis, viirs, frp, count: points.length };
});

const methodOption = computed(() =>
    props.options.methods.find((m) => m.value === props.series.method),
);
const exportUrl = computed(() =>
    exportCalendar.url(props.region.slug, {
        query: {
            period: period.value,
            method: method.value,
            reference: reference.value,
        },
    }),
);
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
                        >· {{ formatNumber(region.area_km2) }} km² · box
                        {{ region.bbox.join(', ') }}</span
                    >
                    <span
                        v-if="region.summary?.first_date"
                        class="whitespace-nowrap"
                    >
                        · data {{ formatDate(region.summary.first_date) }} →
                        {{ formatDate(region.summary.last_date) }}</span
                    >
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span
                    v-if="props.backfill"
                    class="inline-flex items-center gap-2 rounded-md border border-orange-500/40 bg-orange-500/10 px-2.5 py-1 text-xs text-orange-600 dark:text-orange-400"
                    :title="`Started ${props.backfill.started_at}`"
                >
                    <span
                        class="size-2 animate-pulse rounded-full bg-orange-500"
                    />
                    Fetching FIRMS history · {{ props.backfill.done }} /
                    {{ props.backfill.total }} windows
                    <span v-if="props.backfill.failed > 0"
                        >· {{ props.backfill.failed }} failed</span
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
                        ><History class="size-4" /> Fetch full history</Link
                    >
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="lab(region.slug)"
                        ><FlaskConical class="size-4" /> Harmonization lab</Link
                    >
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <a :href="exportUrl"
                        ><Download class="size-4" /> CSV + receipt</a
                    >
                </Button>
                <template v-if="region.is_owner">
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="edit(region.slug)"
                            ><Pencil class="size-4" /> Edit</Link
                        >
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <Link
                            :href="harmonize(region.slug)"
                            method="post"
                            as="button"
                            ><RefreshCw class="size-4" /> Re-run</Link
                        >
                    </Button>
                </template>
            </div>
        </header>

        <div
            class="flex flex-wrap items-end gap-3 rounded-xl border border-sidebar-border/70 bg-card p-3 dark:border-sidebar-border"
        >
            <label class="grid gap-1 text-xs">
                <span class="text-muted-foreground">Period</span>
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
            <label class="grid gap-1 text-xs">
                <span class="text-muted-foreground">Transfer method</span>
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
            <label class="grid gap-1 text-xs">
                <span class="text-muted-foreground">Express everything as</span>
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
            <label class="flex items-center gap-2 text-xs"
                ><input v-model="showNative" type="checkbox" class="size-3.5" />
                native MODIS / VIIRS</label
            >
            <label
                class="flex items-center gap-2 text-xs font-medium text-rose-500"
                ><input v-model="showNaive" type="checkbox" class="size-3.5" />
                show the naive merge (sensor-switch illusion)</label
            >
            <p
                v-if="methodOption?.description"
                class="basis-full text-xs text-muted-foreground"
            >
                {{ methodOption.description }}
            </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <KpiTile
                label="Harmonized cell-days"
                :value="formatCompact(totals.harmonized)"
                :hint="`${series.reference.toUpperCase()}-equivalent · ${totals.count} ${series.period}s`"
                accent="#a3e635"
            />
            <KpiTile
                label="Estimated periods"
                :value="String(totals.estimated)"
                hint="transferred from the other sensor"
            />
            <KpiTile
                label="MODIS native"
                :value="formatCompact(totals.modis)"
                hint="1 km cell-days"
                accent="#38bdf8"
            />
            <KpiTile
                label="VIIRS native"
                :value="formatCompact(totals.viirs)"
                hint="375 m pixels collapsed to 1 km cell-days"
                accent="#f97316"
            />
            <KpiTile
                label="Calibration"
                :value="
                    series.calibrated
                        ? series.validation
                            ? `−${formatPercent(series.validation.mae_improvement)} error`
                            : 'ready'
                        : 'pending'
                "
                :hint="
                    series.validation
                        ? `holdout MAE ${formatNumber(series.validation.harmonized.mae, 1)} vs naive ${formatNumber(series.validation.naive.mae, 1)}`
                        : 'needs overlap days'
                "
            />
        </div>

        <SectionCard
            title="Harmonized burning activity"
            :description="`Active 1 km cell-days per ${series.period}; shaded band is the 95% transfer interval on estimated periods.`"
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
                class="mt-2 rounded-md bg-rose-500/10 p-2 text-xs text-rose-600 dark:text-rose-400"
            >
                The naive merge concatenates whichever sensor is newest. Where
                VIIRS starts (2012) it jumps by the sensitivity gap, not because
                burning changed.
            </p>
        </SectionCard>

        <div class="grid gap-4 xl:grid-cols-2">
            <SectionCard
                title="Burning activity calendar"
                :description="`Each cell is one ${series.period}; brighter is more harmonized activity.`"
            >
                <CalendarHeatmap
                    :points="series.points"
                    :period="series.period"
                />
            </SectionCard>
            <SectionCard
                title="Critical periods"
                description="Climatological fire season from the harmonized monthly record"
            >
                <SeasonBar :critical="critical" />
            </SectionCard>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <SectionCard
                class="xl:col-span-2"
                title="Where it burned"
                description="Both sensors on the same 1 km grid for one day"
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
                            {{ mode }}
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
                title="Unusual periods"
                description="Robust anomaly screening on the harmonized series"
            >
                <AlertList :alerts="alerts" />
            </SectionCard>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <SectionCard
                title="Transfer functions"
                description="Fitted on the days both instruments observed this area; validated on held-out days"
            >
                <FactorTable :factors="factors" />
            </SectionCard>
            <SectionCard
                title="Method receipt"
                description="Provenance shipped with every export and API response"
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
    </div>
</template>

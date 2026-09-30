<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BadgeCheck,
    Boxes,
    CalendarRange,
    CloudDownload,
    Cloud,
    Code2,
    Database,
    FlaskConical,
    Fingerprint,
    Gauge,
    Globe2,
    Grid3x3,
    Layers,
    Radar,
    Satellite,
    ServerCog,
    ShieldCheck,
    Sigma,
    Timer,
    Workflow,
} from '@lucide/vue';
import { computed, defineAsyncComponent } from 'vue';
import ArchitectureFlow from '@/components/engineering/ArchitectureFlow.vue';
import type { ArchitectureLayer } from '@/components/engineering/ArchitectureFlow.vue';
import CountUp from '@/components/engineering/CountUp.vue';
import PipelineTimeline from '@/components/engineering/PipelineTimeline.vue';
import type { PipelineStage } from '@/components/engineering/PipelineTimeline.vue';
import { Button } from '@/components/ui/button';
import { vReveal } from '@/composables/useReveal';
import { formatCompact, formatDate, formatNumber } from '@/lib/format';
import { apiDocs, engineering, home, method } from '@/routes';
import { lab, show } from '@/routes/regions';
import type { OverlapPair, ThresholdPoint } from '@/types/continuum';

// The map and charts are the heaviest code on the page: load them on demand.
const FireMap = defineAsyncComponent(
    () => import('@/components/continuum/FireMap.vue'),
);
const OverlapScatterChart = defineAsyncComponent(
    () => import('@/components/continuum/OverlapScatterChart.vue'),
);
const ThresholdCurveChart = defineAsyncComponent(
    () => import('@/components/continuum/ThresholdCurveChart.vue'),
);

type Validation = {
    train_days: number;
    test_days: number;
    holdout: string | null;
    mae: number | null;
    r2: number | null;
    bias: number | null;
    naive_mae: number | null;
    naive_r2: number | null;
    mae_improvement: number | null;
    correlation: number | null;
};

const props = defineProps<{
    generated_at: string;
    totals: {
        detections: number;
        cell_days: number;
        days: number;
        first_date: string | null;
        last_date: string | null;
        modis_detections: number;
        viirs_detections: number;
        satellites: number;
        public_areas: number;
        areas: number;
        calibrated_areas: number;
        alerts: number;
        ingest_runs: number;
        area_windows: number;
        public_pulls: number;
        rows_read: number;
        rows_inserted: number;
        rows_skipped: number;
        calibration_factors: number;
        calendar_entries: number;
    };
    config: {
        grid_km: number;
        calendar_cell_deg: number;
        bootstrap_samples: number;
        holdout_fraction: number;
        method_version: string;
        window_days: number;
        thresholds: { watch: number; warning: number; critical: number };
    };
    featured: {
        slug: string;
        name: string;
        color: string;
        bbox: [number, number, number, number];
        map_date: string | null;
        ratio: {
            value: number;
            ci95: [number, number];
            sample_days: number;
            overlap: [string | null, string | null];
            validation: Validation | null;
        };
        threshold: { value: number; validation: Validation | null } | null;
        pairs: OverlapPair[];
        threshold_curve: ThresholdPoint[];
    } | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Overview', href: home() },
            { title: 'How it works', href: engineering() },
        ],
    },
});

const t = computed(() => props.totals);
const f = computed(() => props.featured);
const shortName = computed(() => f.value?.name.split(' (')[0] ?? '');
const years = computed(() => {
    if (!t.value.first_date || !t.value.last_date) {
        return null;
    }

    return (
        Number(t.value.last_date.slice(0, 4)) -
        Number(t.value.first_date.slice(0, 4)) +
        1
    );
});
const ratioValidation = computed(() => f.value?.ratio.validation ?? null);
const improvement = computed(() =>
    ratioValidation.value?.mae_improvement === null ||
    ratioValidation.value?.mae_improvement === undefined
        ? null
        : ratioValidation.value.mae_improvement * 100,
);
const inflation = computed(() =>
    t.value.modis_detections > 0
        ? t.value.viirs_detections / t.value.modis_detections
        : null,
);

const compact = (value: number) => formatCompact(Math.round(value));
const whole = (value: number) => formatNumber(Math.round(value));

const stages = computed<PipelineStage[]>(() => [
    {
        icon: CloudDownload,
        title: 'Fetch',
        body: `The FIRMS Area API is walked in ${props.config.window_days}-day windows per sensor, newest first, inside a transaction budget that pauses the queue instead of failing it. Boxes are padded so neighbouring areas reuse each download.`,
        code: 'BackfillPlanner · AreaApiClient',
        metric: formatNumber(t.value.area_windows),
        metricLabel: 'FIRMS windows fetched',
    },
    {
        icon: Fingerprint,
        title: 'Parse and deduplicate',
        body: 'Every CSV row is normalised across MODIS C6.1 and VIIRS C2 schemas and fingerprinted. Re-downloads, overlapping boxes and near-real-time rows later replaced by the science archive never count twice.',
        code: 'CsvParser · IngestDetections',
        metric: compact(t.value.rows_read),
        metricLabel: 'rows read from NASA',
    },
    {
        icon: Grid3x3,
        title: 'Snap to one grid',
        body: `A 1 km pixel and a 375 m pixel are not the same event. Both sensors land on one ${props.config.grid_km} km equal-area grid, and the unit of record becomes the active cell-day, with each instrument's satellites merged.`,
        code: 'Support\\Geo\\Grid · AggregateCellDays',
        metric: compact(t.value.cell_days),
        metricLabel: 'cell-days on the grid',
    },
    {
        icon: Sigma,
        title: 'Calibrate on the overlap',
        body: `Where both instruments flew, two transfer functions are fitted: a monthly ratio with a ${props.config.bootstrap_samples}-sample paired bootstrap interval, and an FRP visibility threshold chosen from the data.`,
        code: 'CalibrateRegion',
        metric: formatNumber(t.value.calibration_factors),
        metricLabel: 'fitted factors',
    },
    {
        icon: ShieldCheck,
        title: 'Validate before publishing',
        body: `Every ${Math.round(1 / props.config.holdout_fraction)}th overlap day is held out. Factors are fitted without it and scored against it, beside the naive merge, so the improvement is measured, not asserted.`,
        code: 'Statistics::errors · interleaved holdout',
        metric:
            improvement.value === null
                ? 'n/a'
                : `${formatNumber(improvement.value, 0)}%`,
        metricLabel: 'less error than a naive merge',
    },
    {
        icon: CalendarRange,
        title: 'Calendar and anomalies',
        body: `Harmonized counts roll up into day, week and month calendars. Each period is compared with the same period in other years using median and MAD, and flagged from z ≥ ${props.config.thresholds.watch}.`,
        code: 'HarmonizeRegion · CriticalPeriods',
        metric: compact(t.value.calendar_entries),
        metricLabel: 'calendar entries',
    },
    {
        icon: Code2,
        title: 'Serve with a receipt',
        body: 'The same numbers power this site and a public JSON API. Every export carries a method receipt: sensors, versions, factors, uncertainty and the holdout score behind them.',
        code: 'Api\\V1 · MethodReceipt',
        metric: formatNumber(t.value.alerts),
        metricLabel: 'unusual periods flagged',
    },
]);

const layers: ArchitectureLayer[] = [
    {
        name: 'NASA sources',
        nodes: [
            {
                icon: Satellite,
                title: 'FIRMS Area API',
                detail: 'MODIS and VIIRS archive plus near-real-time, per box and day range.',
            },
            {
                icon: Radar,
                title: 'FIRMS global feeds',
                detail: 'Key-free 24 h to 7 d CSVs, pulled daily at 03:00 UTC.',
            },
            {
                icon: Globe2,
                title: 'NASA GIBS',
                detail: 'VIIRS true-colour imagery for the selected day on every map.',
            },
        ],
    },
    {
        name: 'Ingest',
        nodes: [
            {
                icon: Workflow,
                title: 'Queue worker',
                detail: 'Batched windows on a priority lane, budget-aware, resumable.',
            },
            {
                icon: Timer,
                title: 'Scheduler',
                detail: 'Daily ingest and harmonization, cache warming every 5 min.',
            },
        ],
    },
    {
        name: 'Store',
        nodes: [
            {
                icon: Database,
                title: 'PostgreSQL 16',
                detail: 'Detections, cell-days, calendars and factors. Composite indexes per query shape.',
            },
        ],
    },
    {
        name: 'Harmonize',
        nodes: [
            {
                icon: FlaskConical,
                title: 'Calibration engine',
                detail: 'Ratio and FRP threshold transfers, bootstrap intervals, holdout scoring.',
            },
            {
                icon: BadgeCheck,
                title: 'Anomaly screen',
                detail: 'Robust z-scores against climatology by week and month.',
            },
        ],
    },
    {
        name: 'Serve',
        nodes: [
            {
                icon: ServerCog,
                title: 'Laravel 13 + Inertia',
                detail: 'Vue 3 pages, MapLibre maps, ECharts, stale-while-revalidate caches.',
            },
            {
                icon: Cloud,
                title: 'Public API',
                detail: 'Regions, calendars, calibration, alerts, hotspots and CSV exports.',
            },
        ],
    },
];

const decisions: {
    icon: typeof Gauge;
    title: string;
    body: string;
    before: string | null;
    after: string;
    label: string;
}[] = [
    {
        icon: Gauge,
        title: 'Heavy aggregates never block a visitor',
        body: 'Global totals scan millions of cell-days. They are rewritten as hash aggregates, served stale-while-revalidate and warmed on a schedule.',
        before: '12.8 s',
        after: '0.7 s',
        label: 'home page on the production server',
    },
    {
        icon: Layers,
        title: 'Indexes shaped like the questions',
        body: 'The hotspot map asks for one day, one sensor and a box. A composite index with the coordinates lets Postgres reject cells without reading them.',
        before: '34k',
        after: '2k',
        label: 'rows read per map request',
    },
    {
        icon: Boxes,
        title: 'Progress counted, not simulated',
        body: 'The data intake panel counts window keys against real coverage instead of instantiating thousands of queued jobs on every poll.',
        before: '8.3 s',
        after: '62 ms',
        label: 'per 20-second dashboard poll',
    },
    {
        icon: ShieldCheck,
        title: 'Guard rails over good intentions',
        body: 'A second backfill for the same area is refused, builds never delete live assets, and every change passes the test suite and static analysis first.',
        before: null,
        after: '250+ tests',
        label: 'plus PHPStan level 7 on every change',
    },
];
</script>

<template>
    <Head title="How it works">
        <meta
            name="description"
            content="How Continuum turns MODIS and VIIRS fire detections into one validated record: pipeline, calibration evidence and architecture, with live numbers."
        />
    </Head>

    <div
        class="engineering mx-auto flex w-full max-w-[1400px] flex-col gap-24 px-4 py-10 md:px-8 md:py-14"
    >
        <!-- Hero: the promise on the left, the live system on the right. -->
        <section
            class="grid items-center gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]"
        >
            <div v-reveal class="max-w-xl">
                <p class="text-sm font-medium text-lime-600 dark:text-lime-400">
                    How it works
                </p>
                <h1
                    class="mt-3 text-4xl leading-[1.05] font-semibold tracking-tighter text-balance md:text-5xl lg:text-6xl"
                >
                    Two satellites. One record you can check.
                </h1>
                <p
                    class="mt-5 max-w-[46ch] text-base leading-relaxed text-muted-foreground md:text-lg"
                >
                    The pipeline, the evidence and the machine behind Continuum,
                    with every number read live from the system.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <Button as-child size="lg" class="active:scale-[0.98]">
                        <a href="#pipeline"
                            >See the pipeline <ArrowRight class="size-4"
                        /></a>
                    </Button>
                    <Button
                        as-child
                        size="lg"
                        variant="outline"
                        class="active:scale-[0.98]"
                    >
                        <Link :href="method()">Read the method</Link>
                    </Button>
                </div>
            </div>

            <figure v-if="f" v-reveal="120" class="min-w-0">
                <div
                    class="overflow-hidden rounded-2xl border border-border bg-card shadow-[0_30px_80px_-40px_rgb(56_189_248/0.45)]"
                >
                    <FireMap
                        :bbox="f.bbox"
                        :date="f.map_date"
                        height="h-[340px] md:h-[440px]"
                    />
                </div>
                <figcaption class="mt-3 text-sm text-muted-foreground">
                    {{ shortName }} on {{ formatDate(f.map_date) }}: MODIS and
                    VIIRS cells, live from our API over NASA GIBS imagery.
                </figcaption>
            </figure>
        </section>

        <!-- The scale, in four numbers. -->
        <section
            aria-label="What the system has processed"
            class="grid grid-cols-2 gap-y-10 border-y border-border py-10 lg:grid-cols-4"
        >
            <div v-reveal class="px-2 lg:border-r lg:border-border lg:px-6">
                <p
                    class="font-mono text-4xl font-semibold tracking-tight md:text-5xl"
                >
                    <CountUp :value="t.detections" :format="compact" />
                </p>
                <p class="mt-2 text-sm text-muted-foreground">
                    fire detections ingested
                </p>
            </div>
            <div
                v-reveal="80"
                class="px-2 lg:border-r lg:border-border lg:px-6"
            >
                <p
                    class="font-mono text-4xl font-semibold tracking-tight md:text-5xl"
                >
                    <CountUp :value="years ?? 0" :format="whole" />
                    <span class="text-2xl text-muted-foreground"> yrs</span>
                </p>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ formatDate(t.first_date) }} to
                    {{ formatDate(t.last_date) }}
                </p>
            </div>
            <div
                v-reveal="160"
                class="px-2 lg:border-r lg:border-border lg:px-6"
            >
                <p
                    class="font-mono text-4xl font-semibold tracking-tight md:text-5xl"
                >
                    <CountUp :value="t.satellites" :format="whole" />
                </p>
                <p class="mt-2 text-sm text-muted-foreground">
                    satellites, two instruments
                </p>
            </div>
            <div v-reveal="240" class="px-2 lg:px-6">
                <p
                    class="font-mono text-4xl font-semibold tracking-tight md:text-5xl"
                >
                    ×<CountUp
                        :value="inflation ?? 0"
                        :format="(v) => formatNumber(v, 1)"
                    />
                </p>
                <p class="mt-2 text-sm text-muted-foreground">
                    VIIRS pixels per MODIS pixel, before harmonizing
                </p>
            </div>
        </section>

        <!-- Pipeline -->
        <section id="pipeline" class="scroll-mt-20">
            <div v-reveal class="mb-12 max-w-2xl">
                <h2 class="text-3xl font-semibold tracking-tight md:text-4xl">
                    From a CSV row to a calendar you can trust
                </h2>
                <p class="mt-4 text-base leading-relaxed text-muted-foreground">
                    Seven stages, each one a class you can read in the
                    repository, each one reporting what it has done so far.
                </p>
            </div>
            <PipelineTimeline :stages="stages" />
        </section>

        <!-- Evidence -->
        <section v-if="f">
            <div v-reveal class="mb-10 max-w-2xl">
                <h2 class="text-3xl font-semibold tracking-tight md:text-4xl">
                    The evidence, not the claim
                </h2>
                <p class="mt-4 text-base leading-relaxed text-muted-foreground">
                    {{ shortName }} has the longest calibrated overlap of the
                    public areas. This is what its transfer functions rest on.
                </p>
            </div>

            <div class="grid gap-4 lg:grid-cols-6 lg:grid-rows-[auto_auto]">
                <article
                    v-reveal
                    class="rounded-2xl border border-border bg-card p-5 lg:col-span-4 lg:row-span-2"
                >
                    <h3 class="font-semibold">Same day, both sensors</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ formatNumber(f.pairs.length) }} overlap days. The
                        fitted ratio is {{ formatNumber(f.ratio.value, 3) }},
                        95% interval {{ formatNumber(f.ratio.ci95[0], 3) }} to
                        {{ formatNumber(f.ratio.ci95[1], 3) }}.
                    </p>
                    <div class="mt-4 h-[360px] lg:h-[460px]">
                        <OverlapScatterChart
                            :pairs="f.pairs"
                            :ratio="f.ratio.value"
                        />
                    </div>
                </article>

                <article
                    v-reveal="100"
                    class="relative overflow-hidden rounded-2xl border border-lime-500/30 bg-gradient-to-br from-lime-500/15 via-card to-sky-500/10 p-6 lg:col-span-2"
                >
                    <p
                        class="text-sm font-medium text-lime-700 dark:text-lime-400"
                    >
                        On held-out days
                    </p>
                    <p
                        class="mt-3 font-mono text-6xl font-semibold tracking-tighter"
                    >
                        <template v-if="improvement !== null">
                            <CountUp :value="improvement" :format="whole" />%
                        </template>
                        <template v-else>n/a</template>
                    </p>
                    <p
                        class="mt-2 text-sm leading-relaxed text-muted-foreground"
                    >
                        less absolute error than merging the two records
                        naively.
                    </p>
                    <dl
                        v-if="ratioValidation"
                        class="mt-6 grid grid-cols-2 gap-4 text-sm"
                    >
                        <div>
                            <dt class="text-muted-foreground">
                                Mean abs. error
                            </dt>
                            <dd class="font-mono font-semibold">
                                {{ formatNumber(ratioValidation.mae, 1) }}
                                <span class="font-normal text-muted-foreground"
                                    >vs
                                    {{
                                        formatNumber(
                                            ratioValidation.naive_mae,
                                            1,
                                        )
                                    }}</span
                                >
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">R²</dt>
                            <dd class="font-mono font-semibold">
                                {{ formatNumber(ratioValidation.r2, 2) }}
                                <span class="font-normal text-muted-foreground"
                                    >vs
                                    {{
                                        formatNumber(
                                            ratioValidation.naive_r2,
                                            2,
                                        )
                                    }}</span
                                >
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Fitted on</dt>
                            <dd class="font-mono font-semibold">
                                {{ formatNumber(ratioValidation.train_days) }}
                                days
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Scored on</dt>
                            <dd class="font-mono font-semibold">
                                {{ formatNumber(ratioValidation.test_days) }}
                                days
                            </dd>
                        </div>
                    </dl>
                </article>

                <article
                    v-reveal="180"
                    class="rounded-2xl border border-border bg-card p-5 lg:col-span-2"
                >
                    <h3 class="font-semibold">Choosing the FRP threshold</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Error of every candidate. The minimum is
                        <span class="font-mono text-foreground">{{
                            f.threshold
                                ? formatNumber(f.threshold.value, 0) + ' MW'
                                : 'n/a'
                        }}</span
                        >.
                    </p>
                    <div class="mt-3 h-[220px]">
                        <ThresholdCurveChart
                            :curve="f.threshold_curve"
                            :chosen="f.threshold?.value ?? null"
                        />
                    </div>
                </article>
            </div>

            <p v-reveal class="mt-4 text-sm text-muted-foreground">
                Explore every factor, month by month, in the
                <Link
                    :href="lab(f.slug)"
                    class="font-medium text-foreground underline underline-offset-4"
                    >calibration lab</Link
                >
                or open the
                <Link
                    :href="show(f.slug)"
                    class="font-medium text-foreground underline underline-offset-4"
                    >{{ shortName }} calendar</Link
                >.
            </p>
        </section>

        <!-- Architecture -->
        <section
            class="rounded-3xl border border-border bg-gradient-to-b from-muted/60 to-background p-6 md:p-10"
        >
            <div v-reveal class="mb-10 max-w-2xl">
                <h2 class="text-3xl font-semibold tracking-tight md:text-4xl">
                    One service, end to end
                </h2>
                <p class="mt-4 text-base leading-relaxed text-muted-foreground">
                    Open NASA data in, a validated record out. Everything runs
                    on a single small server behind Cloudflare.
                </p>
            </div>
            <ArchitectureFlow :layers="layers" />
        </section>

        <!-- Engineering decisions -->
        <section>
            <div v-reveal class="mb-10 max-w-2xl">
                <h2 class="text-3xl font-semibold tracking-tight md:text-4xl">
                    Built to keep running
                </h2>
                <p class="mt-4 text-base leading-relaxed text-muted-foreground">
                    Measured on the production server while a 26-year backfill
                    was streaming in.
                </p>
            </div>
            <div class="grid gap-x-10 gap-y-12 md:grid-cols-2">
                <article
                    v-for="(decision, index) in decisions"
                    :key="decision.title"
                    v-reveal="index * 80"
                    class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4"
                >
                    <component
                        :is="decision.icon"
                        class="mt-1 size-6 text-lime-600 dark:text-lime-400"
                        :stroke-width="1.75"
                    />
                    <div>
                        <h3 class="text-lg font-semibold tracking-tight">
                            {{ decision.title }}
                        </h3>
                        <p
                            class="mt-2 max-w-[52ch] text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ decision.body }}
                        </p>
                        <p class="mt-4 flex items-baseline gap-3 font-mono">
                            <template v-if="decision.before">
                                <span
                                    class="text-lg text-muted-foreground line-through decoration-1"
                                    >{{ decision.before }}</span
                                >
                                <ArrowRight
                                    class="size-4 self-center text-muted-foreground"
                                />
                            </template>
                            <span class="text-2xl font-semibold">{{
                                decision.after
                            }}</span>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ decision.label }}
                        </p>
                    </div>
                </article>
            </div>
        </section>

        <!-- Close -->
        <section
            v-reveal
            class="grid items-center gap-8 rounded-3xl border border-lime-500/30 bg-gradient-to-br from-lime-500/15 via-card to-sky-500/10 p-8 md:grid-cols-[minmax(0,1fr)_auto] md:p-12"
        >
            <div>
                <h2 class="text-3xl font-semibold tracking-tight md:text-4xl">
                    Every number here is an API call away.
                </h2>
                <p class="mt-3 max-w-[55ch] text-muted-foreground">
                    Calendars, calibration factors, alerts and hotspots for
                    {{ t.public_areas }} public areas, open and documented, each
                    with its method receipt.
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <Button as-child size="lg" class="active:scale-[0.98]">
                    <Link :href="apiDocs()"
                        >Explore the API <ArrowRight class="size-4"
                    /></Link>
                </Button>
            </div>
        </section>
    </div>
</template>

<style scoped>
.engineering :deep([data-reveal='hidden']) {
    opacity: 0;
    transform: translateY(24px);
}

.engineering :deep([data-reveal='shown']) {
    opacity: 1;
    transform: none;
    transition:
        opacity 0.7s cubic-bezier(0.16, 1, 0.3, 1) var(--reveal-delay, 0ms),
        transform 0.7s cubic-bezier(0.16, 1, 0.3, 1) var(--reveal-delay, 0ms);
}
</style>

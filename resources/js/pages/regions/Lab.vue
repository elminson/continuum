<script setup lang="ts">
import { Deferred, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import DetectionCurveChart from '@/components/continuum/DetectionCurveChart.vue';
import FactorTable from '@/components/continuum/FactorTable.vue';
import KpiTile from '@/components/continuum/KpiTile.vue';
import OverlapScatterChart from '@/components/continuum/OverlapScatterChart.vue';
import SectionCard from '@/components/continuum/SectionCard.vue';
import ThresholdCurveChart from '@/components/continuum/ThresholdCurveChart.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { formatNumber, formatPercent } from '@/lib/format';
import { home } from '@/routes';
import { index, lab, show } from '@/routes/regions';
import type {
    DetectionCurve,
    Factor,
    OverlapPair,
    Region,
    ThresholdPoint,
} from '@/types/continuum';

const props = defineProps<{
    region: Region;
    pairs: OverlapPair[];
    factors: Factor[];
    thresholdCurve: ThresholdPoint[];
    detectionCurve?: DetectionCurve;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Overview', href: home() },
        { title: 'Areas of interest', href: index() },
        {
            title: props.region.name,
            href: show(props.region.slug),
        },
        { title: 'Harmonization lab', href: lab(props.region.slug) },
    ],
});

const ratio = computed(
    () =>
        props.factors.find(
            (f) =>
                f.method === 'ratio' &&
                f.reference === 'modis' &&
                f.month === null,
        ) ?? null,
);
const threshold = computed(
    () =>
        props.factors.find(
            (f) =>
                f.method === 'frp_threshold' &&
                f.reference === 'modis' &&
                f.month === null,
        ) ?? null,
);
const sums = computed(() => ({
    modis: props.pairs.reduce((s, p) => s + p.modis, 0),
    viirs: props.pairs.reduce((s, p) => s + p.viirs, 0),
}));
const best = computed(() =>
    props.thresholdCurve.length > 0
        ? props.thresholdCurve.reduce((a, b) => (b.mae < a.mae ? b : a))
        : null,
);
</script>

<template>
    <Head :title="`Lab · ${region.name}`" />

    <div class="flex flex-col gap-4 p-4">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">
                    Harmonization lab · {{ region.name }}
                </h1>
                <p class="max-w-3xl text-sm text-muted-foreground">
                    Everything on this page is computed from the days both
                    instruments observed the area. It shows why a naive merge
                    fails, how each transfer function is fitted, and how well it
                    does on days it never saw.
                </p>
            </div>
            <Button variant="outline" size="sm" as-child>
                <Link :href="show(region.slug)"
                    ><ArrowLeft class="size-4" /> Calendar</Link
                >
            </Button>
        </header>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <KpiTile
                label="Overlap days"
                :value="String(pairs.length)"
                hint="days with both MODIS and VIIRS coverage"
            />
            <KpiTile
                label="VIIRS ÷ MODIS cell-days"
                :value="
                    sums.modis > 0
                        ? `×${formatNumber(sums.viirs / sums.modis, 2)}`
                        : '—'
                "
                hint="after collapsing 375 m pixels to 1 km cells"
                accent="#f97316"
            />
            <KpiTile
                label="Ratio transfer"
                :value="ratio ? `×${formatNumber(ratio.value, 3)}` : '—'"
                :hint="
                    ratio?.validation
                        ? `holdout error −${formatPercent(ratio.validation.mae_improvement)} vs naive`
                        : 'not calibrated'
                "
                accent="#38bdf8"
            />
            <KpiTile
                label="FRP visibility threshold"
                :value="
                    threshold ? `${formatNumber(threshold.value, 1)} MW` : '—'
                "
                :hint="
                    threshold?.validation
                        ? `holdout error −${formatPercent(threshold.validation.mae_improvement)} vs naive`
                        : 'not calibrated'
                "
                accent="#a3e635"
            />
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <SectionCard
                title="Same day, two sensors"
                description="Daily active cell-days. Points off the 1:1 line are the harmonization problem; the fitted line is the ratio transfer."
            >
                <OverlapScatterChart
                    :pairs="pairs"
                    :ratio="ratio?.value ?? null"
                />
            </SectionCard>
            <SectionCard
                title="Choosing the FRP visibility threshold"
                description="Count only VIIRS cell-days whose summed FRP reaches τ; the τ with the lowest daily error against MODIS wins."
            >
                <ThresholdCurveChart
                    :curve="thresholdCurve"
                    :chosen="threshold?.value ?? null"
                />
                <p v-if="best" class="mt-2 text-xs text-muted-foreground">
                    Best τ on all overlap days: {{ best.tau }} MW (MAE
                    {{ formatNumber(best.mae, 1) }} cell-days/day; Σ predicted
                    {{ formatNumber(best.predicted) }} vs Σ MODIS
                    {{ formatNumber(best.observed) }}).
                </p>
            </SectionCard>
        </div>

        <SectionCard
            title="Why a threshold is physical"
            description="For every VIIRS cell-day, did MODIS detect the same 1 km cell the same day? Detection probability rises with fire radiative power."
        >
            <Deferred data="detectionCurve">
                <template #fallback>
                    <Skeleton class="h-72 w-full" />
                </template>
                <div v-if="detectionCurve">
                    <DetectionCurveChart
                        :curve="detectionCurve"
                        :chosen="threshold?.value ?? null"
                    />
                    <p class="mt-2 text-xs text-muted-foreground">
                        Last {{ detectionCurve.days }} overlap day(s):
                        {{ formatNumber(detectionCurve.both) }} cell-days seen
                        by both,
                        {{ formatNumber(detectionCurve.viirs_only) }} by VIIRS
                        only (small or cool fires below MODIS sensitivity),
                        {{ formatNumber(detectionCurve.modis_only) }} by MODIS
                        only (different overpass times, clouds, Terra's morning
                        orbit).
                    </p>
                </div>
            </Deferred>
        </SectionCard>

        <SectionCard
            title="All fitted factors"
            description="Per-month factors are used when a month has enough overlap days; otherwise the all-year factor applies."
        >
            <FactorTable :factors="factors" />
        </SectionCard>
    </div>
</template>

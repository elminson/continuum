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
    <Head :title="$t('Lab · :name', { name: region.name })" />

    <div class="flex flex-col gap-4 p-4">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">
                    {{ $t('Harmonization lab · :name', { name: region.name }) }}
                </h1>
                <p class="max-w-3xl text-sm text-muted-foreground">
                    {{
                        $t(
                            'Everything on this page is computed from the days both instruments observed the area. It shows why a naive merge fails, how each transfer function is fitted, and how well it does on days it never saw.',
                        )
                    }}
                </p>
            </div>
            <Button variant="outline" size="sm" as-child>
                <Link :href="show(region.slug)"
                    ><ArrowLeft class="size-4" /> {{ $t('Calendar') }}</Link
                >
            </Button>
        </header>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <KpiTile
                :label="$t('Overlap days')"
                :value="String(pairs.length)"
                :hint="$t('days with both MODIS and VIIRS coverage')"
            />
            <KpiTile
                :label="$t('VIIRS ÷ MODIS cell-days')"
                :value="
                    sums.modis > 0
                        ? `×${formatNumber(sums.viirs / sums.modis, 2)}`
                        : '—'
                "
                :hint="$t('after collapsing 375 m pixels to 1 km cells')"
                accent="#f97316"
            />
            <KpiTile
                :label="$t('Ratio transfer')"
                :value="ratio ? `×${formatNumber(ratio.value, 3)}` : '—'"
                :hint="
                    ratio?.validation
                        ? $t('holdout error −:percent vs naive', {
                              percent: formatPercent(
                                  ratio.validation.mae_improvement,
                              ),
                          })
                        : $t('not calibrated')
                "
                accent="#3b82f6"
            />
            <KpiTile
                :label="$t('FRP visibility threshold')"
                :value="
                    threshold ? `${formatNumber(threshold.value, 1)} MW` : '—'
                "
                :hint="
                    threshold?.validation
                        ? $t('holdout error −:percent vs naive', {
                              percent: formatPercent(
                                  threshold.validation.mae_improvement,
                              ),
                          })
                        : $t('not calibrated')
                "
                accent="#4ade80"
            />
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <SectionCard
                :title="$t('Same day, two sensors')"
                :description="
                    $t(
                        'Daily active cell-days. Points off the 1:1 line are the harmonization problem; the fitted line is the ratio transfer.',
                    )
                "
            >
                <OverlapScatterChart
                    :pairs="pairs"
                    :ratio="ratio?.value ?? null"
                />
            </SectionCard>
            <SectionCard
                :title="$t('Choosing the FRP visibility threshold')"
                :description="
                    $t(
                        'Count only VIIRS cell-days whose summed FRP reaches τ; the τ with the lowest daily error against MODIS wins.',
                    )
                "
            >
                <ThresholdCurveChart
                    :curve="thresholdCurve"
                    :chosen="threshold?.value ?? null"
                />
                <p v-if="best" class="mt-2 text-xs text-muted-foreground">
                    {{
                        $t(
                            'Best τ on all overlap days: :tau MW (MAE :mae cell-days/day; Σ predicted :predicted vs Σ MODIS :observed).',
                            {
                                tau: best.tau,
                                mae: formatNumber(best.mae, 1),
                                predicted: formatNumber(best.predicted),
                                observed: formatNumber(best.observed),
                            },
                        )
                    }}
                </p>
            </SectionCard>
        </div>

        <SectionCard
            :title="$t('Why a threshold is physical')"
            :description="
                $t(
                    'For every VIIRS cell-day, did MODIS detect the same 1 km cell the same day? Detection probability rises with fire radiative power.',
                )
            "
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
                        {{
                            $t(
                                "Last :days overlap day(s): :both cell-days seen by both, :viirs by VIIRS only (small or cool fires below MODIS sensitivity), :modis by MODIS only (different overpass times, clouds, Terra's morning orbit).",
                                {
                                    days: detectionCurve.days,
                                    both: formatNumber(detectionCurve.both),
                                    viirs: formatNumber(
                                        detectionCurve.viirs_only,
                                    ),
                                    modis: formatNumber(
                                        detectionCurve.modis_only,
                                    ),
                                },
                            )
                        }}
                    </p>
                </div>
            </Deferred>
        </SectionCard>

        <SectionCard
            :title="$t('All fitted factors')"
            :description="
                $t(
                    'Per-month factors are used when a month has enough overlap days; otherwise the all-year factor applies.',
                )
            "
        >
            <FactorTable :factors="factors" />
        </SectionCard>
    </div>
</template>

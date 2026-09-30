<script setup lang="ts">
import { Deferred, Head, Link, usePoll } from '@inertiajs/vue3';
import { ArrowRight, BookOpenText, Code2, Flame, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import AlertList from '@/components/continuum/AlertList.vue';
import DataIntakePanel from '@/components/continuum/DataIntakePanel.vue';
import FireMap from '@/components/continuum/FireMap.vue';
import InstrumentDot from '@/components/continuum/InstrumentDot.vue';
import KpiTile from '@/components/continuum/KpiTile.vue';
import RegionCard from '@/components/continuum/RegionCard.vue';
import SectionCard from '@/components/continuum/SectionCard.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Button } from '@/components/ui/button';
import { formatCompact, formatDate, formatNumber } from '@/lib/format';
import { apiDocs, home, method } from '@/routes';
import { create, show } from '@/routes/regions';
import type {
    DataIntake,
    FireAlert,
    GlobalSummary,
    Region,
} from '@/types/continuum';

const props = defineProps<{
    global: GlobalSummary;
    regions: Region[];
    alerts: FireAlert[];
    intake?: DataIntake;
}>();

usePoll(20_000, { only: ['intake'] }, { keepAlive: true });

defineOptions({
    layout: { breadcrumbs: [{ title: 'Overview', href: home() }] },
});

const featured = computed(
    () =>
        props.regions.find((region) => region.summary?.last_active_date) ??
        props.regions.find((region) => region.summary?.last_date) ??
        props.regions[0] ??
        null,
);
const featuredDate = computed(
    () =>
        featured.value?.summary?.last_active_date ??
        featured.value?.summary?.last_date ??
        null,
);
const mapMode = ref<'both' | 'modis' | 'viirs'>('both');
const mapCounts = ref({ modis: 0, viirs: 0 });

const pixelRatio = computed(() =>
    props.global.modis.detections > 0
        ? props.global.viirs.detections / props.global.modis.detections
        : null,
);
const cellRatio = computed(() =>
    props.global.modis.cell_days > 0
        ? props.global.viirs.cell_days / props.global.modis.cell_days
        : null,
);
</script>

<template>
    <Head title="Overview" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <section
            class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-gradient-to-br from-orange-500/10 via-card to-sky-500/10 p-6 dark:border-sidebar-border"
        >
            <div class="max-w-3xl">
                <p
                    class="text-xs font-semibold tracking-widest text-orange-500 uppercase"
                >
                    NASA Space Apps 2026 · Harmonization of MODIS and VIIRS hot
                    spots
                </p>
                <h1
                    class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl"
                >
                    One continuous burning-activity record, from 2000 to today.
                </h1>
                <p class="mt-2 text-sm text-muted-foreground sm:text-base">
                    MODIS (1 km, since 2000) and VIIRS (375 m, since 2012) count
                    the same fire differently: a single 1 km MODIS pixel becomes
                    3–7 VIIRS pixels, and VIIRS sees fires MODIS cannot.
                    Continuum snaps both records to one 1 km grid, calibrates a
                    physically-grounded transfer on the years both sensors flew,
                    and publishes a validated calendar with uncertainty for any
                    area of interest.
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <Button v-if="featured" as-child>
                        <Link :href="show(featured.slug)"
                            >Open {{ featured.name.split(' (')[0] }}
                            <ArrowRight class="size-4"
                        /></Link>
                    </Button>
                    <Button variant="outline" as-child>
                        <Link :href="method()"
                            ><BookOpenText class="size-4" /> Method</Link
                        >
                    </Button>
                    <Button variant="outline" as-child>
                        <Link :href="apiDocs()"
                            ><Code2 class="size-4" /> API</Link
                        >
                    </Button>
                    <Button
                        v-if="$page.props.auth.user"
                        variant="ghost"
                        as-child
                    >
                        <Link :href="create()"
                            ><Plus class="size-4" /> New area of interest</Link
                        >
                    </Button>
                </div>
            </div>
            <Flame
                class="pointer-events-none absolute -right-6 -bottom-8 size-48 text-orange-500/10"
            />
        </section>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <KpiTile
                label="Detections ingested"
                :value="formatCompact(global.detections)"
                :hint="`${formatCompact(global.modis.detections)} MODIS · ${formatCompact(global.viirs.detections)} VIIRS`"
            />
            <KpiTile
                label="1 km cell-days"
                :value="formatCompact(global.cell_days)"
                :hint="`${formatCompact(global.modis.cell_days)} MODIS · ${formatCompact(global.viirs.cell_days)} VIIRS`"
            />
            <KpiTile
                label="Raw pixel inflation"
                :value="pixelRatio ? `×${formatNumber(pixelRatio, 1)}` : '—'"
                hint="VIIRS pixels per MODIS pixel, before harmonization"
                accent="#f43f5e"
            />
            <KpiTile
                label="After 1 km gridding"
                :value="cellRatio ? `×${formatNumber(cellRatio, 1)}` : '—'"
                hint="what remains is sensitivity, not pixel splitting"
                accent="#a3e635"
            />
            <KpiTile
                label="Record"
                :value="global.days ? `${global.days} days` : '—'"
                :hint="
                    global.first_date
                        ? `${formatDate(global.first_date)} → ${formatDate(global.last_date)} · ${global.modis.satellites + global.viirs.satellites} satellites`
                        : 'no data ingested yet'
                "
            />
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <SectionCard
                v-if="featured && featuredDate"
                class="xl:col-span-2"
                :title="`Latest day with fire activity · ${featured.name}`"
                :description="`${formatDate(featuredDate)} · both sensors on the same 1 km grid`"
            >
                <template #actions>
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
                    :bbox="featured.bbox"
                    :date="featuredDate"
                    :mode="mapMode"
                    height="h-[440px]"
                    @loaded="mapCounts = $event"
                />
                <p class="mt-2 text-xs text-muted-foreground">
                    <InstrumentDot
                        instrument="modis"
                        :label="`MODIS ${formatNumber(mapCounts.modis)} cells`"
                    />
                    ·
                    <InstrumentDot
                        instrument="viirs"
                        :label="`VIIRS ${formatNumber(mapCounts.viirs)} cells`"
                    />
                    — same day, same grid: the VIIRS surplus that survives
                    gridding is the sensitivity gap the transfer function has to
                    close.
                </p>
            </SectionCard>

            <SectionCard
                title="Unusual periods"
                description="Robust z-scores against the same week or month in other years"
            >
                <AlertList :alerts="alerts" show-region />
            </SectionCard>
        </div>

        <SectionCard
            title="Areas of interest"
            description="Presets ship with real NASA FIRMS samples; sign in to add your own."
        >
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <RegionCard
                    v-for="region in regions"
                    :key="region.id"
                    :region="region"
                />
            </div>
        </SectionCard>

        <SectionCard
            title="Data intake"
            description="How much NASA FIRMS data the public areas have pulled, and what is still arriving"
        >
            <Deferred data="intake">
                <template #fallback>
                    <div class="space-y-3">
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <Skeleton
                                v-for="index in 4"
                                :key="index"
                                class="h-20 rounded-lg"
                            />
                        </div>
                        <Skeleton class="h-40 rounded-lg" />
                    </div>
                </template>
                <DataIntakePanel v-if="intake" :intake="intake" />
            </Deferred>
        </SectionCard>
    </div>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AlertList from '@/components/continuum/AlertList.vue';
import AlertsMap from '@/components/continuum/AlertsMap.vue';
import SectionCard from '@/components/continuum/SectionCard.vue';
import { home } from '@/routes';
import { index } from '@/routes/alerts';
import type { AlertMapRegion, FireAlert, Paginated } from '@/types/continuum';

const props = defineProps<{
    alerts: Paginated<FireAlert>;
    filters: { severity: string | null; region: string | null };
    regions: { slug: string; name: string; color: string }[];
    map: AlertMapRegion[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Overview', href: home() },
            { title: 'Unusual periods', href: index() },
        ],
    },
});

const severity = ref(props.filters.severity ?? '');
const region = ref(props.filters.region ?? '');

watch([severity, region], () => {
    router.get(
        index().url,
        {
            severity: severity.value || undefined,
            region: region.value || undefined,
        },
        { preserveState: true, replace: true },
    );
});
</script>

<template>
    <Head title="Unusual periods" />

    <div class="flex flex-col gap-4 p-4">
        <header>
            <h1 class="text-xl font-semibold">Unusual periods</h1>
            <p class="text-sm text-muted-foreground">
                Weeks and months whose harmonized activity departs from the same
                period in other years (median ± MAD).
            </p>
        </header>

        <div class="flex flex-wrap gap-3">
            <select
                v-model="severity"
                class="h-8 rounded-md border border-input bg-background px-2 text-sm"
            >
                <option value="">All severities</option>
                <option value="watch">Watch</option>
                <option value="warning">Warning</option>
                <option value="critical">Critical</option>
            </select>
            <select
                v-model="region"
                class="h-8 rounded-md border border-input bg-background px-2 text-sm"
            >
                <option value="">All areas</option>
                <option v-for="r in regions" :key="r.slug" :value="r.slug">
                    {{ r.name }}
                </option>
            </select>
        </div>

        <SectionCard
            title="Where"
            description="Each area is coloured by its worst flagged period; click one to filter the list."
        >
            <AlertsMap
                :regions="map"
                :selected="region || null"
                @select="(slug) => (region = slug ?? '')"
            />
        </SectionCard>

        <SectionCard :title="`${alerts.meta.total} flagged period(s)`">
            <AlertList :alerts="alerts.data" show-region />
            <nav
                v-if="alerts.meta.last_page > 1"
                class="mt-3 flex flex-wrap gap-1 text-xs"
            >
                <template v-for="link in alerts.meta.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded border px-2 py-1"
                        :class="
                            link.active
                                ? 'bg-foreground text-background'
                                : 'hover:bg-muted'
                        "
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="rounded border px-2 py-1 text-muted-foreground"
                        v-html="link.label"
                    />
                </template>
            </nav>
        </SectionCard>
    </div>
</template>

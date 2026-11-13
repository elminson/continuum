<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import SectionCard from '@/components/continuum/SectionCard.vue';
import { apiDocs, home } from '@/routes';

const props = defineProps<{
    baseUrl: string;
    exampleSlug: string;
    exampleBbox: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Overview', href: home() },
            { title: 'API', href: apiDocs() },
        ],
    },
});

const endpoints = [
    {
        method: 'GET',
        path: '/regions',
        description: 'Preset areas of interest with links.',
    },
    {
        method: 'GET',
        path: '/regions/{slug}',
        description:
            'One area with its summary (recent activity, calibration status, alerts).',
    },
    {
        method: 'GET',
        path: '/regions/{slug}/calendar',
        description:
            'Harmonized calendar. Query: period=day|week|month, method=ratio|frp_threshold, reference=modis|viirs, from, to. Includes native MODIS/VIIRS values, estimated flags, intervals, naive merge and the method receipt.',
    },
    {
        method: 'GET',
        path: '/regions/{slug}/calibration',
        description:
            'All fitted transfer factors with intervals and held-out validation.',
    },
    {
        method: 'GET',
        path: '/regions/{slug}/critical-periods',
        description:
            "Climatological season, peak, onset and the latest period's position. Query: period, method, reference.",
    },
    {
        method: 'GET',
        path: '/regions/{slug}/alerts',
        description: 'Unusual periods (paginated). Query: period.',
    },
    {
        method: 'GET',
        path: '/regions/{slug}/export',
        description:
            'CSV download of the harmonized calendar with the receipt as comment lines. Same query as /calendar.',
    },
    {
        method: 'GET',
        path: '/hotspots',
        description:
            'GeoJSON of 1 km cell-days (or raw pixels with raw=1) for one day. Query: bbox=w,s,e,n (required), date=YYYY-MM-DD (required), instrument, limit (≤ 20000).',
    },
];

const example = `${props.baseUrl}/regions/${props.exampleSlug}/calendar?period=month&method=frp_threshold&reference=modis`;
const hotspotExample = `${props.baseUrl}/hotspots?bbox=${props.exampleBbox}&date=2026-09-24&instrument=viirs`;
</script>

<template>
    <Head title="API" />

    <div class="mx-auto flex max-w-4xl flex-col gap-4 p-4">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ $t('Public JSON API') }}
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {{
                    $t(
                        'Read-only, no key required, 120 requests per minute per client.',
                    )
                }}
                {{ $t('Base URL') }}
                <code class="rounded bg-muted px-1">{{ baseUrl }}</code
                >.
                {{
                    $t(
                        'Every response that carries harmonized numbers also carries the method receipt.',
                    )
                }}
            </p>
        </header>

        <SectionCard :title="$t('Endpoints')">
            <table class="w-full text-sm">
                <tbody>
                    <tr
                        v-for="endpoint in endpoints"
                        :key="endpoint.path"
                        class="border-t border-border align-top"
                    >
                        <td
                            class="py-2 pr-3 font-mono text-xs whitespace-nowrap"
                        >
                            <span
                                class="rounded bg-lime-500/15 px-1 text-lime-700 dark:text-lime-400"
                                >{{ endpoint.method }}</span
                            >
                            {{ endpoint.path }}
                        </td>
                        <td class="py-2 text-muted-foreground">
                            {{ $t(endpoint.description) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </SectionCard>

        <SectionCard :title="$t('Examples')">
            <pre
                class="overflow-x-auto rounded-md bg-muted p-3 text-xs"
            ><code>curl "{{ example }}"

curl "{{ hotspotExample }}"

curl -o calendar.csv "{{ baseUrl }}/regions/{{ exampleSlug }}/export?period=week&method=ratio&reference=viirs"</code></pre>
            <p class="mt-2 text-xs text-muted-foreground">
                {{ $t('Calendar points look like') }}
                <code class="rounded bg-muted px-1"
                    >{ period_start, modis: {cell_days, detections, frp_sum,
                    coverage_days…}, viirs: {…}, harmonized, low, high,
                    estimated, source, factor, naive }</code
                >.
            </p>
        </SectionCard>
    </div>
</template>

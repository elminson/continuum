<script setup lang="ts">
import type { Feature, FeatureCollection } from 'geojson';
import {
    GeoJSONSource,
    Map as MapLibreMap,
    NavigationControl,
    Popup,
    setWorkerUrl,
} from 'maplibre-gl';
import type { MapLayerMouseEvent } from 'maplibre-gl';
import mapWorkerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { basemapStyle } from '@/lib/basemap';
import { formatNumber, formatPeriod } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { AlertMapRegion, Severity } from '@/types/continuum';

setWorkerUrl(mapWorkerUrl);

const props = withDefaults(
    defineProps<{
        regions: AlertMapRegion[];
        selected?: string | null;
        height?: string;
    }>(),
    { selected: null, height: 'h-[380px]' },
);

const emit = defineEmits<{ select: [slug: string | null] }>();

const SEVERITY_COLORS: Record<Severity, string> = {
    critical: '#ef4444',
    warning: '#f97316',
    watch: '#eab308',
};

const QUIET = '#64748b';

let map: MapLibreMap | null = null;
let hover: Popup | null = null;

function fillColor(region: AlertMapRegion): string {
    return region.top ? SEVERITY_COLORS[region.top.severity] : QUIET;
}

function worldBounds(
    regions: AlertMapRegion[],
): [[number, number], [number, number]] {
    if (regions.length === 0) {
        return [
            [-170, -60],
            [170, 75],
        ];
    }

    const w = Math.min(...regions.map((r) => r.bbox[0]));
    const s = Math.min(...regions.map((r) => r.bbox[1]));
    const e = Math.max(...regions.map((r) => r.bbox[2]));
    const n = Math.max(...regions.map((r) => r.bbox[3]));

    return [
        [w, s],
        [e, n],
    ];
}

function polygons(): FeatureCollection {
    return {
        type: 'FeatureCollection',
        features: props.regions.map((region): Feature => {
            const [w, s, e, n] = region.bbox;

            return {
                type: 'Feature',
                properties: {
                    slug: region.slug,
                    name: region.name,
                    color: fillColor(region),
                    outline: region.color,
                    total: region.alerts.total,
                    selected: region.slug === props.selected,
                    dimmed:
                        props.selected !== null &&
                        region.slug !== props.selected,
                },
                geometry: {
                    type: 'Polygon',
                    coordinates: [
                        [
                            [w, s],
                            [e, s],
                            [e, n],
                            [w, n],
                            [w, s],
                        ],
                    ],
                },
            };
        }),
    };
}

function markers(): FeatureCollection {
    return {
        type: 'FeatureCollection',
        features: props.regions
            .filter((region) => region.alerts.total > 0)
            .map((region): Feature => ({
                type: 'Feature',
                properties: {
                    slug: region.slug,
                    name: region.name,
                    color: fillColor(region),
                    total: region.alerts.total,
                    label: String(region.alerts.total),
                    dimmed:
                        props.selected !== null &&
                        region.slug !== props.selected,
                },
                geometry: {
                    type: 'Point',
                    coordinates: [region.center.lon, region.center.lat],
                },
            })),
    };
}

function popupHtml(region: AlertMapRegion): string {
    const counts = [
        region.alerts.critical
            ? `<span style="color:${SEVERITY_COLORS.critical}">${t(':count critical', { count: region.alerts.critical })}</span>`
            : '',
        region.alerts.warning
            ? `<span style="color:${SEVERITY_COLORS.warning}">${t(':count warning', { count: region.alerts.warning })}</span>`
            : '',
        region.alerts.watch
            ? `<span style="color:${SEVERITY_COLORS.watch}">${t(':count watch', { count: region.alerts.watch })}</span>`
            : '',
    ]
        .filter(Boolean)
        .join(' · ');

    const top = region.top
        ? `<div style="margin-top:4px">${formatPeriod(region.top.period_start, region.top.period)} · z = ${formatNumber(region.top.z_score, 1)}<br>${region.top.message}</div>`
        : `<div style="margin-top:4px;opacity:.7">${t('No unusual periods flagged')}</div>`;

    return `<div style="font-size:12px;line-height:1.35;max-width:240px">
        <strong>${region.name}</strong><br>${counts || `<span style="opacity:.7">${t('quiet')}</span>`}${top}
        <div style="margin-top:4px;opacity:.6">${t('click to filter the list')}</div>
    </div>`;
}

function addLayers(): void {
    if (!map) {
        return;
    }

    map.addSource('areas', { type: 'geojson', data: polygons() });
    map.addSource('counts', { type: 'geojson', data: markers() });

    map.addLayer({
        id: 'areas-fill',
        type: 'fill',
        source: 'areas',
        paint: {
            'fill-color': ['get', 'color'],
            'fill-opacity': [
                'case',
                ['get', 'selected'],
                0.35,
                ['get', 'dimmed'],
                0.06,
                0.18,
            ],
        },
    });
    map.addLayer({
        id: 'areas-line',
        type: 'line',
        source: 'areas',
        paint: {
            'line-color': ['get', 'outline'],
            'line-width': ['case', ['get', 'selected'], 2.5, 1.2],
            'line-opacity': ['case', ['get', 'dimmed'], 0.4, 1],
        },
    });
    map.addLayer({
        id: 'counts-circle',
        type: 'circle',
        source: 'counts',
        paint: {
            'circle-color': ['get', 'color'],
            'circle-opacity': ['case', ['get', 'dimmed'], 0.35, 0.9],
            'circle-stroke-color': '#fff',
            'circle-stroke-width': 1.5,
            'circle-radius': [
                'interpolate',
                ['linear'],
                ['get', 'total'],
                1,
                11,
                10,
                16,
                50,
                24,
            ],
        },
    });
    map.addLayer({
        id: 'counts-label',
        type: 'symbol',
        source: 'counts',
        layout: {
            'text-field': ['get', 'label'],
            'text-size': 12,
            'text-allow-overlap': true,
        },
        paint: {
            'text-color': '#fff',
            'text-opacity': ['case', ['get', 'dimmed'], 0.5, 1],
        },
    });

    for (const layer of ['areas-fill', 'counts-circle']) {
        map.on('click', layer, (event: MapLayerMouseEvent) => {
            const slug = event.features?.[0]?.properties?.slug as
                | string
                | undefined;

            if (!slug) {
                return;
            }

            emit('select', props.selected === slug ? null : slug);
        });
        map.on('mousemove', layer, (event: MapLayerMouseEvent) => {
            if (!map) {
                return;
            }

            const slug = event.features?.[0]?.properties?.slug as
                | string
                | undefined;
            const region = props.regions.find((r) => r.slug === slug);

            map.getCanvas().style.cursor = 'pointer';

            if (!region) {
                return;
            }

            hover ??= new Popup({
                closeButton: false,
                closeOnClick: false,
                maxWidth: '260px',
            });
            hover.setLngLat(event.lngLat).setHTML(popupHtml(region)).addTo(map);
        });
        map.on('mouseleave', layer, () => {
            if (map) {
                map.getCanvas().style.cursor = '';
            }
            hover?.remove();
        });
    }
}

function refresh(): void {
    (map?.getSource('areas') as GeoJSONSource | undefined)?.setData(polygons());
    (map?.getSource('counts') as GeoJSONSource | undefined)?.setData(markers());
}

function focus(): void {
    if (!map) {
        return;
    }

    const region = props.regions.find((r) => r.slug === props.selected);
    const bounds = region
        ? ([
              [region.bbox[0], region.bbox[1]],
              [region.bbox[2], region.bbox[3]],
          ] as [[number, number], [number, number]])
        : worldBounds(props.regions);

    map.fitBounds(bounds, { padding: 40, duration: 700, maxZoom: 6 });
}

const legend = computed(() =>
    (['critical', 'warning', 'watch'] as const).map((severity) => ({
        severity,
        color: SEVERITY_COLORS[severity],
        count: props.regions.reduce(
            (sum, region) => sum + region.alerts[severity],
            0,
        ),
    })),
);

onMounted(() => {
    const container = document.getElementById('alerts-map');

    if (!container) {
        return;
    }

    map = new MapLibreMap({
        container,
        style: basemapStyle(null, 'bluemarble'),
        bounds: worldBounds(props.regions),
        fitBoundsOptions: { padding: 40, maxZoom: 6 },
        attributionControl: { compact: true },
    });
    map.addControl(new NavigationControl({ showCompass: false }), 'top-right');
    map.once('style.load', () => {
        addLayers();
        focus();
    });
});

onBeforeUnmount(() => {
    hover?.remove();
    map?.remove();
    map = null;
});

watch(() => props.regions, refresh, { deep: true });
watch(
    () => props.selected,
    () => {
        refresh();
        focus();
    },
);
</script>

<template>
    <div class="relative overflow-hidden rounded-lg" :class="height">
        <!-- Inline size: MapLibre's own stylesheet sets position on .maplibregl-map and would override layered utilities. -->
        <div
            id="alerts-map"
            style="position: absolute; inset: 0; width: 100%; height: 100%"
        />
        <div
            class="pointer-events-none absolute bottom-6 left-2 flex flex-col gap-1 rounded-md bg-background/85 px-2 py-1.5 text-[11px] shadow backdrop-blur"
        >
            <span
                v-for="item in legend"
                :key="item.severity"
                class="flex items-center gap-1.5 capitalize"
            >
                <span
                    class="size-2.5 rounded-full"
                    :style="{ background: item.color }"
                />
                {{ $t(item.severity) }} · {{ formatNumber(item.count) }}
            </span>
            <span class="flex items-center gap-1.5">
                <span
                    class="size-2.5 rounded-full"
                    :style="{ background: QUIET }"
                />
                {{ $t('No unusual periods') }}
            </span>
            <span class="text-muted-foreground">{{
                $t('area colour = worst severity · number = flagged periods')
            }}</span>
        </div>
        <button
            v-if="selected"
            type="button"
            class="absolute top-2 left-2 rounded-md bg-background/85 px-2 py-1 text-xs shadow backdrop-blur hover:bg-background"
            @click="emit('select', null)"
        >
            ← {{ $t('All areas') }}
        </button>
    </div>
</template>

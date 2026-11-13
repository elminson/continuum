<script setup lang="ts">
import type { FeatureCollection } from 'geojson';
import {
    Map as MapLibreMap,
    NavigationControl,
    Popup,
    setWorkerUrl,
} from 'maplibre-gl';
import type { MapLayerMouseEvent } from 'maplibre-gl';
import mapWorkerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { basemapStyle } from '@/lib/basemap';
import { formatDate, formatNumber } from '@/lib/format';
import { t } from '@/lib/i18n';

setWorkerUrl(mapWorkerUrl);

const props = withDefaults(
    defineProps<{
        footprint: FeatureCollection;
        bounds: [number, number, number, number] | null;
        regionBox: [number, number, number, number];
        imageryDate: string | null;
        height?: string;
    }>(),
    { height: 'h-[460px]' },
);

const container = ref<HTMLDivElement | null>(null);
let map: MapLibreMap | null = null;

function regionOutline(): FeatureCollection {
    const [w, s, e, n] = props.regionBox;

    return {
        type: 'FeatureCollection',
        features: [
            {
                type: 'Feature',
                properties: {},
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
            },
        ],
    };
}

function addLayers(): void {
    if (!map) {
        return;
    }

    map.addSource('region', { type: 'geojson', data: regionOutline() });
    map.addLayer({
        id: 'region-line',
        type: 'line',
        source: 'region',
        paint: {
            'line-color': '#4ade80',
            'line-width': 1.5,
            'line-dasharray': [2, 2],
        },
    });

    map.addSource('cells', { type: 'geojson', data: props.footprint });

    // Zoomed out: where the fire concentrated, weighted by radiative power.
    map.addLayer({
        id: 'cells-heat',
        type: 'heatmap',
        source: 'cells',
        maxzoom: 9,
        paint: {
            'heatmap-weight': [
                'interpolate',
                ['linear'],
                ['get', 'frp_sum'],
                0,
                0.1,
                500,
                1,
            ],
            'heatmap-intensity': [
                'interpolate',
                ['linear'],
                ['zoom'],
                3,
                0.6,
                9,
                2,
            ],
            'heatmap-radius': [
                'interpolate',
                ['linear'],
                ['zoom'],
                3,
                6,
                9,
                22,
            ],
            'heatmap-opacity': [
                'interpolate',
                ['linear'],
                ['zoom'],
                7,
                0.85,
                9,
                0,
            ],
            'heatmap-color': [
                'interpolate',
                ['linear'],
                ['heatmap-density'],
                0,
                'rgba(0,0,0,0)',
                0.2,
                '#fde68a',
                0.45,
                '#fb923c',
                0.7,
                '#ef4444',
                1,
                '#7f1d1d',
            ],
        },
    });

    // Zoomed in: every 1 km cell, sized by how many days it burned.
    map.addLayer({
        id: 'cells-dots',
        type: 'circle',
        source: 'cells',
        minzoom: 6,
        paint: {
            'circle-radius': [
                'interpolate',
                ['linear'],
                ['zoom'],
                6,
                ['interpolate', ['linear'], ['get', 'days'], 1, 2, 7, 4],
                12,
                ['interpolate', ['linear'], ['get', 'days'], 1, 6, 7, 14],
            ],
            'circle-color': [
                'interpolate',
                ['linear'],
                ['get', 'frp_max'],
                0,
                '#fde68a',
                50,
                '#fb923c',
                300,
                '#ef4444',
            ],
            'circle-opacity': [
                'interpolate',
                ['linear'],
                ['zoom'],
                6,
                0,
                7.5,
                0.9,
            ],
            'circle-stroke-color': '#18181b',
            'circle-stroke-width': 0.6,
        },
    });

    map.on('click', 'cells-dots', (event: MapLayerMouseEvent) => {
        const feature = event.features?.[0];

        if (!feature || !map) {
            return;
        }

        const p = feature.properties as Record<string, string | number>;
        const sensors = String(p.sensors)
            .replace('M', 'MODIS ')
            .replace('V', 'VIIRS')
            .trim()
            .replace(' ', ' + ');
        const span =
            p.first === p.last
                ? formatDate(String(p.first))
                : t(':from to :to', {
                      from: formatDate(String(p.first)),
                      to: formatDate(String(p.last)),
                  });

        new Popup({ closeButton: false, maxWidth: '260px' })
            .setLngLat(event.lngLat)
            .setHTML(
                `<div style="font-size:12px;line-height:1.4">
                    <strong>${t('Burned :count day(s)', { count: p.days })}</strong> · ${span}<br>
                    ${t(':count detection(s) by :sensors', { count: formatNumber(Number(p.detections)), sensors })}<br>
                    ${t('Peak FRP :value MW', { value: formatNumber(Number(p.frp_max), 1) })}<br>
                    <span style="opacity:.7">${formatNumber(Number(feature.geometry.type === 'Point' ? feature.geometry.coordinates[1] : 0), 3)}, ${formatNumber(Number(feature.geometry.type === 'Point' ? feature.geometry.coordinates[0] : 0), 3)}</span>
                </div>`,
            )
            .addTo(map);
    });
    map.on('mouseenter', 'cells-dots', () => {
        if (map) {
            map.getCanvas().style.cursor = 'pointer';
        }
    });
    map.on('mouseleave', 'cells-dots', () => {
        if (map) {
            map.getCanvas().style.cursor = '';
        }
    });
}

onMounted(() => {
    if (!container.value) {
        return;
    }

    const [w, s, e, n] = props.bounds ?? props.regionBox;

    map = new MapLibreMap({
        container: container.value,
        style: basemapStyle(props.imageryDate),
        bounds: [
            [w, s],
            [e, n],
        ],
        fitBoundsOptions: { padding: 32, maxZoom: 10 },
        attributionControl: { compact: true },
    });
    map.addControl(new NavigationControl({ showCompass: false }), 'top-right');
    map.once('style.load', addLayers);
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
});
</script>

<template>
    <div class="relative overflow-hidden rounded-xl" :class="height">
        <!-- Inline size: MapLibre's stylesheet would override layered utilities. -->
        <div
            ref="container"
            style="position: absolute; inset: 0; width: 100%; height: 100%"
        />
        <div
            class="pointer-events-none absolute bottom-6 left-2 flex flex-col gap-1 rounded-md bg-background/85 px-2 py-1.5 text-[11px] shadow backdrop-blur"
        >
            <span class="flex items-center gap-1.5">
                <span
                    class="h-2 w-16 rounded-full"
                    style="
                        background: linear-gradient(
                            90deg,
                            #fde68a,
                            #fb923c,
                            #ef4444
                        );
                    "
                />
                {{ $t('fire radiative power') }}
            </span>
            <span class="text-muted-foreground">{{
                $t(
                    'heat when zoomed out · dots per 1 km cell, sized by days burning · click a dot',
                )
            }}</span>
        </div>
    </div>
</template>

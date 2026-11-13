<script setup lang="ts">
import type { Feature, FeatureCollection } from 'geojson';
import {
    GeoJSONSource,
    Map as MapLibreMap,
    NavigationControl,
    Popup,
    setWorkerUrl,
} from 'maplibre-gl';
import type {
    MapLayerMouseEvent,
    MapMouseEvent,
    MapTouchEvent,
    RasterTileSource,
} from 'maplibre-gl';
import mapWorkerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { basemapStyle, imageryTiles } from '@/lib/basemap';
import type { Basemap } from '@/lib/basemap';
import { INSTRUMENT_COLORS, formatNumber } from '@/lib/format';
import { t } from '@/lib/i18n';
import { hotspots } from '@/routes/api/v1';

// MapLibre resolves its worker with a dynamic URL that bundlers cannot
// rewrite, so point it at the worker Vite builds for us.
setWorkerUrl(mapWorkerUrl);

type Mode = 'both' | 'modis' | 'viirs';

const props = withDefaults(
    defineProps<{
        bbox: [number, number, number, number];
        date: string | null;
        mode?: Mode;
        height?: string;
        interactive?: boolean;
        preview?: boolean;
        /** Show a "Draw area" control that lets the user drag a rectangle. */
        draw?: boolean;
    }>(),
    {
        mode: 'both',
        height: 'h-[420px]',
        interactive: true,
        preview: false,
        draw: false,
    },
);

const emit = defineEmits<{
    loaded: [count: { modis: number; viirs: number }];
    drawn: [bbox: [number, number, number, number]];
}>();

const container = ref<HTMLDivElement | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
const counts = ref({ modis: 0, viirs: 0 });
let map: MapLibreMap | null = null;
let requestId = 0;

const basemapChoice = ref<Basemap>('truecolor');

function applyImagery(): void {
    (map?.getSource('imagery') as RasterTileSource | undefined)?.setTiles(
        imageryTiles(props.date, basemapChoice.value),
    );
}

function emptyCollection(): FeatureCollection {
    return { type: 'FeatureCollection', features: [] };
}

const drawing = ref(false);
let drawStart: [number, number] | null = null;

function round(value: number): number {
    return Math.round(value * 1000) / 1000;
}

function boxPolygon(
    bbox: [number, number, number, number] = props.bbox,
): Feature {
    const [w, s, e, n] = bbox;

    return {
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
    };
}

function addLayers(): void {
    if (!map) {
        return;
    }

    map.addSource('aoi', { type: 'geojson', data: boxPolygon() });
    map.addLayer({
        id: 'aoi-line',
        type: 'line',
        source: 'aoi',
        paint: {
            'line-color': '#4ade80',
            'line-width': 1.5,
            'line-dasharray': [2, 2],
        },
    });

    for (const instrument of ['viirs', 'modis'] as const) {
        map.addSource(instrument, { type: 'geojson', data: emptyCollection() });
        map.addLayer({
            id: `${instrument}-cells`,
            type: 'circle',
            source: instrument,
            paint: {
                'circle-color': INSTRUMENT_COLORS[instrument],
                'circle-opacity': instrument === 'modis' ? 0.85 : 0.6,
                'circle-stroke-color': '#000',
                'circle-stroke-opacity': 0.3,
                'circle-stroke-width': 0.5,
                'circle-radius': [
                    'interpolate',
                    ['linear'],
                    ['zoom'],
                    2,
                    [
                        'interpolate',
                        ['linear'],
                        ['get', 'frp_sum'],
                        0,
                        1.5,
                        200,
                        3.5,
                    ],
                    8,
                    [
                        'interpolate',
                        ['linear'],
                        ['get', 'frp_sum'],
                        0,
                        4,
                        200,
                        10,
                    ],
                    12,
                    [
                        'interpolate',
                        ['linear'],
                        ['get', 'frp_sum'],
                        0,
                        8,
                        200,
                        18,
                    ],
                ],
            },
        });

        map.on('click', `${instrument}-cells`, (event: MapLayerMouseEvent) => {
            const feature = event.features?.[0];

            if (!feature || !map) {
                return;
            }

            const p = feature.properties as Record<string, string | number>;

            new Popup({ closeButton: false, maxWidth: '260px' })
                .setLngLat(event.lngLat)
                .setHTML(
                    `<div>
                        <strong>${String(p.instrument).toUpperCase()}</strong> · ${p.satellite}<br>
                        ${t(':count pixel(s) in this 1 km cell', { count: formatNumber(Number(p.detections)) })}<br>
                        ${t('FRP Σ :sum MW · max :max MW', { sum: formatNumber(Number(p.frp_sum), 1), max: formatNumber(Number(p.frp_max), 1) })}<br>
                        ${t('day/night :day/:night · high conf :high', { day: p.day, night: p.night, high: p.high_confidence })}
                    </div>`,
                )
                .addTo(map);
        });

        map.on('mouseenter', `${instrument}-cells`, () => {
            if (map) {
                map.getCanvas().style.cursor = 'pointer';
            }
        });
        map.on('mouseleave', `${instrument}-cells`, () => {
            if (map) {
                map.getCanvas().style.cursor = '';
            }
        });
    }

    applyMode();
}

function applyMode(): void {
    if (!map || !map.getLayer('modis-cells')) {
        return;
    }

    map.setLayoutProperty(
        'modis-cells',
        'visibility',
        props.mode === 'viirs' ? 'none' : 'visible',
    );
    map.setLayoutProperty(
        'viirs-cells',
        'visibility',
        props.mode === 'modis' ? 'none' : 'visible',
    );
}

async function load(): Promise<void> {
    if (!map || !props.date) {
        return;
    }

    const id = ++requestId;
    loading.value = true;
    error.value = null;

    try {
        const results = await Promise.all(
            (['modis', 'viirs'] as const).map(async (instrument) => {
                const url = hotspots.url({
                    query: {
                        bbox: props.bbox.join(','),
                        date: props.date,
                        instrument,
                        limit: 20000,
                    },
                });
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    throw new Error(
                        t('Hotspot request failed (:status)', {
                            status: response.status,
                        }),
                    );
                }

                return [
                    instrument,
                    (await response.json()) as FeatureCollection,
                ] as const;
            }),
        );

        if (id !== requestId || !map) {
            return;
        }

        for (const [instrument, collection] of results) {
            (map.getSource(instrument) as GeoJSONSource | undefined)?.setData(
                collection,
            );
            counts.value[instrument] = collection.features.length;
        }

        emit('loaded', { ...counts.value });
    } catch (exception) {
        error.value =
            exception instanceof Error
                ? exception.message
                : t('Failed to load hotspots');
    } finally {
        if (id === requestId) {
            loading.value = false;
        }
    }
}

onMounted(() => {
    if (!container.value) {
        return;
    }

    const [w, s, e, n] = props.bbox;

    map = new MapLibreMap({
        container: container.value,
        style: basemapStyle(props.date, basemapChoice.value),
        bounds: [
            [w, s],
            [e, n],
        ],
        fitBoundsOptions: { padding: 24 },
        interactive: props.interactive,
        attributionControl: { compact: true },
        // Keeps the last frame readable so the map can be saved as a PNG.
        canvasContextAttributes: { preserveDrawingBuffer: true },
    });

    if (props.draw) {
        map.on('mousedown', onDrawStart);
        map.on('mousemove', onDrawMove);
        map.on('mouseup', onDrawEnd);
        map.on('touchstart', onDrawStart);
        map.on('touchmove', onDrawMove);
        map.on('touchend', onDrawEnd);
    }

    if (props.interactive) {
        map.addControl(
            new NavigationControl({ showCompass: false }),
            'top-right',
        );
    }

    // style.load fires as soon as the style is parsed, so hotspots appear even
    // when the basemap tiles are slow or blocked.
    map.once('style.load', () => {
        addLayers();
        void load();
    });

    // Expose the instance for QA scripts and the browser console.
    (window as Window & { __continuumMaps?: MapLibreMap[] }).__continuumMaps = [
        ...((window as Window & { __continuumMaps?: MapLibreMap[] })
            .__continuumMaps ?? []),
        map,
    ];
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
});

function toggleDraw(): void {
    if (!map) {
        return;
    }

    drawing.value = !drawing.value;
    drawStart = null;

    if (drawing.value) {
        map.dragPan.disable();
        map.getCanvas().style.cursor = 'crosshair';
    } else {
        map.dragPan.enable();
        map.getCanvas().style.cursor = '';
    }
}

function rectangle(
    from: [number, number],
    to: [number, number],
): [number, number, number, number] {
    return [
        round(Math.min(from[0], to[0])),
        round(Math.min(from[1], to[1])),
        round(Math.max(from[0], to[0])),
        round(Math.max(from[1], to[1])),
    ];
}

function onDrawStart(event: MapMouseEvent | MapTouchEvent): void {
    if (!drawing.value) {
        return;
    }

    event.preventDefault();
    drawStart = [event.lngLat.lng, event.lngLat.lat];
}

function onDrawMove(event: MapMouseEvent | MapTouchEvent): void {
    if (!drawing.value || !drawStart || !map) {
        return;
    }

    (map.getSource('aoi') as GeoJSONSource | undefined)?.setData(
        boxPolygon(rectangle(drawStart, [event.lngLat.lng, event.lngLat.lat])),
    );
}

function onDrawEnd(event: MapMouseEvent | MapTouchEvent): void {
    if (!drawing.value || !drawStart) {
        return;
    }

    const bbox = rectangle(drawStart, [event.lngLat.lng, event.lngLat.lat]);
    toggleDraw();

    if (bbox[0] < bbox[2] && bbox[1] < bbox[3]) {
        emit('drawn', bbox);
    } else {
        (map?.getSource('aoi') as GeoJSONSource | undefined)?.setData(
            boxPolygon(),
        );
    }
}

function getBounds(): [number, number, number, number] | null {
    if (!map) {
        return null;
    }

    const bounds = map.getBounds();

    return [
        bounds.getWest(),
        bounds.getSouth(),
        bounds.getEast(),
        bounds.getNorth(),
    ];
}

defineExpose({ getBounds });

watch(
    () => props.date,
    () => void load(),
);
watch(() => props.mode, applyMode);
watch(
    () => props.bbox,
    (bbox) => {
        if (!map) {
            return;
        }

        (map.getSource('aoi') as GeoJSONSource | undefined)?.setData(
            boxPolygon(),
        );
        map.fitBounds(
            [
                [bbox[0], bbox[1]],
                [bbox[2], bbox[3]],
            ],
            { padding: 24, duration: 600 },
        );
        void load();
    },
);
</script>

<template>
    <div class="relative overflow-hidden rounded-lg" :class="height">
        <!-- Inline size: MapLibre's own stylesheet sets position on .maplibregl-map and would override layered utilities. -->
        <div
            ref="container"
            style="position: absolute; inset: 0; width: 100%; height: 100%"
        />
        <div
            v-if="date"
            class="pointer-events-none absolute bottom-6 left-2 flex flex-col gap-1 rounded-md bg-background/85 px-2 py-1.5 text-[11px] shadow backdrop-blur"
        >
            <span v-if="mode !== 'viirs'" class="flex items-center gap-1.5">
                <span
                    class="size-2.5 rounded-full"
                    :style="{ background: INSTRUMENT_COLORS.modis }"
                />
                {{ $t('MODIS 1 km cells') }} · {{ formatNumber(counts.modis) }}
            </span>
            <span v-if="mode !== 'modis'" class="flex items-center gap-1.5">
                <span
                    class="size-2.5 rounded-full"
                    :style="{ background: INSTRUMENT_COLORS.viirs }"
                />
                {{ $t('VIIRS 375 m → 1 km cells') }} ·
                {{ formatNumber(counts.viirs) }}
            </span>
            <span class="text-muted-foreground">{{
                $t('circle size ∝ FRP · click a cell')
            }}</span>
        </div>
        <button
            v-if="draw"
            type="button"
            class="absolute top-2 left-2 z-10 rounded-md px-2.5 py-1 text-xs font-medium shadow backdrop-blur"
            :class="
                drawing
                    ? 'bg-foreground text-background'
                    : 'bg-background/90 text-foreground hover:bg-background'
            "
            @click="toggleDraw"
        >
            {{
                drawing
                    ? $t('Drag a rectangle… (click to cancel)')
                    : $t('Draw area')
            }}
        </button>
        <div
            v-if="loading"
            class="absolute left-2 rounded-md bg-background/85 px-2 py-1 text-xs shadow backdrop-blur"
            :class="draw ? 'top-10' : 'top-2'"
        >
            {{ $t('Loading :date…', { date: date ?? '' }) }}
        </div>
        <div
            v-if="error"
            class="absolute left-2 rounded-md bg-destructive/90 px-2 py-1 text-xs text-white shadow"
            :class="draw ? 'top-10' : 'top-2'"
        >
            {{ error }}
        </div>
        <div
            v-if="!date && !preview"
            class="absolute inset-0 flex items-center justify-center bg-background/60 text-sm text-muted-foreground"
        >
            {{ $t('No detections ingested for this area yet.') }}
        </div>
    </div>
</template>

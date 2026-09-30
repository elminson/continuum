import type { StyleSpecification } from 'maplibre-gl';

export type Basemap = 'truecolor' | 'bluemarble';

/**
 * GIBS publishes a day's true-colour scene a few hours after acquisition, so
 * never ask for anything newer than yesterday (UTC).
 */
export function imageryDate(date: string | null): string | null {
    if (!date) {
        return null;
    }

    const yesterday = new Date(Date.now() - 86400000)
        .toISOString()
        .slice(0, 10);

    return date < yesterday ? date : yesterday;
}

/**
 * NASA GIBS imagery: the selected day's VIIRS (NOAA-20) true-colour scene when
 * a date is set, Blue Marble otherwise. Both are key-free.
 */
export function imageryTiles(
    date: string | null,
    choice: Basemap = 'truecolor',
): string[] {
    const day = imageryDate(date);

    return day && choice === 'truecolor'
        ? [
              `https://gibs.earthdata.nasa.gov/wmts/epsg3857/best/VIIRS_NOAA20_CorrectedReflectance_TrueColor/default/${day}/GoogleMapsCompatible_Level9/{z}/{y}/{x}.jpg`,
          ]
        : [
              'https://gibs.earthdata.nasa.gov/wmts/epsg3857/best/BlueMarble_ShadedRelief_Bathymetry/default/2004-08/GoogleMapsCompatible_Level8/{z}/{y}/{x}.jpeg',
          ];
}

/**
 * Raster basemap: GIBS imagery with Esri's reference tiles (borders and place
 * names) on top.
 */
export function basemapStyle(
    date: string | null,
    choice: Basemap = 'truecolor',
): StyleSpecification {
    return {
        version: 8,
        sources: {
            imagery: {
                type: 'raster',
                tiles: imageryTiles(date, choice),
                tileSize: 256,
                maxzoom: 9,
                attribution:
                    'Imagery: <a href="https://www.earthdata.nasa.gov/data/tools/global-imagery-browse-services-gibs">NASA GIBS</a> · Labels: Esri · Fire data: NASA FIRMS',
            },
            labels: {
                type: 'raster',
                tiles: [
                    'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}',
                ],
                tileSize: 256,
                maxzoom: 12,
            },
        },
        layers: [
            { id: 'imagery', type: 'raster', source: 'imagery' },
            {
                id: 'labels',
                type: 'raster',
                source: 'labels',
                paint: { 'raster-opacity': 0.85 },
            },
        ],
    };
}

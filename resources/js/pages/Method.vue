<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import SectionCard from '@/components/continuum/SectionCard.vue';
import { formatNumber } from '@/lib/format';
import { home, method } from '@/routes';
import { show } from '@/routes/regions';
import type { ValidationRow } from '@/types/continuum';

const props = defineProps<{
    gridKm: number;
    calendarCellDeg: number;
    frpEdges: number[];
    calibration: {
        method_version: string;
        min_days: number;
        bootstrap_samples: number;
        holdout_fraction: number;
        seed: number;
    };
    anomalies: {
        min_years: number;
        thresholds: { watch: number; warning: number; critical: number };
    };
    validation: ValidationRow[];
    thresholds: { small_sample_days: number; weak_r2: number };
}>();

const modisRows = computed(() =>
    props.validation.filter((row) => row.reference === 'modis'),
);

const viirsRows = computed(() =>
    props.validation.filter((row) => row.reference === 'viirs'),
);

function median(values: number[]): number | null {
    if (values.length === 0) {
        return null;
    }

    const sorted = [...values].sort((a, b) => a - b);
    const mid = Math.floor(sorted.length / 2);

    return sorted.length % 2
        ? sorted[mid]
        : (sorted[mid - 1] + sorted[mid]) / 2;
}

const viirsMedianR2 = computed(() =>
    median(viirsRows.value.map((r) => r.r2 ?? 0)),
);
const modisMedianR2 = computed(() =>
    median(modisRows.value.map((r) => r.r2 ?? 0)),
);

const ISSUE_LABELS: Record<ValidationRow['issues'][number], string> = {
    small_sample: 'few overlap days',
    weak_daily_fit: 'weak daily fit',
    barely_beats_naive: 'barely beats naive',
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Overview', href: home() },
            { title: 'Method', href: method() },
        ],
    },
});
</script>

<template>
    <Head :title="$t('Method')" />

    <div class="mx-auto flex max-w-4xl flex-col gap-4 p-4">
        <header>
            <p
                class="text-xs font-semibold tracking-widest text-orange-500 uppercase"
            >
                {{
                    $t('Method · v:version', {
                        version: calibration.method_version,
                    })
                }}
            </p>
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ $t('How Continuum harmonizes MODIS and VIIRS hot spots') }}
            </h1>
            <p class="mt-2 text-sm text-muted-foreground">
                {{
                    $t(
                        'The record is split across two instruments that disagree by design. Continuum makes the disagreement explicit, removes the part that is pure geometry, fits the part that is sensor physics on the years both instruments flew, and reports how well the fit holds on days it never saw.',
                    )
                }}
            </p>
        </header>

        <SectionCard :title="$t('1 · The problem is two problems')">
            <div class="prose prose-sm max-w-none dark:prose-invert">
                <ul>
                    <li>
                        <strong>{{ $t('Pixel multiplicity.') }}</strong>
                        {{
                            $t(
                                'A MODIS active-fire pixel is nominally 1 km × 1 km; a VIIRS I-band pixel is 375 m × 375 m, about 7× smaller in area. The same fire front is reported once by MODIS and 3–7 times by VIIRS. This is geometry and can be removed exactly.',
                            )
                        }}
                    </li>
                    <li>
                        <strong>{{ $t('Sensitivity.') }}</strong>
                        {{
                            $t(
                                'A 375 m pixel needs far less fire to raise its brightness temperature above the background than a 1 km pixel does, so VIIRS detects many small, cool or short fires MODIS never sees. This is physics; it cannot be removed, only modelled. In the bundled samples the VIIRS surplus that survives gridding is still ×4–5 per region.',
                            )
                        }}
                    </li>
                    <li>
                        <strong>{{ $t('Fleet size.') }}</strong>
                        {{
                            $t(
                                'MODIS flies on two platforms (Terra 10:30, Aqua 13:30); VIIRS on one (2012), two (2018) and three (2023) platforms, all near 13:30. Raw counts grow with every launch even if burning does not.',
                            )
                        }}
                    </li>
                </ul>
                <p>
                    {{
                        $t(
                            'Concatenating the two records therefore produces the well-known illusion that burning activity roughly tripled in 2012. Every chart in Continuum can overlay that naive merge so the artefact stays visible.',
                        )
                    }}
                </p>
            </div>
        </SectionCard>

        <SectionCard :title="$t('2 · Common grid and the cell-day')">
            <div class="prose prose-sm max-w-none dark:prose-invert">
                <p>
                    {{
                        $t(
                            'Every detection from every platform is snapped to a global grid of roughly equal-area cells :km km on a side, i.e. MODIS-nadir scale. Rows are latitude bands of constant height; each row is split into as many columns as fit around that parallel, so cells do not shrink toward the poles. Cell ids pack (row, column) into one integer so the database can group billions of pixels cheaply.',
                            { km: gridKm },
                        )
                    }}
                </p>
                <p>
                    {{ $t('The unit of burning activity is the') }}
                    <strong>{{ $t('active cell-day') }}</strong
                    >{{
                        $t(
                            ": one grid cell with at least one detection from an instrument on one UTC day. All platforms of an instrument are merged before counting, so a cell seen by Suomi NPP, NOAA-20 and NOAA-21 on the same day counts once, exactly as a cell seen by Terra and Aqua counts once. The cell-day's fire radiative power (FRP) is the strongest single-platform view of that cell that day. After this step pixel multiplicity and fleet size are gone; what remains between MODIS and VIIRS is sensitivity.",
                        )
                    }}
                </p>
                <p>
                    {{
                        $t(
                            'Confidence is normalised to low / nominal / high (MODIS 0–29 / 30–79 / 80–100; VIIRS l / n / h). Detections flagged by FIRMS as volcanoes, static land sources or offshore are excluded. Scan and track sizes are kept per pixel so the true footprint is available for area-based studies.',
                        )
                    }}
                </p>
            </div>
        </SectionCard>

        <SectionCard
            :title="$t('3 · Two transfer functions, fitted on the overlap')"
        >
            <div class="prose prose-sm max-w-none dark:prose-invert">
                <p>
                    {{
                        $t(
                            'Since 20 January 2012 both instruments have observed the same fires. For every area of interest Continuum takes the days on which both instruments reported anywhere on Earth (so an empty day is a real zero, not an outage) and fits:',
                        )
                    }}
                </p>
                <ol>
                    <li>
                        <strong>{{ $t('Monthly ratio.') }}</strong>
                        {{
                            $t(
                                'r = Σ MODIS cell-days ÷ Σ VIIRS cell-days over overlap days, per calendar month (when a month has at least :min_days days) and for the whole year. A :samples-sample paired bootstrap (fixed seed :seed) gives the 95% interval that becomes the shaded band on every estimated period. The inverse direction (VIIRS-equivalent) is fitted separately, not derived, so its interval is honest.',
                                {
                                    min_days: calibration.min_days,
                                    samples: calibration.bootstrap_samples,
                                    seed: calibration.seed,
                                },
                            )
                        }}
                    </li>
                    <li>
                        <strong>{{ $t('FRP visibility threshold.') }}</strong>
                        {{
                            $t(
                                "A VIIRS cell-day is counted as MODIS-equivalent only if its summed FRP is at least τ megawatts. τ is chosen from the histogram edges (:edges … :last MW) to minimise the mean absolute daily error against MODIS. Calendar entries store each period's VIIRS cell-days as counts per FRP bin, so any τ can be re-evaluated instantly. The Harmonization Lab shows the empirical probability that MODIS detected a VIIRS cell as a function of its FRP, which is what makes this threshold physical rather than statistical.",
                                {
                                    edges: frpEdges.slice(0, 8).join(', '),
                                    last: frpEdges[frpEdges.length - 1],
                                },
                            )
                        }}
                    </li>
                </ol>
                <p>
                    {{
                        $t(
                            "Whichever method is chosen, the reference sensor's own observations are always used when it flew. Transfers are applied only where the reference is absent (pre-2012 for VIIRS-equivalent series, post-MODIS for MODIS-equivalent series), and every transferred value is flagged",
                        )
                    }}
                    <em>{{ $t('estimated') }}</em>
                    {{ $t('in the API, the CSV export and the charts.') }}
                </p>
            </div>
        </SectionCard>

        <SectionCard :title="$t('4 · Validation you can see')">
            <div class="prose prose-sm max-w-none dark:prose-invert">
                <p>
                    {{
                        $t(
                            'Before a factor is stored it is fitted on three quarters of the overlap days and scored on the interleaved fourth (every fourth day, so every season is represented in both sets). Continuum reports the held-out mean absolute error, bias, RMSE, R² and correlation for the harmonized series',
                        )
                    }}
                    <em>{{ $t('and') }}</em>
                    {{
                        $t(
                            'for the naive merge, and the relative improvement between them. A transfer that does not beat the naive merge is shown as such. The stored factor is then refitted on all overlap days.',
                        )
                    }}
                </p>
            </div>

            <div v-if="modisRows.length > 0" class="mt-4 overflow-x-auto">
                <p class="mb-2 text-xs text-muted-foreground">
                    {{
                        $t(
                            'Live held-out scores for every area you can see, monthly ratio, MODIS scale, weakest first. Daily error is in cell-days.',
                        )
                    }}
                </p>
                <table class="w-full text-xs tabular-nums">
                    <thead class="text-left text-muted-foreground">
                        <tr class="border-b border-border">
                            <th class="py-1.5 pr-3 font-medium">
                                {{ $t('Area') }}
                            </th>
                            <th class="py-1.5 pr-3 text-right font-medium">
                                {{ $t('Overlap days') }}
                            </th>
                            <th class="py-1.5 pr-3 text-right font-medium">
                                R²
                            </th>
                            <th class="py-1.5 pr-3 text-right font-medium">
                                {{ $t('Error') }}
                            </th>
                            <th class="py-1.5 pr-3 text-right font-medium">
                                {{ $t('Naive error') }}
                            </th>
                            <th class="py-1.5 pr-3 text-right font-medium">
                                {{ $t('Better by') }}
                            </th>
                            <th class="py-1.5 font-medium">
                                {{ $t('Weakness') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in modisRows"
                            :key="row.slug"
                            class="border-b border-border/50"
                        >
                            <td class="py-1.5 pr-3">
                                <Link
                                    :href="show(row.slug)"
                                    class="hover:underline"
                                    >{{ row.region }}</Link
                                >
                            </td>
                            <td class="py-1.5 pr-3 text-right">
                                {{ formatNumber(row.overlap_days) }}
                            </td>
                            <td class="py-1.5 pr-3 text-right">
                                {{ formatNumber(row.r2, 2) }}
                            </td>
                            <td class="py-1.5 pr-3 text-right">
                                {{ formatNumber(row.mae, 1) }}
                            </td>
                            <td class="py-1.5 pr-3 text-right">
                                {{ formatNumber(row.naive_mae, 1) }}
                            </td>
                            <td class="py-1.5 pr-3 text-right">
                                {{
                                    row.improvement === null
                                        ? '—'
                                        : `${Math.round(row.improvement * 100)}%`
                                }}
                            </td>
                            <td class="py-1.5 text-muted-foreground">
                                {{
                                    row.issues
                                        .map((issue) => $t(ISSUE_LABELS[issue]))
                                        .join(', ') || '—'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="prose prose-sm mt-4 max-w-none dark:prose-invert">
                <h4>{{ $t('Where it works worst') }}</h4>
                <ul>
                    <li>
                        <strong>{{ $t('Small or low-fire areas.') }}</strong>
                        {{
                            $t(
                                'With only a handful of fires a day, one missed or extra pixel is a large share of the total, so daily R² falls below :r2. Monthly and yearly totals are far steadier than single days.',
                                { r2: thresholds.weak_r2 },
                            )
                        }}
                    </li>
                    <li>
                        <strong>{{ $t('Short overlap.') }}</strong>
                        {{
                            $t(
                                'Areas with fewer than :days days where both sensors flew rest on a sample, not a record; fetch the full history before trusting their factors.',
                                { days: thresholds.small_sample_days },
                            )
                        }}
                    </li>
                    <li>
                        <strong>{{ $t('VIIRS scale.') }}</strong>
                        {{
                            $t(
                                'Rebuilding VIIRS-like counts before 2012 is harder than the reverse: VIIRS sees small, cool fires that MODIS never recorded, so no factor can recover them',
                            )
                        }}<template
                            v-if="
                                viirsMedianR2 !== null && modisMedianR2 !== null
                            "
                        >
                            {{
                                $t(
                                    '(median R² :viirs versus :modis on the MODIS scale)',
                                    {
                                        viirs: formatNumber(viirsMedianR2, 2),
                                        modis: formatNumber(modisMedianR2, 2),
                                    },
                                )
                            }}</template
                        >.
                        {{ $t('MODIS scale is the default for this reason.') }}
                    </li>
                    <li>
                        <strong>{{ $t('Persistent cloud and smoke.') }}</strong>
                        {{
                            $t(
                                'Both sensors miss fires under cloud or thick smoke, and Continuum cannot tell a cloudy day from a day without fire.',
                            )
                        }}
                    </li>
                </ul>
            </div>
        </SectionCard>

        <SectionCard
            :title="$t('5 · Calendar, critical periods and unusual conditions')"
        >
            <div class="prose prose-sm max-w-none dark:prose-invert">
                <ul>
                    <li>
                        <strong>{{ $t('Calendar.') }}</strong>
                        {{
                            $t(
                                'Daily, ISO-weekly (Monday start) and monthly native series per instrument, with coverage days, day/night split, confidence mix, FRP totals and the FRP histogram; harmonized values are derived on request so a new calibration never requires a rebuild.',
                            )
                        }}
                    </li>
                    <li>
                        <strong>{{ $t('Critical periods.') }}</strong>
                        {{
                            $t(
                                'The climatological season is the run of months (or weeks) whose typical harmonized activity stays above half of the seasonal peak; onset is where it first exceeds a quarter. The latest observation is placed inside or outside that window.',
                            )
                        }}
                    </li>
                    <li>
                        <strong>{{ $t('Unusual conditions.') }}</strong>
                        {{
                            $t(
                                'Each week or month is compared with the same period in every other year using the median and the scaled median absolute deviation (robust to one extreme year). With at least :years reference years, z ≥ :watch is a watch, ≥ :warning a warning and ≥ :critical critical; the percentile rank is reported alongside.',
                                {
                                    years: anomalies.min_years,
                                    watch: anomalies.thresholds.watch,
                                    warning: anomalies.thresholds.warning,
                                    critical: anomalies.thresholds.critical,
                                },
                            )
                        }}
                    </li>
                </ul>
            </div>
        </SectionCard>

        <SectionCard :title="$t('6 · Provenance and limits')">
            <div class="prose prose-sm max-w-none dark:prose-invert">
                <p>
                    {{ $t('Every export and API response carries a') }}
                    <strong>{{ $t('method receipt') }}</strong
                    >{{
                        $t(
                            ': sources and collections, ingest runs, grid, method, factor, interval, overlap window, validation and caveats. The main caveats:',
                        )
                    }}
                </p>
                <ul>
                    <li>
                        {{
                            $t(
                                'Active-fire products are positive-only. A day without detections can be cloud, an outage or truly no fire; Continuum states how many days an instrument reported anywhere on Earth (coverage days) but does not claim a per-cell observation mask.',
                            )
                        }}
                    </li>
                    <li>
                        {{
                            $t(
                                'Near-real-time records are replaced by standard-processing records when a backfill runs; values can change slightly.',
                            )
                        }}
                    </li>
                    <li>
                        {{
                            $t(
                                'Rectangular areas only; areas crossing the antimeridian are not supported.',
                            )
                        }}
                    </li>
                    <li>
                        {{
                            $t(
                                'Colours are chosen to stay distinct under deuteranopia, protanopia and tritanopia: blue MODIS, orange VIIRS, green harmonized, and sequential ramps whose lightness rises with fire activity.',
                            )
                        }}
                    </li>
                    <li>
                        {{
                            $t(
                                "Terra's 10:30 overpass observes a different diurnal phase than the 13:30 constellation; the calendar keeps day/night counts so users can restrict comparisons if needed.",
                            )
                        }}
                    </li>
                </ul>
                <p>
                    {{
                        $t(
                            'Data: NASA FIRMS MODIS Collection 6.1 (Giglio et al.) and VIIRS Collection 2 375 m (Schroeder et al., 2014) active fire products. Sample data bundled with the app are clipped from the public 7-day feeds; historical backfills use the Area API with a free MAP_KEY.',
                        )
                    }}
                </p>
            </div>
        </SectionCard>
    </div>
</template>

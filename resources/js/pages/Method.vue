<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import SectionCard from '@/components/continuum/SectionCard.vue';
import { home, method } from '@/routes';

defineProps<{
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
}>();

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
    <Head title="Method" />

    <div class="mx-auto flex max-w-4xl flex-col gap-4 p-4">
        <header>
            <p
                class="text-xs font-semibold tracking-widest text-orange-500 uppercase"
            >
                Method · v{{ calibration.method_version }}
            </p>
            <h1 class="text-2xl font-semibold tracking-tight">
                How Continuum harmonizes MODIS and VIIRS hot spots
            </h1>
            <p class="mt-2 text-sm text-muted-foreground">
                The record is split across two instruments that disagree by
                design. Continuum makes the disagreement explicit, removes the
                part that is pure geometry, fits the part that is sensor physics
                on the years both instruments flew, and reports how well the fit
                holds on days it never saw.
            </p>
        </header>

        <SectionCard title="1 · The problem is two problems">
            <div class="prose prose-sm max-w-none dark:prose-invert">
                <ul>
                    <li>
                        <strong>Pixel multiplicity.</strong> A MODIS active-fire
                        pixel is nominally 1 km × 1 km; a VIIRS I-band pixel is
                        375 m × 375 m, about 7× smaller in area. The same fire
                        front is reported once by MODIS and 3–7 times by VIIRS.
                        This is geometry and can be removed exactly.
                    </li>
                    <li>
                        <strong>Sensitivity.</strong> A 375 m pixel needs far
                        less fire to raise its brightness temperature above the
                        background than a 1 km pixel does, so VIIRS detects many
                        small, cool or short fires MODIS never sees. This is
                        physics; it cannot be removed, only modelled. In the
                        bundled samples the VIIRS surplus that survives gridding
                        is still ×4–5 per region.
                    </li>
                    <li>
                        <strong>Fleet size.</strong> MODIS flies on two
                        platforms (Terra 10:30, Aqua 13:30); VIIRS on one
                        (2012), two (2018) and three (2023) platforms, all near
                        13:30. Raw counts grow with every launch even if burning
                        does not.
                    </li>
                </ul>
                <p>
                    Concatenating the two records therefore produces the
                    well-known illusion that burning activity roughly tripled in
                    2012. Every chart in Continuum can overlay that naive merge
                    so the artefact stays visible.
                </p>
            </div>
        </SectionCard>

        <SectionCard title="2 · Common grid and the cell-day">
            <div class="prose prose-sm max-w-none dark:prose-invert">
                <p>
                    Every detection from every platform is snapped to a global
                    grid of roughly equal-area cells
                    {{ gridKm }} km on a side, i.e. MODIS-nadir scale. Rows are
                    latitude bands of constant height; each row is split into as
                    many columns as fit around that parallel, so cells do not
                    shrink toward the poles. Cell ids pack (row, column) into
                    one integer so the database can group billions of pixels
                    cheaply.
                </p>
                <p>
                    The unit of burning activity is the
                    <strong>active cell-day</strong>: one grid cell with at
                    least one detection from an instrument on one UTC day. All
                    platforms of an instrument are merged before counting, so a
                    cell seen by Suomi NPP, NOAA-20 and NOAA-21 on the same day
                    counts once, exactly as a cell seen by Terra and Aqua counts
                    once. The cell-day's fire radiative power (FRP) is the
                    strongest single-platform view of that cell that day. After
                    this step pixel multiplicity and fleet size are gone; what
                    remains between MODIS and VIIRS is sensitivity.
                </p>
                <p>
                    Confidence is normalised to low / nominal / high (MODIS 0–29
                    / 30–79 / 80–100; VIIRS l / n / h). Detections flagged by
                    FIRMS as volcanoes, static land sources or offshore are
                    excluded. Scan and track sizes are kept per pixel so the
                    true footprint is available for area-based studies.
                </p>
            </div>
        </SectionCard>

        <SectionCard title="3 · Two transfer functions, fitted on the overlap">
            <div class="prose prose-sm max-w-none dark:prose-invert">
                <p>
                    Since 20 January 2012 both instruments have observed the
                    same fires. For every area of interest Continuum takes the
                    days on which both instruments reported anywhere on Earth
                    (so an empty day is a real zero, not an outage) and fits:
                </p>
                <ol>
                    <li>
                        <strong>Monthly ratio.</strong> r = Σ MODIS cell-days ÷
                        Σ VIIRS cell-days over overlap days, per calendar month
                        (when a month has at least
                        {{ calibration.min_days }} days) and for the whole year.
                        A {{ calibration.bootstrap_samples }}-sample paired
                        bootstrap (fixed seed {{ calibration.seed }}) gives the
                        95% interval that becomes the shaded band on every
                        estimated period. The inverse direction
                        (VIIRS-equivalent) is fitted separately, not derived, so
                        its interval is honest.
                    </li>
                    <li>
                        <strong>FRP visibility threshold.</strong> A VIIRS
                        cell-day is counted as MODIS-equivalent only if its
                        summed FRP is at least τ megawatts. τ is chosen from the
                        histogram edges ({{ frpEdges.slice(0, 8).join(', ') }} …
                        {{ frpEdges[frpEdges.length - 1] }} MW) to minimise the
                        mean absolute daily error against MODIS. Calendar
                        entries store each period's VIIRS cell-days as counts
                        per FRP bin, so any τ can be re-evaluated instantly. The
                        Harmonization Lab shows the empirical probability that
                        MODIS detected a VIIRS cell as a function of its FRP,
                        which is what makes this threshold physical rather than
                        statistical.
                    </li>
                </ol>
                <p>
                    Whichever method is chosen, the reference sensor's own
                    observations are always used when it flew. Transfers are
                    applied only where the reference is absent (pre-2012 for
                    VIIRS-equivalent series, post-MODIS for MODIS-equivalent
                    series), and every transferred value is flagged
                    <em>estimated</em> in the API, the CSV export and the
                    charts.
                </p>
            </div>
        </SectionCard>

        <SectionCard title="4 · Validation you can see">
            <div class="prose prose-sm max-w-none dark:prose-invert">
                <p>
                    Before a factor is stored it is fitted on three quarters of
                    the overlap days and scored on the interleaved fourth (every
                    fourth day, so every season is represented in both sets).
                    Continuum reports the held-out mean absolute error, bias,
                    RMSE, R² and correlation for the harmonized series
                    <em>and</em> for the naive merge, and the relative
                    improvement between them. A transfer that does not beat the
                    naive merge is shown as such. The stored factor is then
                    refitted on all overlap days.
                </p>
                <p>
                    On the bundled Gran Chaco sample (8 days, 4 platforms) the
                    FRP threshold reduces daily error by more than 80% relative
                    to the naive merge; the monthly ratio by about 75%.
                    Multi-year backfills through the FIRMS Area API turn these
                    small-sample numbers into per-month, per-region
                    climatologies.
                </p>
            </div>
        </SectionCard>

        <SectionCard
            title="5 · Calendar, critical periods and unusual conditions"
        >
            <div class="prose prose-sm max-w-none dark:prose-invert">
                <ul>
                    <li>
                        <strong>Calendar.</strong> Daily, ISO-weekly (Monday
                        start) and monthly native series per instrument, with
                        coverage days, day/night split, confidence mix, FRP
                        totals and the FRP histogram; harmonized values are
                        derived on request so a new calibration never requires a
                        rebuild.
                    </li>
                    <li>
                        <strong>Critical periods.</strong> The climatological
                        season is the run of months (or weeks) whose typical
                        harmonized activity stays above half of the seasonal
                        peak; onset is where it first exceeds a quarter. The
                        latest observation is placed inside or outside that
                        window.
                    </li>
                    <li>
                        <strong>Unusual conditions.</strong> Each week or month
                        is compared with the same period in every other year
                        using the median and the scaled median absolute
                        deviation (robust to one extreme year). With at least
                        {{ anomalies.min_years }} reference years, z ≥
                        {{ anomalies.thresholds.watch }} is a watch, ≥
                        {{ anomalies.thresholds.warning }} a warning and ≥
                        {{ anomalies.thresholds.critical }} critical; the
                        percentile rank is reported alongside.
                    </li>
                </ul>
            </div>
        </SectionCard>

        <SectionCard title="6 · Provenance and limits">
            <div class="prose prose-sm max-w-none dark:prose-invert">
                <p>
                    Every export and API response carries a
                    <strong>method receipt</strong>: sources and collections,
                    ingest runs, grid, method, factor, interval, overlap window,
                    validation and caveats. The main caveats:
                </p>
                <ul>
                    <li>
                        Active-fire products are positive-only. A day without
                        detections can be cloud, an outage or truly no fire;
                        Continuum states how many days an instrument reported
                        anywhere on Earth (coverage days) but does not claim a
                        per-cell observation mask.
                    </li>
                    <li>
                        Near-real-time records are replaced by
                        standard-processing records when a backfill runs; values
                        can change slightly.
                    </li>
                    <li>
                        Rectangular areas only; areas crossing the antimeridian
                        are not supported.
                    </li>
                    <li>
                        Terra's 10:30 overpass observes a different diurnal
                        phase than the 13:30 constellation; the calendar keeps
                        day/night counts so users can restrict comparisons if
                        needed.
                    </li>
                </ul>
                <p>
                    Data: NASA FIRMS MODIS Collection 6.1 (Giglio et al.) and
                    VIIRS Collection 2 375 m (Schroeder et al., 2014) active
                    fire products. Sample data bundled with the app are clipped
                    from the public 7-day feeds; historical backfills use the
                    Area API with a free MAP_KEY.
                </p>
            </div>
        </SectionCard>
    </div>
</template>

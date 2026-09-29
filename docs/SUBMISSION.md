# Submission notes

## Pitch (project page paragraph)

Continuum turns NASA's split active-fire record — MODIS at 1 km since 2000 and VIIRS at 375 m since 2012 — into one continuous burning-activity calendar for any area of interest. It removes the part of the disagreement that is pure geometry by counting distinct 1 km grid cells per day instead of pixels, merges each instrument's platforms so fleet size cannot inflate the series, and models the part that is sensor physics with two transparent transfer functions fitted on the years both instruments flew: a per-month ratio with a bootstrap interval, and an FRP visibility threshold that keeps only the VIIRS cell-days a MODIS pixel could have seen. Every factor is scored on held-out days against the naive merge, every transferred value is flagged as estimated, and every export and API response carries a method receipt. On top of the harmonized record Continuum derives climatological fire seasons, flags unusual weeks and months with robust statistics, maps both sensors on the same grid for any day, and runs as an operational Laravel service with daily ingestion from NASA FIRMS.

## How the judging criteria are addressed

**Impact.** Fire managers, scientists and land managers get a record they can compare across 2012 without the sensor-switch artefact, per area of interest, with uncertainty and provenance — as a web app, a CSV export and a JSON API that downstream tools can consume (`/api/v1/regions/{slug}/calendar`, `/export`). Ingestion is scheduled daily and works without any key; a free MAP_KEY unlocks the full 26-year archive.

**Creativity.** The FRP visibility threshold reframes harmonization as a physical detection-limit question and the Lab page proves it empirically (probability that MODIS detected a VIIRS cell as a function of its FRP). The "show the naive merge" toggle keeps the illusion the challenge warns about visible on every chart. The method receipt makes each number auditable. The calendar stores FRP histograms so any threshold can be re-evaluated instantly.

**Validity.** Interleaved holdout validation with MAE, bias, RMSE, R² and correlation for the harmonized series _and_ the naive merge (`/regions/{slug}/lab`, `/api/v1/regions/{slug}/calibration`); reproducible bootstrap intervals (fixed seed); the reference sensor's own data is never overwritten; coverage days distinguish real zeros from missing data; explicit limits in the Method page and README. Small-sample results on the bundled data: holdout error reduced by 76–95% versus the naive merge across the four preset areas (8 overlap days, 2 holdout days).

**Relevance.** Each clause of the challenge maps to a screen: calendar (`/regions/{slug}`, heatmap and series), area of interest (presets + user-drawn boxes), historical patterns (native and harmonized series, FRP), unusual conditions (`/alerts`, robust z-scores), critical periods (season bar with onset, peak, latest position), early warning (same-day map, alerts, API).

**Presentation.** Overview page tells the story in one screen (raw pixel inflation ×9.2 → ×6 after gridding → transfer closes the rest); Method page and `docs/METHOD.md` explain the science; `docs/DEMO_SCRIPT.md` is a 3-minute walk-through; README gives a two-command setup (`composer run setup`, `php artisan continuum:demo`).

## Pre-submission checklist

- [ ] Make the repository public (no secrets: `.env` is ignored; `FIRMS_MAP_KEY` is read from the environment only).
- [ ] Record the demo video (≤ 3 min) following `docs/DEMO_SCRIPT.md`; upload and link it.
- [ ] Project page: paste the pitch above, the "What Continuum does" table from the README, links to the repo, the live app (if deployed) and the video.
- [ ] Team information and challenge selection on spaceappschallenge.org; submission closes 15 November 2026, 11:59 PM local time (per the 2026 event page — verify on the site).
- [ ] Optional: deploy (any PHP 8.4 + PostgreSQL host; `composer run setup`, `php artisan continuum:demo`, `php artisan schedule:work`, `php artisan queue:work`) and backfill at least one area with a MAP_KEY so the calendar shows multiple years and anomaly screening activates.
- [ ] Re-run `composer test`, `npm run types:check` and `npm run check` before tagging the submission commit.

## What we would do next

- **Key-free archive fallback** through NASA GIBS thermal-anomaly vector tiles, so the 26-year record can be built without a MAP_KEY.
- **True observation masks** from MOD14A1 / VNP14A1 daily fire-mask products to replace the global `coverage_days` proxy with per-cell "observed / cloud / no fire" coverage.
- **Polygon areas of interest** (GeoJSON upload, antimeridian-safe), not only rectangles.
- **Per-biome priors** for the transfer factors so freshly drawn areas start from a plausible ratio and τ before their own overlap statistics accumulate.
- Longer term: burned-area cross-checks (MCD64A1 / VNP64A1) as corroboration, and NOAA-20/21-only continuity once Suomi NPP data delivery ends (announced for 1 November 2026).

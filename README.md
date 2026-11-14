# Continuum

One continuous burning-activity record from NASA's split MODIS (2000→) and VIIRS (2012→) active-fire archives, harmonized on a common 1 km grid, validated on the years both sensors flew, and published as a calendar with uncertainty for any area of interest.

Built for the **NASA Space Apps Challenge 2026** — _Harmonization of MODIS and VIIRS Hot Spots_ — by team Continuum (Elminson and Miguel), Santo Domingo, Dominican Republic.

**Live app:** [continuum.techspark.dev](https://continuum.techspark.dev) · no login needed to explore. To draw your own area, sign in with the shared judge account `judge@continuum.techspark.dev` / `SpaceApps2026!` (up to three areas of about 10° × 10°). English and Spanish.

**Demo video (30 s):** [English](docs/video/continuum-demo-en.mp4) · [Español, English subtitles](docs/video/continuum-demo-es.mp4) (AI-generated voice-over).

**Presentation:** [English](docs/presentation/continuum-en.pdf) · [Español](docs/presentation/continuum-es.pdf) (also as HTML in `docs/presentation/`).

## Challenge

> Understanding how burning activity is distributed across our planet over time, including wildfires and agricultural burns intense enough to be detected by satellite sensors, enables us to anticipate critical periods, guide monitoring efforts, and improve fire response. Satellites have tracked active fire hotspots for more than two decades, but the record is split across sensors whose data cannot be compared directly, leaving fire managers without a consistent picture of when and where burning has occurred. Your challenge is to build a web application that harmonizes these records into a burning activity calendar based on active fire hotspots, enabling early warning and emergency responders, scientists, and land managers to examine historical fire patterns, unusual conditions, and critical periods within a selected area of interest.

— [spaceappschallenge.org](https://www.spaceappschallenge.org/2026/challenges/harmonization-of-modis-and-viirs-hot-spots/)

## What Continuum does

| Challenge clause                                       | Continuum                                                                                                                                                                                                                      |
| ------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| _a web application_                                    | Laravel 13 + Inertia/Vue app with public pages (no login needed to explore) and a public JSON API.                                                                                                                             |
| _harmonizes these records_                             | Snaps every MODIS and VIIRS pixel to one ~1 km equal-area grid, collapses them to **active cell-days**, then fits two transfer functions on the 2012→ overlap so either sensor's record can be expressed on the other's scale. |
| _into a burning activity calendar_                     | Daily, weekly and monthly calendar per area, as a time series with confidence band, a year × month/week heatmap, and a CSV export that carries its own provenance.                                                             |
| _within a selected area of interest_                   | Four public areas with the full Nov 2000 → today record (Gran Chaco, Cerrado & Amazon arc, Hispaniola, Cuba); signed-in users draw a box on the map or type its corners, and the full 2000→ history is fetched and harmonized for them automatically, newest windows first. |
| _examine historical fire patterns_                     | Harmonized series with native MODIS and VIIRS overlays, FRP totals, day/night and confidence mix.                                                                                                                              |
| _unusual conditions_                                   | Robust anomaly screening (median ± MAD against the same week/month in other years) with watch / warning / critical severities.                                                                                                 |
| _critical periods_                                     | Climatological fire season (start, end, onset, peak) derived from the harmonized record, with the latest observation placed inside or outside it.                                                                              |
| _early warning, responders, scientists, land managers_ | Same-day map of both sensors on the same grid, alerts page, Harmonization Lab that shows the fit and its held-out error, API for downstream tools.                                                                             |

## Why it is valid

The disagreement between the two records is really three problems, and Continuum treats each one differently:

1. **Pixel multiplicity** (geometry). A MODIS pixel is nominally 1 km; a VIIRS I-band pixel is 375 m, about 7× smaller in area, so one fire front is reported once by MODIS and 3–7 times by VIIRS. This is removed exactly by counting distinct **1 km grid cells** per UTC day (the _cell-day_) instead of pixels.
2. **Fleet size**. MODIS flies on Terra and Aqua; VIIRS on one, then two, then three platforms. All platforms of an instrument are merged before counting, so a cell seen by three VIIRS satellites counts once.
3. **Sensitivity** (physics). A 375 m pixel detects fires far too small or cool for a 1 km pixel. This cannot be removed, only modelled, so Continuum fits it on the overlap period with two transparent transfer functions:
    - **Monthly ratio** — Σ MODIS cell-days ÷ Σ VIIRS cell-days per calendar month and all-year, with a 400-sample paired bootstrap 95% interval (fixed seed, reproducible).
    - **FRP visibility threshold** — a VIIRS cell-day counts as MODIS-equivalent only if its summed fire radiative power reaches τ MW; τ is chosen to minimise the daily error against MODIS. The Lab shows the empirical probability that MODIS detected a VIIRS cell as a function of its FRP, which is what makes τ physical rather than a fudge factor.

Every factor is fitted on three quarters of the overlap days and scored on an **interleaved holdout** (every fourth day), and the app reports MAE, bias, RMSE, R² and correlation for the harmonized series **and for the naive merge**, so you can see whether the transfer beats doing nothing. The reference sensor's own observations are always used when it flew; transfers are applied only where it is absent, and every transferred value is flagged `estimated`. A **method receipt** (sources, ingest runs, grid, method, factor, interval, validation, caveats) ships with every export and API response.

Measured live on the four public areas (all-year monthly ratio → MODIS-equivalent, fitted on the 2012-01-20 → 2026-11 overlap and scored on interleaved held-out days):

| Area                 | MODIS/VIIRS cell-day ratio [95% CI] | Held-out days | Error vs naive merge  | r    |
| -------------------- | ----------------------------------- | ------------- | --------------------- | ---- |
| Hispaniola           | 0.128 [0.124, 0.132]                | 1,352         | MAE 3.0 vs 26.6 (−89%) | 0.63 |
| Cuba                 | 0.230 [0.222, 0.237]                | 1,344         | MAE 8.4 vs 58.8 (−86%) | 0.85 |
| Gran Chaco           | 0.361 [0.355, 0.367]                | 1,353         | MAE 110 vs 663 (−83%)  | 0.91 |
| Cerrado & Amazon arc | 0.387 [0.373, 0.401]                | 1,220         | MAE 58 vs 241 (−76%)   | 0.85 |

The FRP visibility threshold scores within a few points of the ratio on every area. Hispaniola has the weakest daily fit (r 0.63), probably because its fires are small and scattered, so daily counts are noisy; the app flags it in the Method page's "where it works worst" table. Live numbers for any area: `/regions/{slug}/lab` and `/api/v1/regions/{slug}/calibration`.

## Quick start

Requirements: PHP 8.4, Composer, Node 22+, PostgreSQL 15+ (no PostGIS needed). Cache and queue default to the `database` driver, so Redis is optional.

```bash
git clone <this repository> continuum && cd continuum
cp .env.example .env          # then set DB_DATABASE / DB_USERNAME / DB_PASSWORD for your PostgreSQL
createdb continuum            # or create the database any other way
composer run setup            # composer install, app key, migrate, npm install, npm run build
php artisan continuum:demo    # seeds 4 preset areas, loads the bundled NASA FIRMS samples, harmonizes
composer run dev              # server + queue worker + logs + Vite
```

Open <http://localhost:8000>. Sign up if you want to draw your own areas of interest.

## Get the full 26-year record

The bundled samples cover eight days. For the multi-year calendar, request a free FIRMS MAP_KEY at <https://firms.modaps.eosdis.nasa.gov/api/map_key/> and set `FIRMS_MAP_KEY` in `.env`.

```bash
php artisan firms:backfill gran-chaco --from=2000-11-01   # queues 5-day Area API windows for MODIS_SP, VIIRS_SNPP_SP, VIIRS_NOAA20_SP
php artisan queue:work --queue=priority,default           # runs them; harmonization is dispatched when the batch finishes
```

Creating an area in the UI queues the same history automatically when `FIRMS_MAP_KEY` is set (standard archive of every instrument plus the last 70 days of near-real-time data, newest first), and the area page shows the progress; the command is the equivalent for scripts. The newest 150 windows of a new area and every calendar refresh go to the `priority` queue so they run ahead of older backfills, the calendar is refreshed at milestones while windows arrive, windows already fetched for a box that contains the area are skipped (FIRMS charges per day requested, not per area), and each request is padded by `FIRMS_FETCH_PADDING_DEG` degrees so nearby areas added later are free.

For a long backfill, run the worker in its own terminal (`php artisan queue:work --queue=priority,default`) rather than inside `composer run dev`: the dev dashboard keeps recent output in memory and thousands of job lines over several hours can exhaust it.

One worker is enough. FIRMS charges roughly ten transactions for each five-day, country-sized request, so a single worker already uses most of the 5,000-per-10-minute budget; the app tracks that budget (`FIRMS_RATE_LIMIT`, `FIRMS_TRANSACTION_WEIGHT`) and any extra worker simply waits for the next window instead of failing.

Keep the record current with the key-free public feeds:

```bash
php artisan firms:ingest-public --window=7d --presets     # last 7 days, all four feeds, clipped to the preset areas
php artisan continuum:harmonize
php artisan schedule:work                                 # daily: 48h ingest at 03:00 UTC, harmonize at 04:30 UTC
```

Standard-processing backfills replace near-real-time rows for the same window, so values can change slightly after a backfill.

## Commands

| Command                           | What it does                                                                                      | Key options                                                                                                                                 |
| --------------------------------- | ------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| `firms:import {path}`             | Import a FIRMS CSV or CSV.GZ (public feed, Area API response or archive download).                | `--bbox=w,s,e,n`, `--source=label`                                                                                                          |
| `firms:ingest-public`             | Download and import the key-free global feeds.                                                    | `--feed=modis\|viirs-snpp\|viirs-noaa20\|viirs-noaa21` (repeatable, default all), `--window=24h\|48h\|7d`, `--bbox`, `--presets`, `--queue` |
| `firms:backfill {region}`         | Backfill a region from the Area API, five days per request (needs `FIRMS_MAP_KEY`).               | `--source=MODIS_SP` (repeatable), `--from`, `--to`, `--sync`                                                                                |
| `continuum:harmonize [region...]` | Aggregate detections to cell-days, rebuild day/week/month calendars, calibrate, screen anomalies. | `--skip-aggregate`, `--from`, `--to`                                                                                                        |
| `continuum:demo`                  | Seed presets, load bundled samples, run `continuum:harmonize`.                                    | `--fresh`, `--region=slug` (repeatable)                                                                                                     |

## API

Read-only, no key, 120 requests per minute per client. Base URL `/api/v1`. Full description at `/api-docs` in the app.

| Endpoint                               | Returns                                                                                                                                                                                                                    |
| -------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `GET /regions`                         | Preset areas with links.                                                                                                                                                                                                   |
| `GET /regions/{slug}`                  | One area with its summary (recent activity, calibration status, alerts).                                                                                                                                                   |
| `GET /regions/{slug}/calendar`         | Harmonized calendar. Query `period=day\|week\|month`, `method=ratio\|frp_threshold`, `reference=modis\|viirs`, `from`, `to`. Includes native values, `estimated` flags, intervals, the naive merge and the method receipt. |
| `GET /regions/{slug}/calibration`      | All fitted factors with intervals and held-out validation.                                                                                                                                                                 |
| `GET /regions/{slug}/critical-periods` | Climatological season, peak, onset and the latest period's position.                                                                                                                                                       |
| `GET /regions/{slug}/alerts`           | Unusual periods, paginated. Query `period`.                                                                                                                                                                                |
| `GET /regions/{slug}/export`           | CSV of the harmonized calendar with the receipt as `#` comment lines. Same query as `/calendar`.                                                                                                                           |
| `GET /hotspots`                        | GeoJSON of 1 km cell-days (or raw pixels with `raw=1`) for one day. Query `bbox=w,s,e,n`, `date=YYYY-MM-DD` (both required), `instrument`, `limit` ≤ 20000.                                                                |

```bash
curl "http://localhost:8000/api/v1/regions/gran-chaco/calendar?period=month&method=frp_threshold&reference=modis"
curl "http://localhost:8000/api/v1/hotspots?bbox=-70,-35,-55,-15&date=2026-09-24&instrument=viirs"
```

## Architecture

Ingestion (`CsvParser` → `IngestDetections`, idempotent on a per-pixel fingerprint) feeds a `detections` table; `AggregateCellDays` collapses pixels to one row per (grid cell, day, satellite); `BuildCalendar` rolls cell-days into per-instrument day/week/month entries with coverage days and an FRP histogram; `CalibrateRegion` fits and validates the transfer functions; `HarmonizedCalendar`, `CriticalPeriods` and `DetectAnomalies` derive everything the pages and the API show. Details, table layout, the data flowchart and a [deployment and data-refresh diagram](docs/ARCHITECTURE.md#deployment-and-data-refresh) (repo → server → NASA FIRMS) in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md); the science in [docs/METHOD.md](docs/METHOD.md); data sources in [docs/DATA.md](docs/DATA.md).

## Tech stack

Laravel 13 · PHP 8.4 · Inertia 3 + Vue 3 + TypeScript · Tailwind CSS 4 · shadcn-vue · Laravel Wayfinder · Laravel Fortify · Pest 5 · Laravel Pint · Larastan (PHPStan level 7) · Laravel Boost · MapLibre GL · Apache ECharts · PostgreSQL.

## Testing & quality

```bash
composer test               # Pint check, PHPStan, then the Pest suite (298 tests)
composer run types:check    # PHPStan level 7 over app/, config/, database/, routes/
npm run types:check         # vue-tsc
npm run check               # vite-plus lint + format check
```

`.github/workflows/ci.yml` runs code style, static analysis, the frontend build and the test suite against a PostgreSQL service on every push and pull request.

## Data & credits

- NASA FIRMS **MODIS Collection 6.1** active fire (Terra + Aqua, 1 km) and **VIIRS Collection 2 375 m** active fire (Suomi NPP, NOAA-20, NOAA-21). Bundled samples are clipped from the public 7-day feeds; historical backfills use the FIRMS Area API; the live site pulls the key-free global feeds every hour. Suomi NPP stopped delivering observations on 2 November 2026, so near-real-time pulls now use MODIS, NOAA-20 and NOAA-21 while the Suomi NPP archive stays in the record.
- Schroeder, W., Oliva, P., Giglio, L., Csiszar, I. A. (2014). _The New VIIRS 375 m active fire detection data product: Algorithm description and initial assessment._ Remote Sensing of Environment, 143, 85–96.
- Giglio, L., Schroeder, W., Justice, C. O. (2016). _The collection 6 MODIS active fire detection algorithm and fire products._ Remote Sensing of Environment, 178, 31–41.
- Basemap imagery: NASA GIBS (VIIRS NOAA-20 corrected reflectance true colour for the selected day; Blue Marble otherwise); borders and place names: Esri World Boundaries and Places.
- Challenge image photo credit: CONAE (Comisión Nacional de Actividades Espaciales, Argentina).

## Limitations

- Active-fire products are positive-only. A day with no detections may be cloud, an outage or truly no fire. Continuum reports how many days an instrument reported anywhere on Earth (`coverage_days`) but does not claim a per-cell observation mask.
- Near-real-time records are replaced by standard-processing records when a backfill runs; values can change slightly.
- Areas of interest are rectangles; areas crossing the antimeridian are not supported.
- Anomaly screening needs at least three reference years for the same week or month, so it stays silent on the bundled sample.
- The bundled sample used by `continuum:demo` covers 8 days; run a backfill to reproduce the live numbers above.

## Use of AI

- **Code:** written with Claude Code (Anthropic) as a coding assistant; those commits carry a `Co-Authored-By: Claude` line.
- **In the app:** the optional "AI reading" on an unusual period comes from a self-hosted open model (Gemma 4 26B on Ollama) and is labelled _advisory_. The statistics flag the period; the model only explains it.
- **Demo video:** the narration is AI-generated text-to-speech, stated on screen and in the file metadata; every visual is a real screenshot of the app.
- **Presentation and docs:** the slides, the submission text, this README and the speaker script were drafted with Claude and edited by the team.

Most of the app was built before the hackathon weekend; see the [build timeline](docs/BUILD-TIMELINE.md).

## License

MIT — see [LICENSE](LICENSE).

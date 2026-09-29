# Data sources

All fire data come from NASA's Fire Information for Resource Management System (FIRMS). Continuum reads three kinds of input; all are parsed by the same `App\Services\Firms\CsvParser`.

## 1. Public global feeds (no key)

Text feeds published at `https://firms.modaps.eosdis.nasa.gov/` for the last 24 h, 48 h and 7 days (`FeedWindow`), one file per platform (`PublicFeed`):

| Feed (`--feed`) | Path under the FIRMS host                                                    | Instrument               |
| --------------- | ---------------------------------------------------------------------------- | ------------------------ |
| `modis`         | `data/active_fire/modis-c6.1/csv/MODIS_C6_1_Global_{24h\|48h\|7d}.csv`       | MODIS C6.1, Terra + Aqua |
| `viirs-snpp`    | `data/active_fire/suomi-npp-viirs-c2/csv/SUOMI_VIIRS_C2_Global_{window}.csv` | VIIRS C2, Suomi NPP      |
| `viirs-noaa20`  | `data/active_fire/noaa-20-viirs-c2/csv/J1_VIIRS_C2_Global_{window}.csv`      | VIIRS C2, NOAA-20        |
| `viirs-noaa21`  | `data/active_fire/noaa-21-viirs-c2/csv/J2_VIIRS_C2_Global_{window}.csv`      | VIIRS C2, NOAA-21        |

`PublicFeedClient` downloads with retries to `storage/app/private/firms/feeds/` and verifies the file starts with `latitude,longitude`. `firms:ingest-public` imports every feed (optionally clipped with `--bbox` or `--presets`) and deletes the temporary file afterwards. Base URL: `FIRMS_BASE_URL`.

## 2. Area API (free MAP_KEY)

`https://firms.modaps.eosdis.nasa.gov/api/area/csv/{MAP_KEY}/{SOURCE}/{west,south,east,north}/{days}/{YYYY-MM-DD}` — at most **5 days** per request. Sources (`FirmsSource`) and the earliest date each one provides:

| Source                                            | Earliest date used by `firms:backfill` |
| ------------------------------------------------- | -------------------------------------- |
| `MODIS_SP`                                        | 2000-11-01                             |
| `VIIRS_SNPP_SP`                                   | 2012-01-20                             |
| `VIIRS_NOAA20_SP`                                 | 2018-01-01                             |
| `VIIRS_NOAA21_NRT`                                | 2023-01-17                             |
| `MODIS_NRT`, `VIIRS_SNPP_NRT`, `VIIRS_NOAA20_NRT` | recent months only (NRT)               |

Default backfill sources are `MODIS_SP`, `VIIRS_SNPP_SP` and `VIIRS_NOAA20_SP`. Request a key at <https://firms.modaps.eosdis.nasa.gov/api/map_key/> and set `FIRMS_MAP_KEY`.

**Rate limit.** FIRMS allows 5000 transactions per 10-minute window per key (larger windows may count as several transactions). `AreaApiClient` enforces a local budget of `FIRMS_RATE_LIMIT` (default 4500) per 600 s through Laravel's `RateLimiter`, throws a clear `FirmsException` for a missing or invalid key, and validates that responses are CSV. Standard-processing (`_SP`) windows purge the NRT/URT/RT rows they replace (`PurgeNearRealTime`) before inserting.

## 3. Files

`firms:import {path}` accepts plain or gzip CSV: a saved public feed, an Area API response, or an archive download from the FIRMS "Archive Download" tool (these include `instrument` and `type` columns and may spell satellites as `Terra`/`Aqua`). `--bbox` clips on import.

## Columns parsed

Required: `latitude`, `longitude`, `acq_date`, `acq_time`, `satellite`, `confidence`, `frp`, `scan`, `track`, and the instrument's brightness column. The instrument is detected from the header (`bright_ti4` present ⇒ VIIRS, otherwise MODIS) unless an `instrument` column says otherwise.

| Stored field                   | MODIS column                                | VIIRS column                                                    | Notes                                                                   |
| ------------------------------ | ------------------------------------------- | --------------------------------------------------------------- | ----------------------------------------------------------------------- |
| `brightness`                   | `brightness` (ch. 21/22, K)                 | `bright_ti4` (I-4, K)                                           |                                                                         |
| `brightness_secondary`         | `bright_t31`                                | `bright_ti5`                                                    | optional                                                                |
| `scan`, `track`                | same                                        | same                                                            | actual pixel size (km); floored at 0.01; `footprint_km2 = scan × track` |
| `frp`                          | `frp`                                       | `frp`                                                           | MW, floored at 0                                                        |
| `acquired_at`, `acq_date`      | `acq_date` + `acq_time` (HHMM, zero-padded) | same                                                            | UTC                                                                     |
| `confidence_raw`, `confidence` | 0–100                                       | `l/n/h` or `low/nominal/high`                                   | see mapping                                                             |
| `daynight`                     | `D`/`N`                                     | `D`/`N`                                                         |                                                                         |
| `version`                      | e.g. `6.1NRT`, `6.1`                        | e.g. `2.0NRT`, `2.0URT`, `2.0`                                  | used to identify NRT rows for purging                                   |
| `satellite`                    | `T`/`Terra`, `A`/`Aqua`                     | `N`/`NPP`/`Suomi NPP`, `N20`/`NOAA-20`/`1`, `N21`/`NOAA-21`/`2` | `Satellite::fromFirms`                                                  |

Rows with invalid coordinates or an unparseable date/time are skipped and counted in `rows_skipped`; unknown satellite codes are skipped the same way.

### Confidence mapping (`ConfidenceClass`)

| Class   | MODIS  | VIIRS                                |
| ------- | ------ | ------------------------------------ |
| low     | 0–29   | `l`, `low`                           |
| nominal | 30–79  | `n`, `nominal` (and any other value) |
| high    | 80–100 | `h`, `high`                          |

### `type` filtering

FIRMS archive files carry a `type` column: 0 = presumed vegetation fire, 1 = active volcano, 2 = other static land source, 3 = offshore. When the column exists, rows with `type ≠ 0` are excluded (`CsvParser::$includeStaticSources = false`). Public feeds have no `type` column, so nothing is filtered there.

### Fingerprint

Every pixel gets `md5(satellite | acquisition minute | latitude(5 dp) | longitude(5 dp))`. Ingestion uses `insertOrIgnore` on it, so overlapping 24h/48h/7d feeds or repeated imports never duplicate rows. Standard-processing rows may differ slightly in position from their NRT counterparts, which is why SP backfills purge NRT rows for the window first.

## Bundled samples (`database/samples/`)

Sixteen gzip CSVs, ≈ 7.6 MB, clipped from the public 7-day feeds of 2026-09-25 (dates 2026-09-18 → 2026-09-25) for the four preset areas — `gran-chaco`, `cerrado-amazon-arc`, `northern-australia`, `borneo-sumatra` — one file per platform (`{slug}__{modis|viirs-snpp|viirs-noaa20|viirs-noaa21}.csv.gz`). `php artisan continuum:demo` seeds the presets from `config/continuum.php`, imports each file clipped to its region's box, and runs `continuum:harmonize`. Samples are real NASA data, unmodified apart from the spatial clip.

## Credits

NASA FIRMS, part of NASA's Earth Science Data Systems (ESDS) program; MODIS Collection 6.1 active fire product (Giglio et al.); VIIRS 375 m active fire product (Schroeder et al., 2014, _Remote Sensing of Environment_ 143, 85–96). Basemap imagery: NASA GIBS (VIIRS NOAA-20 corrected reflectance true colour for the selected day; Blue Marble otherwise). Borders and place names: Esri World Boundaries and Places.

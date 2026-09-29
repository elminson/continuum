# Method

Continuum method version **2026.1** (`config('continuum.calibration.method_version')`). This document expands the six sections of the in-app Method page (`/method`) with the exact rules the code applies.

## 1. The problem is three problems

MODIS (Terra 1999, Aqua 2002) reports active fires at 1 km nadir resolution; VIIRS (Suomi NPP 2011, NOAA-20 2017, NOAA-21 2022) at 375 m. Their records disagree for three separable reasons:

| Cause                                                                                                                        | Nature   | Treatment                                        |
| ---------------------------------------------------------------------------------------------------------------------------- | -------- | ------------------------------------------------ |
| **Pixel multiplicity** — a 1 km footprint holds ~7 VIIRS footprints, so one fire front is 1 MODIS pixel and 3–7 VIIRS pixels | geometry | removed exactly by gridding (§2)                 |
| **Fleet size** — 2 MODIS platforms; 1, 2 then 3 VIIRS platforms                                                              | sampling | removed by merging platforms per instrument (§2) |
| **Sensitivity** — a 375 m pixel needs far less fire to exceed its background than a 1 km pixel                               | physics  | modelled on the overlap period (§3)              |

Concatenating the raw records therefore produces an apparent jump in 2012 that is not a change in burning. Continuum keeps that _naive merge_ (`naive` in every calendar point: VIIRS cell-days where VIIRS has coverage, otherwise MODIS) so the artefact is always visible next to the harmonized series.

## 2. Common grid and the cell-day

### Grid (`App\Support\Geo\Grid`)

- Cell size `cellKm` = `CONTINUUM_GRID_KM` (default 1 km).
- Rows are latitude bands of constant height `Δφ = cellKm / 111.319491°`.
- Row `r` (centre latitude `φ_r`) is divided into `N_r = ceil(360 · cos φ_r / Δφ)` columns, so cells stay ≈ `cellKm × cellKm` at every latitude instead of shrinking toward the poles.
- `row = floor((φ + 90) / Δφ)`, `col = floor((λ + 180) / (360 / N_r))`, `cell_id = (row << 20) | col`.

A plain 0.25° lat/lon grid (`LatLonGrid`, `CONTINUUM_CALENDAR_CELL_DEG`) is also stored per detection for map density.

### Cell-day

The unit of burning activity is the **active cell-day**: one grid cell with at least one detection from an instrument on one UTC day (`acq_date`).

1. `AggregateCellDays` collapses raw pixels to one row per `(cell_id, acq_date, satellite)` with counts, day/night and confidence splits, `frp_sum`, `frp_max` and summed footprint (`scan × track`).
2. `BuildCalendar` merges the satellites of an instrument per `(cell_id, acq_date)`: detections are summed, and the cell-day's FRP is `max` over satellites of `frp_sum` — the strongest single-platform view — so three VIIRS overpasses do not triple the radiative power of a cell.
3. Per instrument and period it stores `cell_days` = count of distinct (cell, day), `active_cells` = distinct cells, `active_days`, `detections`, `frp_sum`, `frp_max`, `footprint_km2`, day/night and low/nominal/high counts.

Confidence is normalised (`ConfidenceClass`): MODIS 0–29 → low, 30–79 → nominal, 80–100 → high; VIIRS `l`/`low`, `n`/`nominal`, `h`/`high`. When a CSV has a `type` column, rows with `type ≠ 0` (active volcano, other static land source, offshore) are skipped.

### Coverage

`coverage_days` for an (instrument, period) is the number of days in the period on which that instrument produced **any cell-day anywhere in the database**. A region entry with `coverage_days > 0` and `cell_days = 0` is therefore a real zero; `coverage_days = 0` means "no data", and such periods yield `null` in the harmonized series. This is a global-availability proxy, not a per-cell observation mask (see §6).

### FRP histogram

Each calendar entry stores its cell-days as counts per FRP bin. Bin lower edges (`FrpHistogram::EDGES`, MW):

```
0, 0.5, 1, 1.5, 2, 3, 4, 5, 7.5, 10, 15, 20, 30, 40, 50, 75, 100,
150, 200, 300, 500, 750, 1000, 1500, 2000, 5000   (last bin open-ended)
```

`countAtOrAbove(histogram, τ)` sums the bins whose lower edge is ≥ the bin that contains τ, which lets any threshold be evaluated without touching cell-level rows.

## 3. Transfer functions (`CalibrateRegion`)

### Overlap days

Overlap days are daily entries with `period_start ≥ 2012-01-20` (`config('continuum.record.viirs_start')`) where **both** instruments have `coverage_days > 0`. Calibration requires at least `min_days` = 5 overlap days, of which at least 5 with any activity (`modis + viirs > 0`); otherwise the region is reported as `insufficient_overlap` or `no_activity` and no factor is stored.

### Holdout

Overlap days are ordered by date; every 4th day (`HOLDOUT_EVERY = 4`) goes to the test set when there are at least 8 overlap days, the rest to training. The interleaving keeps every season in both sets. Factors are fitted on the training set, scored on the test set, then **refitted on all overlap days** for storage.

### Method A — monthly ratio (`ratio`)

For reference sensor R and the other sensor O, with cell-days `R_d`, `O_d` on overlap day d:

```
factor_m = Σ_{d ∈ month m} R_d / Σ_{d ∈ month m} O_d        (m = 1..12, when month m has ≥ 5 days)
factor_0 = Σ_d R_d / Σ_d O_d                                 (all-year fallback)
estimate_d = O_d · factor_{month(d)}  (falls back to factor_0)
```

The 95% interval is a paired bootstrap of the ratio of sums: 400 resamples (`bootstrap_samples`) of the (R_d, O_d) pairs with replacement, seed 20261115, percentiles 2.5 and 97.5. Both directions are fitted independently (`reference = modis`: MODIS/VIIRS; `reference = viirs`: VIIRS/MODIS), so the VIIRS-equivalent interval is not the inverse of the MODIS-equivalent one.

### Method B — FRP visibility threshold (`frp_threshold`, reference MODIS only)

```
estimate_d(τ) = countAtOrAbove(VIIRS_histogram_d, τ)
τ* = argmin_{τ ∈ EDGES} (1/n) Σ_d | estimate_d(τ) − MODIS_d |
```

Ties go to the smaller τ. Per-month τ values are fitted when a month has ≥ 5 days, with the all-year τ as fallback. No interval is computed for τ (`ci_low = ci_high = τ`).

### Validation record

Stored in the all-year factor's `validation` JSON:

```
train_days, test_days, holdout,
harmonized: {n, bias, mae, rmse, r2, mape},   -- estimate vs reference on test days
naive:      {n, bias, mae, rmse, r2, mape},   -- other sensor's raw cell-days vs reference
mae_improvement = 1 − harmonized.mae / naive.mae,
correlation (Pearson, estimate vs reference)
```

Non-finite factors (division by zero) are stored as 99999999.9999.

## 4. Harmonized series (`HarmonizedCalendar`)

For each period and chosen (method, reference):

1. If the reference instrument has `coverage_days > 0`: `harmonized = low = high = reference.cell_days`, `estimated = false`, `source = reference`.
2. Else, if the other instrument has coverage and a factor exists: `estimated = true`, `source = other`;
    - ratio: `harmonized = other.cell_days · factor`, `low/high = other.cell_days · ci_low/ci_high`;
    - frp_threshold: `harmonized = countAtOrAbove(other.frp_histogram, τ)`.
3. Else `harmonized = null`.

The factor is the month's factor if present, otherwise the all-year factor. `frp_threshold` with `reference = viirs` is not defined and falls back to `ratio`.

## 5. Critical periods and unusual conditions

### Critical periods (`CriticalPeriods`)

From the harmonized series (default monthly, ratio, MODIS-equivalent) it computes the climatology: for each seasonal index (month 1–12, ISO week 1–52 or day of year) the **median** of the harmonized values across years.

- Peak = index with the highest median.
- Season = the contiguous run around the peak (wrapping across the year) where the median stays ≥ `SEASON_SHARE = 0.5` × peak.
- Onset = extending backwards from the season start while the median stays ≥ `ONSET_SHARE = 0.25` × peak.
- The latest observed period is reported with `in_season`.

### Unusual conditions (`DetectAnomalies`)

Weeks and months (ISO weeks start Monday) are compared with the same seasonal index in all **other** years:

```
expected = median(other years)
spread   = 1.4826 · MAD(other years)     (if 0: max(1, 0.25 · expected))
z        = (observed − expected) / spread
```

At least `min_years = 3` reference years are required. Severity from `z`: ≥ 1.5 watch, ≥ 2.5 warning, ≥ 4.0 critical. Alerts also store the percentile rank of the observation within the reference years and a plain-language message. `HarmonizeRegion` runs this for weeks and months with `ratio` / `modis`.

## 6. Provenance and limits

`MethodReceipt` attaches to every calendar API response and CSV export: application and method version, region and bounding box, grid definition, method, reference, factor, 95% interval, overlap window and days, validation, calibration time, data sources, the latest 20 completed ingest runs, and caveats.

Limits the method does not hide:

- Detections are positive-only; `coverage_days` is a global proxy, not a per-cell observation mask.
- NRT/URT/RT rows are purged and replaced when a standard-processing backfill covers the same window and box.
- Terra's 10:30 overpass samples a different diurnal phase than the 13:30 constellation; day/night counts are kept so users can restrict comparisons.
- Rectangular areas only; no antimeridian crossing.

## CSV export columns (`GET /api/v1/regions/{slug}/export`)

Lines starting with `#` carry the receipt (application, region and bbox, method/reference/factor/version, grid and cell-day definition, generation time). Then:

| Column                                                                       | Meaning                                                                              |
| ---------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| `period_start`                                                               | First day of the period (day / ISO week Monday / month).                             |
| `harmonized_cell_days`                                                       | Harmonized active cell-days on the reference scale.                                  |
| `ci_low`, `ci_high`                                                          | 95% bootstrap interval (equal to the value when not estimated or for the τ method).  |
| `estimated`                                                                  | 1 if transferred from the other sensor, 0 if the reference sensor's own observation. |
| `source`                                                                     | `modis` or `viirs`: which instrument the value came from.                            |
| `factor`                                                                     | Factor applied (ratio, or τ in MW for `frp_threshold`).                              |
| `naive_cell_days`                                                            | Naive merge: VIIRS if it has coverage, otherwise MODIS.                              |
| `modis_cell_days`, `modis_detections`, `modis_frp_mw`, `modis_coverage_days` | Native MODIS values (empty when no coverage).                                        |
| `viirs_cell_days`, `viirs_detections`, `viirs_frp_mw`, `viirs_coverage_days` | Native VIIRS values (empty when no coverage).                                        |

# When MODIS retires

Built during the event (14 November 2026).

## Why it matters

MODIS has flown on Terra since 1999 and on Aqua since 2002. Both satellites are past their design life and NASA is winding them down. After that, only VIIRS will keep watching fires. The 26-year fire record is on the MODIS scale, and VIIRS sees several times more fire (about 8× on Hispaniola). Unless VIIRS is put on the MODIS scale, the first year without MODIS looks like a sudden jump in fire that never happened.

## What the simulation does

On each area's Lab page (`/regions/{slug}/lab`), section "When MODIS retires", you pick the year MODIS stops. Then:

1. **Fit on the past only.** The monthly ratio (Σ MODIS ÷ Σ VIIRS cell-days, per calendar month plus all-year) is refitted on the overlap days *before* 1 January of that year. Nothing after the cutoff touches the fit.
2. **Rebuild from VIIRS alone.** Every month after the cutoff is rebuilt as VIIRS cell-days × that ratio, with a bootstrap 95% band.
3. **Score against reality.** MODIS is still flying, so the real record for those months exists. The page compares the two:
   - **Total after the cutoff:** estimated vs real MODIS cell-days, in %.
   - **Daily error vs naive:** how much smaller the daily error is than simply using raw VIIRS counts.
   - **Monthly correlation:** how well the month-to-month shape matches.

The same numbers come from `GET /api/v1/regions/{slug}/modis-retirement?year=2024`.

Code: `app/Services/Insights/ModisRetirement.php`.

## Results on the four public areas

MODIS switched off on 1 January 2024 (fit on 2012-01-20 → 2023-12-31, scored on 2024-01-01 → 2026-11):

| Area | Daily error vs naive | Monthly r | Total after cutoff |
| --- | --- | --- | --- |
| Hispaniola | −89% | 0.93 | +25% |
| Cuba | −84% | 0.996 | +18% |
| Gran Chaco | −68% | 0.97 | −22% |
| Cerrado & Amazon arc | −62% | 0.88 | −22% |

## What this shows

- **The month-to-month picture survives.** With VIIRS alone, the daily error is 62–89% smaller than a naive switch, and the monthly shape tracks MODIS closely (r 0.88–0.996). Fire seasons, peaks and unusual weeks stay readable.
- **Totals drift by about ±20% after two to three years.** The ratio between the sensors is not constant over time. Likely causes include changes in the VIIRS fleet (NOAA-20 from 2018, NOAA-21 from 2023) and Terra's orbit drift. We have not separated these causes.
- **No single fix works everywhere.** Fitting only the last one to three years before the cutoff cut the drift to under 10% on Hispaniola and Cuba, but made Gran Chaco and Cerrado worse. So the app keeps the full-history fit and shows the drift openly instead of hiding it.

## Next steps

- Track the ratio over time (per year and per VIIRS satellite) and correct for the trend.
- Fit the ratio per VIIRS satellite instead of for the merged fleet.
- Re-run this test each year while MODIS is still flying, so the final ratio is fitted on the most recent overlap.

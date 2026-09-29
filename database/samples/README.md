# Bundled FIRMS samples

Real NASA FIRMS detections clipped from the public 7-day global feeds
(MODIS C6.1 and VIIRS C2 for Suomi NPP, NOAA-20 and NOAA-21), covering
2026-09-18 to 2026-09-25, for four preset regions:

| Region             | Files                                                             |
| ------------------ | ----------------------------------------------------------------- |
| gran-chaco         | `gran-chaco__{modis,viirs-snpp,viirs-noaa20,viirs-noaa21}.csv.gz` |
| cerrado-amazon-arc | `cerrado-amazon-arc__*.csv.gz`                                    |
| northern-australia | `northern-australia__*.csv.gz`                                    |
| borneo-sumatra     | `borneo-sumatra__*.csv.gz`                                        |

Source: https://firms.modaps.eosdis.nasa.gov/active_fire/ (no key required).
Load them with `php artisan continuum:demo`.

# Space Apps submission

Copy each block into the matching field of the team's Project page (spaceappschallenge.org → My Team → Project → Edit). Everything on that page must be in English.

## Project Name

Continuum

## High-Level Project Summary

Continuum is a web app that turns NASA's split active-fire record into one continuous burning-activity calendar for any area on Earth. MODIS (1 km, since 2000) and VIIRS (375 m, since 2012) count the same fires very differently: on Hispaniola, VIIRS reports about eight times more burning than MODIS. Continuum puts both sensors on one 1 km grid, merges every satellite of each instrument, and models the remaining sensitivity gap with two transparent transfer functions fitted on the 14 years both sensors flew. Scored on more than 1,200 held-out days, the transfer cuts the error of a naive merge by 76–89% on our four public areas. Users pick or draw an area, choose dates, compare the period with the 25-year median, see the climatological fire season, get robust anomaly alerts, and download charts, the harmonized calendar and the raw hotspots as CSV, in English or Spanish. It flagged Cuba's August 2026 wildfires at 21 times a normal week. Continuum gives fire managers, responders, scientists and land managers one consistent 26-year picture of when and where burning happens.

## Link to Project Demo

`<YouTube link to the 30-second video>` (English narration; a Dominican Spanish version with English subtitles is also available: `<link>`)

## Link to Final Project

https://continuum.techspark.dev — no login needed to explore. To draw your own area, sign in as `judge@continuum.techspark.dev` / `SpaceApps2026!`.
Code: https://github.com/elminson/continuum

## Detailed Project Description

**What it does.** Continuum builds a harmonized burning-activity calendar from NASA FIRMS active-fire hotspots for any rectangular area of interest, from November 2000 to today. Four public areas carry the full record: Gran Chaco (Argentina, Paraguay, Bolivia), the Cerrado and Amazon arc of deforestation (Brazil), Hispaniola (Dominican Republic and Haiti) and Cuba. For each area it shows:

- a calendar of fire activity by month and year (heatmap), and a daily, weekly or monthly series with the harmonized value, both native sensors and a 95% interval;
- a date-range picker with key figures: hotspots, harmonized fire activity, the historical median for the same dates and how the period compares with it;
- the climatological fire season (onset, peak, end) and whether the latest observation falls inside it;
- unusual periods: weeks and months screened against the same period in other years with robust statistics (median and MAD), each with its own page, a map of where the fire was and day-by-day counts;
- a same-day map of both sensors on the same grid over NASA GIBS imagery;
- downloads: every chart as PNG, the harmonized calendar as CSV with a method receipt, and the raw hotspots of the search as CSV;
- a public JSON API, so other tools can consume the calendar, alerts and calibration.

Signed-in users draw a box on the map or type its corners, and Continuum fetches the full 2000→ history from the FIRMS Area API and harmonizes it automatically. The interface is available in English and Spanish.

**How it works.** The disagreement between the two records is three different problems, and each is handled on its own:

1. Pixel size. A MODIS pixel is about 1 km; a VIIRS pixel is 375 m, so one fire front is reported once by MODIS and several times by VIIRS. Continuum counts distinct 1 km grid cells with fire per day (cell-days) instead of pixels.
2. Number of satellites. MODIS flies on Terra and Aqua; VIIRS on one, two, then three satellites. All satellites of one instrument are merged before counting.
3. Sensitivity. VIIRS sees fires too small or cool for MODIS. This gap is modelled with two transfer functions fitted on the overlap years: a monthly ratio with a 400-sample bootstrap interval, and a fire-radiative-power visibility threshold that keeps only the VIIRS cell-days a MODIS pixel could have seen.

Each transfer is fitted on three quarters of the overlap days and scored on the remaining quarter (every fourth day) against MODIS, alongside the naive merge. Live results (monthly ratio → MODIS-equivalent): Hispaniola −89% error (1,352 test days), Cuba −86% (1,344), Gran Chaco −83% (1,353), Cerrado −76% (1,220). A sensor's own data is never overwritten; transferred values are flagged as estimated, and every export carries its sources, method, factors and validation. The Method page lists where it works worst.

**Benefits.** Fire managers get a record they can compare across the 2012 sensor switch, so a busy season reads as a busy season and not as a new satellite. Responders get early warning: in the week of 17 August 2026 Continuum flagged Cuba at 321 MODIS-equivalent cell-days, 21.4 times the median of the same week in 25 other years. Fire trackers reported several wildfires across Cuba on 15–25 August 2026. Scientists get reproducible, validated numbers with uncertainty, and land managers get each area's typical fire season.

**What we hope to achieve.** A free, open tool that any fire agency in Latin America and the Caribbean can point at its own territory, starting with the Dominican Republic and Haiti, and that keeps working as MODIS retires: the same method can express the VIIRS-only future on the 26-year MODIS scale.

**Tools.** PHP 8.4, Laravel 13, Inertia 3 with Vue 3 and TypeScript, Tailwind CSS 4, PostgreSQL, MapLibre GL, Apache ECharts, Pest (298 automated tests), PHPStan level 7. It is hosted on a small Linux server that pulls new FIRMS data every hour and re-harmonizes every six hours.

## Use of Super Intelligence (SI)

Yes.
- **Code:** we used Claude Code (Anthropic) as a coding assistant to write, test and review parts of the Laravel and Vue code; those commits are marked `Co-Authored-By: Claude` in the public git history.
- **In the app:** the optional "AI reading" on unusual periods uses a self-hosted open model (Gemma 4 26B, gemma4:26b-a4b, through Ollama) and is labelled "advisory". The statistics flag the period; the model only explains it.
- **Demo video:** the narration is AI-generated text-to-speech (Microsoft neural voices). This is stated on screen, in the video description and in the file metadata. Every visual is a real screenshot of the app.
- **Translation:** the Spanish interface text was drafted with AI assistance.

## NASA Data

- NASA FIRMS (Fire Information for Resource Management System) active fire data, the core of the project:
  - MODIS Collection 6.1 active fire product (Terra and Aqua, 1 km), standard processing and near real-time, November 2000 → today.
  - VIIRS Collection 2 375 m active fire product (Suomi NPP from January 2012, NOAA-20, NOAA-21), standard processing and near real-time.
  - Accessed through the FIRMS Area API (full history per area) and the key-free FIRMS Active Fire Data global feeds (hourly updates). About 26 million detections ingested.
- NASA GIBS imagery for the map basemap (VIIRS NOAA-20 corrected reflectance true colour for the selected day; Blue Marble otherwise).
- NASA Earthdata notice on the end of Suomi NPP observations (2 November 2026), which we followed by moving near-real-time pulls to NOAA-20 and NOAA-21.

## Space Agency Partner Data and Other References

We did not use Space Agency Partner datasets.

Data, code and tools:
- Esri World Boundaries and Places (map labels).
- MapLibre GL JS, Apache ECharts, Laravel, Inertia.js, Vue.js, Tailwind CSS, shadcn-vue, PostgreSQL, Pest, PHPStan (open-source).
- Ollama with Gemma 4 26B (advisory text on alerts).
- Claude Code (Anthropic) coding assistant; Microsoft neural text-to-speech voices, Playwright and FFmpeg for the demo video.

References:
- Giglio, L., Schroeder, W., Justice, C. O. (2016). The collection 6 MODIS active fire detection algorithm and fire products. Remote Sensing of Environment, 178, 31–41.
- Schroeder, W., Oliva, P., Giglio, L., Csiszar, I. A. (2014). The New VIIRS 375 m active fire detection data product: Algorithm description and initial assessment. Remote Sensing of Environment, 143, 85–96.
- NASA FIRMS: https://firms.modaps.eosdis.nasa.gov · NASA Earthdata FIRMS: https://www.earthdata.nasa.gov/data/tools/firms
- Cuba wildfire reports, August 2026 (corroboration of the flagged week): https://currentwildfires.com/en/fire/wildfire-near-cauto-cristo-cu-2026/

## Before clicking "Submit for Judging"

- [ ] Every team member appears on the Members tab and is registered for the Santiago, Dominican Republic event (not waitlisted).
- [ ] The video is uploaded (YouTube, unlisted or public) and the link opens in a private browser window without signing in.
- [ ] The live app and the GitHub link open in a private window.
- [ ] Both "Terms and Conditions" and "Confirm All Work is Original" boxes are checked.
- [ ] Submitted before Sunday 15 November, 23:59 Santiago time; the local video deadline is 15:00.

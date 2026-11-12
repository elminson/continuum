<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Instrument;
use App\Http\Controllers\Controller;
use App\Models\Detection;
use App\Models\Region;
use App\Services\Insights\PeriodComparison;
use App\Services\Insights\RegionSummary;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV of every FIRMS hotspot (MODIS and VIIRS pixels) inside an area for a
 * date range. Without a range it exports the last 30 days of the record.
 * Rows are streamed one day at a time so multi-year exports stay flat in memory.
 */
class HotspotExportController extends Controller
{
    public const array COLUMNS = [
        'acq_date', 'acquired_at_utc', 'latitude', 'longitude', 'instrument', 'satellite',
        'confidence', 'confidence_raw', 'frp_mw', 'brightness_k', 'scan_km', 'track_km',
        'daynight', 'version', 'cell_id',
    ];

    public function __invoke(Request $request, Region $region, RegionSummary $summary, PeriodComparison $comparison): StreamedResponse
    {
        Gate::authorize('view', $region);

        $validated = $request->validate([
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'instrument' => ['sometimes', 'nullable', Rule::enum(Instrument::class)],
        ]);

        [$from, $to] = isset($validated['from'], $validated['to'])
            ? [$validated['from'], $validated['to']]
            : ($comparison->defaultWindow($summary->for($region)['last_date']) ?? [now()->toDateString(), now()->toDateString()]);

        $instrument = isset($validated['instrument']) ? Instrument::from($validated['instrument']) : null;
        $box = $region->bbox();
        $filename = sprintf('continuum_%s_hotspots_%s_%s%s.csv', $region->slug, $from, $to, $instrument ? '_'.$instrument->value : '');

        return response()->streamDownload(function () use ($region, $box, $from, $to, $instrument): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fwrite($out, '# Continuum hotspot export: NASA FIRMS active fire detections'."\n");
            fwrite($out, '# sources: MODIS Collection 6.1 (1 km) and VIIRS 375 m (S-NPP, NOAA-20, NOAA-21)'."\n");
            fwrite($out, '# region: '.$region->name.' bbox='.implode(',', $box->toArray())."\n");
            fwrite($out, '# range: '.$from.' to '.$to.($instrument ? ' instrument='.$instrument->value : '')."\n");
            fwrite($out, '# note: raw pixels, not harmonized; VIIRS reports several pixels per MODIS pixel. Use the calendar export for harmonized counts.'."\n");
            fwrite($out, '# generated: '.now()->toIso8601String()."\n");
            fputcsv($out, self::COLUMNS, escape: '');

            $end = CarbonImmutable::parse($to);

            for ($day = CarbonImmutable::parse($from); $day->lte($end); $day = $day->addDay()) {
                $rows = Detection::query()
                    ->onDate($day->toDateString())
                    ->withinBox($box)
                    ->when($instrument, fn ($query) => $query->forInstrument($instrument))
                    ->orderBy('acquired_at')
                    ->orderBy('id')
                    ->get();

                foreach ($rows as $detection) {
                    fputcsv($out, [
                        $detection->acq_date->toDateString(),
                        $detection->acquired_at->utc()->format('Y-m-d\TH:i\Z'),
                        $detection->latitude,
                        $detection->longitude,
                        $detection->instrument->value,
                        $detection->satellite->label(),
                        $detection->confidence->value,
                        $detection->confidence_raw,
                        $detection->frp,
                        $detection->brightness,
                        $detection->scan,
                        $detection->track,
                        $detection->daynight->value,
                        $detection->version,
                        $detection->cell_id,
                    ], escape: '');
                }

                flush();
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

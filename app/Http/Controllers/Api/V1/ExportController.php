<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalendarQueryRequest;
use App\Models\Region;
use App\Services\Harmonization\HarmonizedCalendar;
use App\Services\Harmonization\MethodReceipt;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export of the harmonized calendar, with the method receipt embedded
 * as comment lines so the file documents its own provenance.
 */
class ExportController extends Controller
{
    public function __invoke(CalendarQueryRequest $request, Region $region, HarmonizedCalendar $calendar, MethodReceipt $receipt): StreamedResponse
    {
        Gate::authorize('view', $region);

        $series = $calendar->series($region, $request->period(), $request->harmonizationMethod(), $request->reference(), $request->from(), $request->to());
        $meta = $receipt->for($region, $request->period(), $request->harmonizationMethod(), $request->reference());
        $filename = sprintf('continuum_%s_%s_%s_%s.csv', $region->slug, $series['period'], $series['method'], $series['reference']);

        return response()->streamDownload(function () use ($series, $meta): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fwrite($out, '# '.$meta['application'].' harmonized burning activity calendar'."\n");
            fwrite($out, '# region: '.$meta['region']['name'].' bbox='.implode(',', $meta['region']['bbox'])."\n");
            fwrite($out, '# method: '.$meta['method']['label'].' reference='.$meta['method']['reference'].' factor='.($meta['method']['factor'] ?? 'n/a').' version='.$meta['method']['version']."\n");
            fwrite($out, '# grid: '.$meta['grid']['harmonization_cell_km'].' km; cell-day = '.$meta['grid']['cell_day_definition']."\n");
            fwrite($out, '# generated: '.$meta['generated_at']."\n");

            fputcsv($out, ['period_start', 'harmonized_cell_days', 'ci_low', 'ci_high', 'estimated', 'source', 'factor', 'naive_cell_days', 'modis_cell_days', 'modis_detections', 'modis_frp_mw', 'modis_coverage_days', 'viirs_cell_days', 'viirs_detections', 'viirs_frp_mw', 'viirs_coverage_days'], escape: '');

            foreach ($series['points'] as $point) {
                fputcsv($out, [
                    $point['period_start'],
                    $point['harmonized'],
                    $point['low'],
                    $point['high'],
                    $point['estimated'] ? 1 : 0,
                    $point['source'],
                    $point['factor'],
                    $point['naive'],
                    $point['modis']['cell_days'] ?? null,
                    $point['modis']['detections'] ?? null,
                    $point['modis']['frp_sum'] ?? null,
                    $point['modis']['coverage_days'] ?? 0,
                    $point['viirs']['cell_days'] ?? null,
                    $point['viirs']['detections'] ?? null,
                    $point['viirs']['frp_sum'] ?? null,
                    $point['viirs']['coverage_days'] ?? 0,
                ], escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

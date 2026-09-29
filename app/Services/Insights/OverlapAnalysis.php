<?php

namespace App\Services\Insights;

use App\Actions\Harmonization\CalibrateRegion;
use App\Enums\Instrument;
use App\Models\Region;
use App\Support\Stats\FrpHistogram;
use App\Support\Stats\Statistics;
use Illuminate\Support\Facades\DB;

/**
 * Diagnostics for the Harmonization Lab: how the two instruments compare
 * on the days they both observed a region.
 */
class OverlapAnalysis
{
    public function __construct(private readonly CalibrateRegion $calibrate) {}

    /**
     * Daily MODIS vs VIIRS cell-day pairs.
     *
     * @return list<array{date: string, month: int, modis: int, viirs: int}>
     */
    public function pairs(Region $region): array
    {
        return array_map(
            fn (array $pair) => ['date' => $pair['date'], 'month' => $pair['month'], 'modis' => $pair['modis'], 'viirs' => $pair['viirs']],
            $this->calibrate->overlapPairs($region),
        );
    }

    /**
     * Error of the FRP-threshold transfer for every candidate threshold.
     *
     * @return list<array{tau: float, mae: float, bias: float, predicted: int, observed: int}>
     */
    public function thresholdCurve(Region $region): array
    {
        $pairs = $this->calibrate->overlapPairs($region);
        $curve = [];

        foreach (FrpHistogram::EDGES as $tau) {
            $predicted = [];
            $observed = [];

            foreach ($pairs as $pair) {
                $predicted[] = FrpHistogram::countAtOrAbove($pair['viirs_histogram'], $tau);
                $observed[] = $pair['modis'];
            }

            $errors = Statistics::errors($predicted, $observed);
            $curve[] = [
                'tau' => (float) $tau,
                'mae' => round($errors['mae'], 2),
                'bias' => round($errors['bias'], 2),
                'predicted' => (int) array_sum($predicted),
                'observed' => (int) array_sum($observed),
            ];
        }

        return $curve;
    }

    /**
     * Probability that a VIIRS cell-day was also detected by MODIS, by
     * VIIRS FRP bin, over the most recent overlap days. This is the
     * empirical justification for the FRP visibility threshold.
     *
     * @return array{days: int, bins: list<array{bin: int, frp_from: float, frp_to: float|null, viirs: int, seen_by_modis: int, probability: float|null}>, modis_only: int, viirs_only: int, both: int}
     */
    public function detectionCurve(Region $region, int $maxDays = 30): array
    {
        $pairs = $this->calibrate->overlapPairs($region);
        $dates = array_slice(array_column($pairs, 'date'), -$maxDays);

        if ($dates === []) {
            return ['days' => 0, 'bins' => [], 'modis_only' => 0, 'viirs_only' => 0, 'both' => 0];
        }

        $box = $region->bbox();
        $rows = DB::table('cell_days')
            ->selectRaw('cell_id, acq_date, instrument, max(frp_sum) as frp')
            ->whereBetween('latitude', [$box->south, $box->north])
            ->whereBetween('longitude', [$box->west, $box->east])
            ->whereIn('acq_date', $dates)
            ->groupBy('cell_id', 'acq_date', 'instrument')
            ->cursor();

        $modis = [];
        $viirs = [];

        foreach ($rows as $row) {
            $key = $row->cell_id.'|'.substr((string) $row->acq_date, 0, 10);

            if ($row->instrument === Instrument::Modis->value) {
                $modis[$key] = true;
            } else {
                $viirs[$key] = (float) $row->frp;
            }
        }

        $binCount = count(FrpHistogram::EDGES);
        $totals = array_fill(0, $binCount, 0);
        $seen = array_fill(0, $binCount, 0);
        $both = 0;

        foreach ($viirs as $key => $frp) {
            $bin = FrpHistogram::binIndex($frp);
            $totals[$bin]++;

            if (isset($modis[$key])) {
                $seen[$bin]++;
                $both++;
            }
        }

        $bins = [];

        foreach (FrpHistogram::EDGES as $i => $edge) {
            $bins[] = [
                'bin' => $i,
                'frp_from' => (float) $edge,
                'frp_to' => isset(FrpHistogram::EDGES[$i + 1]) ? (float) FrpHistogram::EDGES[$i + 1] : null,
                'viirs' => $totals[$i],
                'seen_by_modis' => $seen[$i],
                'probability' => $totals[$i] > 0 ? round($seen[$i] / $totals[$i], 3) : null,
            ];
        }

        return [
            'days' => count($dates),
            'bins' => $bins,
            'modis_only' => count($modis) - $both,
            'viirs_only' => count($viirs) - $both,
            'both' => $both,
        ];
    }
}

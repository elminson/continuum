<?php

namespace App\Support\Stats;

/**
 * Fixed log-spaced histogram of per-cell-day summed FRP (MW).
 *
 * Calendar entries store VIIRS cell-days as counts per bin so that the FRP
 * visibility threshold method can be re-evaluated for any threshold without
 * touching cell-level rows again.
 */
final class FrpHistogram
{
    /**
     * Lower edges of each bin in megawatts. The last bin is open-ended.
     *
     * @var list<float>
     */
    public const array EDGES = [
        0, 0.5, 1, 1.5, 2, 3, 4, 5, 7.5, 10, 15, 20, 30, 40, 50, 75, 100,
        150, 200, 300, 500, 750, 1000, 1500, 2000, 5000,
    ];

    /**
     * SQL CASE mapping a column named frp to its bin index. Kept in sync
     * with EDGES by a unit test.
     */
    public const string SQL_BIN_CASE = '(case when frp >= 5000 then 25 when frp >= 2000 then 24 when frp >= 1500 then 23 when frp >= 1000 then 22 when frp >= 750 then 21 when frp >= 500 then 20 when frp >= 300 then 19 when frp >= 200 then 18 when frp >= 150 then 17 when frp >= 100 then 16 when frp >= 75 then 15 when frp >= 50 then 14 when frp >= 40 then 13 when frp >= 30 then 12 when frp >= 20 then 11 when frp >= 15 then 10 when frp >= 10 then 9 when frp >= 7.5 then 8 when frp >= 5 then 7 when frp >= 4 then 6 when frp >= 3 then 5 when frp >= 2 then 4 when frp >= 1.5 then 3 when frp >= 1 then 2 when frp >= 0.5 then 1 else 0 end)';

    public static function binIndex(float $frp): int
    {
        $index = 0;

        foreach (self::EDGES as $i => $edge) {
            if ($frp >= $edge) {
                $index = $i;
            } else {
                break;
            }
        }

        return $index;
    }

    /**
     * @return list<int>
     */
    public static function empty(): array
    {
        return array_fill(0, count(self::EDGES), 0);
    }

    /**
     * @param  list<int>  $a
     * @param  list<int>  $b
     * @return list<int>
     */
    public static function merge(array $a, array $b): array
    {
        $result = self::empty();

        foreach ($result as $i => $_) {
            $result[$i] = ($a[$i] ?? 0) + ($b[$i] ?? 0);
        }

        return $result;
    }

    /**
     * Number of cell-days whose FRP is at or above the given threshold. The
     * threshold snaps down to the nearest bin edge.
     *
     * @param  list<int>  $counts
     */
    public static function countAtOrAbove(array $counts, float $threshold): int
    {
        $start = self::binIndex($threshold);
        $total = 0;

        for ($i = $start, $n = count(self::EDGES); $i < $n; $i++) {
            $total += $counts[$i] ?? 0;
        }

        return $total;
    }

    /**
     * @param  list<int>  $counts
     */
    public static function total(array $counts): int
    {
        return (int) array_sum($counts);
    }
}

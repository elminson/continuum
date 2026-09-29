<?php

namespace App\Support\Stats;

use InvalidArgumentException;

/**
 * Small, dependency-free statistics helpers. Every method is deterministic;
 * resampling uses an explicit seed so calibrations are reproducible.
 */
final class Statistics
{
    /**
     * @param  list<float|int>  $values
     */
    public static function mean(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        return array_sum($values) / count($values);
    }

    /**
     * @param  list<float|int>  $values
     */
    public static function median(array $values): float
    {
        return self::quantile($values, 0.5);
    }

    /**
     * Linear-interpolated quantile (type 7, as R and NumPy default).
     *
     * @param  list<float|int>  $values
     */
    public static function quantile(array $values, float $probability): float
    {
        if ($values === []) {
            return 0.0;
        }

        if ($probability < 0 || $probability > 1) {
            throw new InvalidArgumentException('Probability must be between 0 and 1.');
        }

        $sorted = $values;
        sort($sorted);
        $count = count($sorted);

        if ($count === 1) {
            return (float) $sorted[0];
        }

        $position = ($count - 1) * $probability;
        $lower = (int) floor($position);
        $upper = (int) ceil($position);
        $weight = $position - $lower;

        return (float) ($sorted[$lower] + ($sorted[$upper] - $sorted[$lower]) * $weight);
    }

    /**
     * Median absolute deviation scaled to be consistent with the standard
     * deviation of a normal distribution.
     *
     * @param  list<float|int>  $values
     */
    public static function robustSpread(array $values): float
    {
        if (count($values) < 2) {
            return 0.0;
        }

        $median = self::median($values);
        $deviations = array_map(fn ($value) => abs($value - $median), $values);

        return 1.4826 * self::median($deviations);
    }

    /**
     * @param  list<float|int>  $values
     */
    public static function standardDeviation(array $values): float
    {
        $count = count($values);

        if ($count < 2) {
            return 0.0;
        }

        $mean = self::mean($values);
        $sumSquares = array_sum(array_map(fn ($value) => ($value - $mean) ** 2, $values));

        return sqrt($sumSquares / ($count - 1));
    }

    /**
     * Ratio of sums with a bootstrap percentile confidence interval obtained
     * by resampling paired observations.
     *
     * @param  list<float|int>  $numerators
     * @param  list<float|int>  $denominators
     * @return array{value: float, low: float, high: float, samples: int}
     */
    public static function ratioOfSums(array $numerators, array $denominators, int $resamples = 400, int $seed = 1): array
    {
        $count = count($numerators);

        if ($count === 0 || $count !== count($denominators)) {
            throw new InvalidArgumentException('Paired series must be non-empty and equal in length.');
        }

        $point = self::safeRatio(array_sum($numerators), array_sum($denominators));

        if ($count < 3 || $resamples < 1) {
            return ['value' => $point, 'low' => $point, 'high' => $point, 'samples' => $count];
        }

        mt_srand($seed);
        $ratios = [];

        for ($i = 0; $i < $resamples; $i++) {
            $sumNumerator = 0.0;
            $sumDenominator = 0.0;

            for ($j = 0; $j < $count; $j++) {
                $index = mt_rand(0, $count - 1);
                $sumNumerator += $numerators[$index];
                $sumDenominator += $denominators[$index];
            }

            $ratios[] = self::safeRatio($sumNumerator, $sumDenominator);
        }

        return [
            'value' => $point,
            'low' => self::quantile($ratios, 0.025),
            'high' => self::quantile($ratios, 0.975),
            'samples' => $count,
        ];
    }

    public static function safeRatio(float $numerator, float $denominator): float
    {
        if ($denominator <= 0) {
            return $numerator > 0 ? INF : 1.0;
        }

        return $numerator / $denominator;
    }

    /**
     * Error metrics between a predicted and an observed series.
     *
     * @param  list<float|int>  $predicted
     * @param  list<float|int>  $observed
     * @return array{n: int, bias: float, mae: float, rmse: float, r2: float, mape: float|null}
     */
    public static function errors(array $predicted, array $observed): array
    {
        $count = count($observed);

        if ($count === 0 || $count !== count($predicted)) {
            return ['n' => 0, 'bias' => 0.0, 'mae' => 0.0, 'rmse' => 0.0, 'r2' => 0.0, 'mape' => null];
        }

        $sumError = 0.0;
        $sumAbs = 0.0;
        $sumSquare = 0.0;
        $sumPct = 0.0;
        $pctCount = 0;

        for ($i = 0; $i < $count; $i++) {
            $error = $predicted[$i] - $observed[$i];
            $sumError += $error;
            $sumAbs += abs($error);
            $sumSquare += $error ** 2;

            if ($observed[$i] > 0) {
                $sumPct += abs($error) / $observed[$i];
                $pctCount++;
            }
        }

        $meanObserved = self::mean($observed);
        $totalVariance = array_sum(array_map(fn ($value) => ($value - $meanObserved) ** 2, $observed));
        $r2 = $totalVariance > 0 ? 1 - ($sumSquare / $totalVariance) : 0.0;

        return [
            'n' => $count,
            'bias' => $sumError / $count,
            'mae' => $sumAbs / $count,
            'rmse' => sqrt($sumSquare / $count),
            'r2' => round($r2, 4),
            'mape' => $pctCount > 0 ? $sumPct / $pctCount : null,
        ];
    }

    /**
     * Pearson correlation coefficient.
     *
     * @param  list<float|int>  $x
     * @param  list<float|int>  $y
     */
    public static function correlation(array $x, array $y): float
    {
        $count = count($x);

        if ($count < 2 || $count !== count($y)) {
            return 0.0;
        }

        $meanX = self::mean($x);
        $meanY = self::mean($y);
        $covariance = 0.0;
        $varianceX = 0.0;
        $varianceY = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $covariance += ($x[$i] - $meanX) * ($y[$i] - $meanY);
            $varianceX += ($x[$i] - $meanX) ** 2;
            $varianceY += ($y[$i] - $meanY) ** 2;
        }

        if ($varianceX <= 0 || $varianceY <= 0) {
            return 0.0;
        }

        return $covariance / sqrt($varianceX * $varianceY);
    }

    /**
     * Percentile rank of a value within a reference sample (0-100).
     *
     * @param  list<float|int>  $reference
     */
    public static function percentileRank(float $value, array $reference): float
    {
        if ($reference === []) {
            return 50.0;
        }

        $below = count(array_filter($reference, fn ($item) => $item < $value));
        $equal = count(array_filter($reference, fn ($item) => $item == $value));

        return 100 * ($below + 0.5 * $equal) / count($reference);
    }
}

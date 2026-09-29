<?php

use App\Support\Stats\Statistics;

it('computes the median of odd and even length samples', function () {
    expect(Statistics::median([5, 1, 3]))->toBe(3.0)
        ->and(Statistics::median([4, 1, 3, 2]))->toBe(2.5)
        ->and(Statistics::median([]))->toBe(0.0);
});

it('interpolates quantiles like R type 7', function () {
    expect(Statistics::quantile([1, 2, 3, 4], 0.25))->toBe(1.75)
        ->and(Statistics::quantile([1, 2, 3, 4], 1.0))->toBe(4.0)
        ->and(Statistics::quantile([7], 0.9))->toBe(7.0);
});

it('rejects probabilities outside zero and one', function () {
    Statistics::quantile([1, 2], 1.5);
})->throws(InvalidArgumentException::class);

it('scales the median absolute deviation to a normal sigma', function () {
    expect(Statistics::robustSpread([10, 10, 10, 10]))->toBe(0.0)
        ->and(Statistics::robustSpread([1, 2, 3, 4, 100]))->toBe(1.4826)
        ->and(Statistics::robustSpread([3]))->toBe(0.0);
});

it('returns the ratio of sums with a bootstrap interval that brackets it', function () {
    $modis = [10, 20, 30, 40, 50, 60];
    $viirs = [45, 75, 130, 155, 200, 245];

    $ratio = Statistics::ratioOfSums($modis, $viirs, resamples: 200, seed: 7);

    expect($ratio['value'])->toBe(210 / 850)
        ->and($ratio['low'])->toBeLessThanOrEqual($ratio['value'])
        ->and($ratio['high'])->toBeGreaterThanOrEqual($ratio['value'])
        ->and($ratio['samples'])->toBe(6);
});

it('produces identical bootstrap intervals for the same seed', function () {
    $a = Statistics::ratioOfSums([1, 5, 9, 2, 8], [4, 20, 30, 10, 40], resamples: 100, seed: 42);
    $b = Statistics::ratioOfSums([1, 5, 9, 2, 8], [4, 20, 30, 10, 40], resamples: 100, seed: 42);

    expect($a)->toBe($b);
});

it('collapses the interval when fewer than three pairs exist', function () {
    $ratio = Statistics::ratioOfSums([2, 4], [8, 16]);

    expect($ratio)->toBe(['value' => 0.25, 'low' => 0.25, 'high' => 0.25, 'samples' => 2]);
});

it('rejects unpaired series', function () {
    Statistics::ratioOfSums([1, 2], [1]);
})->throws(InvalidArgumentException::class);

it('treats a zero denominator as an infinite ratio when the numerator is positive', function () {
    expect(Statistics::safeRatio(5, 0))->toBe(INF)
        ->and(Statistics::safeRatio(0, 0))->toBe(1.0);
});

it('reports bias, absolute and squared errors of a prediction', function () {
    $errors = Statistics::errors([12, 18, 30], [10, 20, 30]);

    expect($errors['n'])->toBe(3)
        ->and($errors['bias'])->toBe(0.0)
        ->and($errors['mae'])->toBe(4 / 3)
        ->and(round($errors['rmse'], 4))->toBe(round(sqrt(8 / 3), 4))
        ->and($errors['r2'])->toBe(round(1 - 8 / 200, 4))
        ->and($errors['mape'])->toBe((0.2 + 0.1 + 0) / 3);
});

it('returns empty error metrics for mismatched series', function () {
    expect(Statistics::errors([1], [1, 2]))->toBe(['n' => 0, 'bias' => 0.0, 'mae' => 0.0, 'rmse' => 0.0, 'r2' => 0.0, 'mape' => null]);
});

it('computes a perfect positive and negative correlation', function () {
    expect(round(Statistics::correlation([1, 2, 3], [2, 4, 6]), 6))->toBe(1.0)
        ->and(round(Statistics::correlation([1, 2, 3], [6, 4, 2]), 6))->toBe(-1.0)
        ->and(Statistics::correlation([1, 1, 1], [1, 2, 3]))->toBe(0.0);
});

it('ranks a value against a reference sample with ties split in half', function () {
    expect(Statistics::percentileRank(30, [10, 20, 30, 40]))->toBe(62.5)
        ->and(Statistics::percentileRank(100, [10, 20]))->toBe(100.0)
        ->and(Statistics::percentileRank(5, []))->toBe(50.0);
});

it('computes the sample standard deviation', function () {
    expect(Statistics::standardDeviation([2, 4, 4, 4, 5, 5, 7, 9]))->toBe(sqrt(32 / 7))
        ->and(Statistics::standardDeviation([1]))->toBe(0.0);
});

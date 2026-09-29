<?php

use App\Support\Stats\FrpHistogram;

it('maps FRP values to the bin whose lower edge they reach', function (float $frp, int $bin) {
    expect(FrpHistogram::binIndex($frp))->toBe($bin);
})->with([
    'zero' => [0.0, 0],
    'below first edge' => [0.4, 0],
    'exactly on an edge' => [10.0, 9],
    'just under an edge' => [9.99, 8],
    'above the last edge' => [12000.0, 25],
]);

it('counts cell-days at or above a threshold that snaps to a bin edge', function () {
    $counts = FrpHistogram::empty();
    $counts[9] = 5;
    $counts[12] = 3;
    $counts[25] = 1;

    expect(FrpHistogram::countAtOrAbove($counts, 10))->toBe(9)
        ->and(FrpHistogram::countAtOrAbove($counts, 12))->toBe(9)
        ->and(FrpHistogram::countAtOrAbove($counts, 30))->toBe(4)
        ->and(FrpHistogram::countAtOrAbove($counts, 5000))->toBe(1)
        ->and(FrpHistogram::total($counts))->toBe(9);
});

it('merges two histograms bin by bin', function () {
    $a = FrpHistogram::empty();
    $b = FrpHistogram::empty();
    $a[3] = 2;
    $b[3] = 5;
    $b[7] = 1;

    $merged = FrpHistogram::merge($a, $b);

    expect($merged[3])->toBe(7)
        ->and($merged[7])->toBe(1)
        ->and(count($merged))->toBe(count(FrpHistogram::EDGES));
});

test('the SQL bin expression matches the histogram edges', function () {
    $edges = FrpHistogram::EDGES;
    $cases = [];

    for ($i = count($edges) - 1; $i >= 1; $i--) {
        $edge = $edges[$i];
        $cases[] = sprintf('when frp >= %s then %d', rtrim(rtrim(number_format($edge, 1, '.', ''), '0'), '.'), $i);
    }

    expect(FrpHistogram::SQL_BIN_CASE)->toBe('(case '.implode(' ', $cases).' else 0 end)');
});

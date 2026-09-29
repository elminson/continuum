<?php

use App\Support\Geo\LatLonGrid;

it('assigns points in the same quarter-degree square to one cell', function () {
    $grid = new LatLonGrid(0.25);

    expect($grid->cellIdFor(10.1, 20.1))->toBe($grid->cellIdFor(10.2, 20.2))
        ->and($grid->cellIdFor(10.1, 20.1))->not->toBe($grid->cellIdFor(10.3, 20.1));
});

it('returns bounds aligned to the cell size that contain the point', function () {
    $grid = new LatLonGrid(0.25);

    $bounds = $grid->cellBounds($grid->cellIdFor(10.1, 20.1));

    expect($bounds)->toBe(['west' => 20.0, 'south' => 10.0, 'east' => 20.25, 'north' => 10.25]);
});

it('returns the centre of a cell', function () {
    $grid = new LatLonGrid(0.25);

    expect($grid->cellCenter($grid->cellIdFor(-0.1, -0.1)))->toBe(['lat' => -0.125, 'lon' => -0.125]);
});

it('rejects cell sizes outside the valid range', function (float $degrees) {
    new LatLonGrid($degrees);
})->with(['zero' => 0.0, 'over ninety' => 90.5])->throws(InvalidArgumentException::class);

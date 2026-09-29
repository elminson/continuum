<?php

use App\Support\Geo\Grid;

it('returns the same cell id for the same coordinates', function () {
    $grid = new Grid(1);

    expect($grid->cellIdFor(-29.50686, -60.74675))->toBe($grid->cellIdFor(-29.50686, -60.74675));
});

it('places nearby points inside one cell and distant points in different cells', function () {
    $grid = new Grid(1);
    $center = $grid->cellCenter($grid->cellIdFor(10.0, 20.0));

    $same = $grid->cellIdFor($center['lat'] + 0.001, $center['lon'] + 0.001);
    $other = $grid->cellIdFor($center['lat'] + 0.05, $center['lon']);

    expect($same)->toBe($grid->cellIdFor($center['lat'], $center['lon']))
        ->and($other)->not->toBe($same);
});

it('returns bounds that contain the point used to compute the cell', function (float $latitude, float $longitude) {
    $grid = new Grid(1);

    $bounds = $grid->cellBounds($grid->cellIdFor($latitude, $longitude));

    expect($latitude)->toBeGreaterThanOrEqual($bounds['south'])->toBeLessThan($bounds['north'])
        ->and($longitude)->toBeGreaterThanOrEqual($bounds['west'])->toBeLessThan($bounds['east']);
})->with([
    'equator' => [0.0, 0.0],
    'southern hemisphere' => [-29.50686, -60.74675],
    'high latitude' => [67.5, 150.25],
    'near the antimeridian' => [10.0, 179.99],
]);

it('shrinks the number of columns toward the poles', function () {
    $grid = new Grid(1);

    $equator = $grid->columnsForRow($grid->rowFor(0));
    $temperate = $grid->columnsForRow($grid->rowFor(45));
    $polar = $grid->columnsForRow($grid->rowFor(80));

    expect($equator)->toBeGreaterThan($temperate)
        ->and($temperate)->toBeGreaterThan($polar)
        ->and($equator)->toBeBetween(40000, 40100);
});

it('produces roughly one kilometre cells at the equator', function () {
    $grid = new Grid(1);
    $bounds = $grid->cellBounds($grid->cellIdFor(0.0, 0.0));

    $heightKm = ($bounds['north'] - $bounds['south']) * Grid::KM_PER_DEGREE;
    $widthKm = ($bounds['east'] - $bounds['west']) * Grid::KM_PER_DEGREE;

    expect($heightKm)->toBeBetween(0.99, 1.01)
        ->and($widthKm)->toBeBetween(0.99, 1.01);
});

it('clamps coordinates at the edges of the globe', function () {
    $grid = new Grid(1);

    expect($grid->cellIdFor(90, 180))->toBe($grid->cellIdFor(89.9999, 179.9999))
        ->and($grid->cellIdFor(-90, -180))->toBe(0);
});

it('rejects impossible cell sizes', function (float $cellKm) {
    new Grid($cellKm);
})->with(['zero' => 0.0, 'negative' => -1.0, 'too large' => 501.0])->throws(InvalidArgumentException::class);

it('reads the cell size from configuration', function () {
    config()->set('continuum.grid_km', 2.5);

    expect(Grid::fromConfig()->cellKm)->toBe(2.5)
        ->and(Grid::fromConfig()->nominalCellAreaKm2())->toBe(6.25);
});

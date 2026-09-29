<?php

use App\Support\Geo\BoundingBox;

it('parses the FIRMS west,south,east,north string', function () {
    $box = BoundingBox::fromString(' -70, -35 ,-55,-15 ');

    expect($box->toArray())->toBe([-70.0, -35.0, -55.0, -15.0])
        ->and($box->toFirmsString())->toBe('-70,-35,-55,-15');
});

it('rejects strings that are not four numbers', function (string $value) {
    BoundingBox::fromString($value);
})->with([
    'three values' => '-70,-35,-55',
    'letters' => '-70,-35,east,-15',
    'empty' => '',
])->throws(InvalidArgumentException::class, 'Expected four numeric values');

it('rejects boxes whose west is not smaller than east', function () {
    new BoundingBox(-55, -35, -70, -15);
})->throws(InvalidArgumentException::class, 'west < east');

it('rejects boxes outside the coordinate range', function () {
    new BoundingBox(-181, -35, -55, -15);
})->throws(InvalidArgumentException::class, 'valid coordinate range');

it('tells whether a point is inside, on the edge, or outside', function () {
    $box = new BoundingBox(-70, -35, -55, -15);

    expect($box->contains(-25, -60))->toBeTrue()
        ->and($box->contains(-35, -70))->toBeTrue()
        ->and($box->contains(-14.9, -60))->toBeFalse()
        ->and($box->contains(-25, -54.9))->toBeFalse();
});

it('estimates a plausible area for a known box', function () {
    $box = new BoundingBox(0, 0, 1, 1);

    expect($box->areaKm2())->toBeBetween(12300, 12400);
});

it('computes the centre of the box', function () {
    expect((new BoundingBox(-70, -35, -55, -15))->center())->toBe(['lat' => -25.0, 'lon' => -62.5]);
});

it('knows when it contains another box and can pad itself within valid bounds', function () {
    $box = BoundingBox::fromString('-71,17,-67,20');

    expect($box->containsBox(BoundingBox::fromString('-70,18,-68,19')))->toBeTrue()
        ->and($box->containsBox(BoundingBox::fromString('-72,18,-68,19')))->toBeFalse()
        ->and($box->padded(2)->toArray())->toBe([-73.0, 15.0, -65.0, 22.0])
        ->and(BoundingBox::fromString('-179,-89,179,89')->padded(5)->toArray())->toBe([-180.0, -90.0, 180.0, 90.0])
        ->and($box->padded(0))->toBe($box);
});

<?php

use App\Models\Region;
use App\Models\User;
use App\Policies\RegionPolicy;

test('anyone can view a preset region and only the owner can view a private one', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $preset = Region::factory()->preset()->create();
    $private = Region::factory()->ownedBy($owner)->create();
    $policy = new RegionPolicy;

    expect($policy->viewAny(null))->toBeTrue()
        ->and($policy->view(null, $preset))->toBeTrue()
        ->and($policy->view(null, $private))->toBeFalse()
        ->and($policy->view($owner, $private))->toBeTrue()
        ->and($policy->view($other, $private))->toBeFalse()
        ->and($policy->view($other, $preset))->toBeTrue();
});

test('any authenticated user can create a region', function () {
    expect((new RegionPolicy)->create(User::factory()->create()))->toBeTrue();
});

test('only the owner can update or delete a region and presets are locked', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $preset = Region::factory()->preset()->create();
    $private = Region::factory()->ownedBy($owner)->create();
    $policy = new RegionPolicy;

    expect($policy->update($owner, $private))->toBeTrue()
        ->and($policy->delete($owner, $private))->toBeTrue()
        ->and($policy->update($other, $private))->toBeFalse()
        ->and($policy->delete($other, $private))->toBeFalse()
        ->and($policy->update($owner, $preset))->toBeFalse()
        ->and($policy->delete($owner, $preset))->toBeFalse();
});

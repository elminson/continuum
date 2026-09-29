<?php

use App\Models\Region;
use App\Models\User;

it('refuses a private area to anonymous API clients', function () {
    $region = Region::factory()->ownedBy(User::factory()->create())->create();

    $this->getJson(route('api.v1.regions.export', $region))->assertForbidden();
    $this->getJson(route('api.v1.regions.calendar', $region))->assertForbidden();
});

it('serves a private area through the API to its owner\'s browser session', function () {
    $owner = User::factory()->create(['password' => bcrypt('secret-password')]);
    $region = Region::factory()->ownedBy($owner)->create();

    $this->post('/login', ['email' => $owner->email, 'password' => 'secret-password'])->assertRedirect();

    $this->get(route('api.v1.regions.export', [$region, 'period' => 'month']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $this->getJson(route('api.v1.regions.calendar', [$region, 'period' => 'month']))
        ->assertOk()
        ->assertJsonPath('meta.region', $region->slug);
});

it('still refuses a private area to a different signed-in user', function () {
    $region = Region::factory()->ownedBy(User::factory()->create())->create();
    $other = User::factory()->create(['password' => bcrypt('secret-password')]);

    $this->post('/login', ['email' => $other->email, 'password' => 'secret-password']);

    $this->getJson(route('api.v1.regions.calendar', $region))->assertForbidden();
});

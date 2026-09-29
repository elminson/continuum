<?php

use App\Models\Region;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the API documentation with the first preset as the example', function () {
    Region::factory()->preset()->withBox([-70, -35, -55, -15])->create(['slug' => 'gran-chaco']);

    $this->get(route('api-docs'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('ApiDocs')
            ->where('exampleSlug', 'gran-chaco')
            ->where('exampleBbox', '-70,-35,-55,-15')
            ->where('baseUrl', rtrim(config('app.url'), '/').'/api/v1'));
});

it('falls back to a default example when no preset exists', function () {
    $this->get(route('api-docs'))
        ->assertInertia(fn (Assert $page) => $page->where('exampleSlug', 'gran-chaco'));
});

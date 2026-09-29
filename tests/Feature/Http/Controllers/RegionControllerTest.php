<?php

use App\Jobs\HarmonizeRegionJob;
use App\Models\Region;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

describe('index', function () {
    it('lists preset regions to guests without exposing private ones', function () {
        Region::factory()->preset()->create(['name' => 'Borneo']);
        Region::factory()->ownedBy(User::factory()->create())->create(['name' => 'Hidden']);

        $this->get(route('regions.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('regions/Index')
                ->has('regions', 1)
                ->where('regions.0.name', 'Borneo')
                ->where('canCreate', false));
    });
});

describe('create', function () {
    it('redirects guests to the login page', function () {
        $this->get(route('regions.create'))->assertRedirect(route('login'));
    });

    it('renders the empty form for a signed-in user', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('regions.create'))
            ->assertInertia(fn (Assert $page) => $page->component('regions/Form')->where('region', null));
    });
});

describe('store', function () {
    it('redirects guests to the login page without creating anything', function () {
        $this->post(route('regions.store'), ['name' => 'X', 'west' => 0, 'south' => 0, 'east' => 1, 'north' => 1])
            ->assertRedirect(route('login'));

        expect(Region::query()->count())->toBe(0);
    });

    it('creates the region for its owner and queues harmonization', function () {
        Queue::fake([HarmonizeRegionJob::class]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('regions.store'), [
            'name' => 'Pantanal test',
            'description' => 'Wetland fires',
            'west' => -59.5,
            'south' => -21.0,
            'east' => -55.0,
            'north' => -16.0,
            'color' => '#22c55e',
        ]);

        $region = Region::query()->where('slug', 'pantanal-test')->sole();

        $response->assertRedirect(route('regions.show', $region))->assertSessionHas('success');

        expect($region->user_id)->toBe($user->id)
            ->and($region->is_preset)->toBeFalse()
            ->and($region->bbox()->toArray())->toBe([-59.5, -21.0, -55.0, -16.0]);

        Queue::assertPushed(HarmonizeRegionJob::class, fn (HarmonizeRegionJob $job) => $job->regionId === $region->id);
    });

    it('rejects a box whose east edge is not beyond its west edge', function () {
        Queue::fake([HarmonizeRegionJob::class]);

        $this->actingAs(User::factory()->create())
            ->from(route('regions.create'))
            ->post(route('regions.store'), ['name' => 'Backwards', 'west' => 10, 'south' => 0, 'east' => 5, 'north' => 1])
            ->assertRedirect(route('regions.create'))
            ->assertSessionHasErrors(['east' => 'East must be greater than west (areas crossing the antimeridian are not supported).']);

        expect(Region::query()->count())->toBe(0);
        Queue::assertNothingPushed();
    });

    it('requires the name and every edge of the box', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('regions.store'), [])
            ->assertSessionHasErrors(['name', 'west', 'south', 'east', 'north']);
    });
});

describe('edit and update', function () {
    it('forbids editing a region owned by someone else', function () {
        $region = Region::factory()->ownedBy(User::factory()->create())->create();

        $this->actingAs(User::factory()->create())
            ->get(route('regions.edit', $region))
            ->assertForbidden();
    });

    it('renders the form with the region for its owner', function () {
        $owner = User::factory()->create();
        $region = Region::factory()->ownedBy($owner)->create(['name' => 'Mine']);

        $this->actingAs($owner)
            ->get(route('regions.edit', $region))
            ->assertInertia(fn (Assert $page) => $page->component('regions/Form')->where('region.name', 'Mine'));
    });

    it('re-harmonizes only when the box changes', function () {
        Queue::fake([HarmonizeRegionJob::class]);
        $owner = User::factory()->create();
        $region = Region::factory()->ownedBy($owner)->withBox([0, 0, 5, 5])->create();

        $this->actingAs($owner)
            ->put(route('regions.update', $region), ['name' => 'Renamed', 'west' => 0, 'south' => 0, 'east' => 5, 'north' => 5])
            ->assertRedirect(route('regions.show', $region));

        expect($region->fresh()->name)->toBe('Renamed');
        Queue::assertNotPushed(HarmonizeRegionJob::class);

        $this->actingAs($owner)
            ->put(route('regions.update', $region), ['name' => 'Renamed', 'west' => 0, 'south' => 0, 'east' => 6, 'north' => 5]);

        expect($region->fresh()->east)->toBe(6.0);
        Queue::assertPushed(HarmonizeRegionJob::class, 1);
    });

    it('forbids updating a preset region', function () {
        $region = Region::factory()->preset()->create(['name' => 'Preset']);

        $this->actingAs(User::factory()->create())
            ->put(route('regions.update', $region), ['name' => 'Hijacked', 'west' => 0, 'south' => 0, 'east' => 5, 'north' => 5])
            ->assertForbidden();

        expect($region->fresh()->name)->toBe('Preset');
    });
});

describe('destroy', function () {
    it('lets the owner delete a private region', function () {
        $owner = User::factory()->create();
        $region = Region::factory()->ownedBy($owner)->create();

        $this->actingAs($owner)->delete(route('regions.destroy', $region))->assertRedirect(route('regions.index'));

        $this->assertModelMissing($region);
    });

    it('forbids other users from deleting', function () {
        $region = Region::factory()->ownedBy(User::factory()->create())->create();

        $this->actingAs(User::factory()->create())->delete(route('regions.destroy', $region))->assertForbidden();

        $this->assertModelExists($region);
    });

    it('redirects guests to the login page instead of deleting', function () {
        $region = Region::factory()->ownedBy(User::factory()->create())->create();

        $this->delete(route('regions.destroy', $region))->assertRedirect(route('login'));

        $this->assertModelExists($region);
    });
});

describe('harmonize', function () {
    it('queues a harmonization run for a visible region', function () {
        Queue::fake([HarmonizeRegionJob::class]);
        $region = Region::factory()->preset()->create();

        $this->actingAs(User::factory()->create())
            ->from(route('regions.show', $region))
            ->post(route('regions.harmonize', $region))
            ->assertRedirect(route('regions.show', $region))
            ->assertSessionHas('success');

        Queue::assertPushed(HarmonizeRegionJob::class, fn (HarmonizeRegionJob $job) => $job->regionId === $region->id);
    });
});

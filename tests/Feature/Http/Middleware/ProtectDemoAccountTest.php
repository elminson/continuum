<?php

use App\Models\Region;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config()->set('continuum.demo.email', 'judge@example.test');
    config()->set('continuum.demo.max_areas', 2);
    config()->set('continuum.firms.map_key', null);
    $this->demo = User::factory()->create(['email' => 'judge@example.test', 'name' => 'Judge']);
});

it('blocks password changes and account deletion for the demo account', function () {
    $this->actingAs($this->demo)
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertRedirect();

    $this->actingAs($this->demo)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect();

    expect(Hash::check('password', $this->demo->fresh()->password))->toBeTrue()
        ->and($this->demo->fresh())->not->toBeNull();
});

it('lets the demo account change its language but not its name or email', function () {
    $this->actingAs($this->demo)
        ->patch(route('profile.update'), ['name' => 'Hacked', 'email' => 'other@example.test', 'locale' => 'es'])
        ->assertSessionHasNoErrors();

    $demo = $this->demo->fresh();

    expect($demo->name)->toBe('Judge')
        ->and($demo->email)->toBe('judge@example.test')
        ->and($demo->locale)->toBe('es');
});

it('caps how many areas the demo account keeps and how big they are', function () {
    Bus::fake();
    $box = ['west' => -71, 'south' => 18, 'east' => -69, 'north' => 19.5];

    $this->actingAs($this->demo)->post(route('regions.store'), ['name' => 'One', ...$box])->assertSessionHasNoErrors();
    $this->actingAs($this->demo)->post(route('regions.store'), ['name' => 'Two', ...$box])->assertSessionHasNoErrors();
    $this->actingAs($this->demo)->post(route('regions.store'), ['name' => 'Three', ...$box])->assertSessionHasErrors('name');

    Region::query()->where('name', 'Two')->delete();

    $this->actingAs($this->demo)
        ->post(route('regions.store'), ['name' => 'Huge', 'west' => -80, 'south' => -30, 'east' => -50, 'north' => 0])
        ->assertSessionHasErrors('east');

    expect(Region::query()->where('user_id', $this->demo->id)->count())->toBe(1);
});

it('leaves other accounts alone', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'New name', 'email' => $user->email])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe('New name');
});

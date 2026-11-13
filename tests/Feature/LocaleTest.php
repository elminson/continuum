<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('follows the browser language for guests', function () {
    $this->withHeader('Accept-Language', 'es-DO,es;q=0.9,en;q=0.8')
        ->get(route('method'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'es'));

    $this->withHeader('Accept-Language', 'fr-FR')
        ->get(route('method'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

it('lets a user choose a language that beats the browser', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'locale' => 'es'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->locale)->toBe('es');

    $this->actingAs($user)
        ->withHeader('Accept-Language', 'en-US')
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'es')->where('auth.user.locale', 'es'));
});

it('goes back to the browser language when the choice is cleared', function () {
    $user = User::factory()->create(['locale' => 'es']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'locale' => ''])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->locale)->toBeNull();
});

it('rejects a language the app does not speak', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'locale' => 'fr'])
        ->assertSessionHasErrors('locale');
});

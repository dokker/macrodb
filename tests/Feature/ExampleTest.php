<?php

use App\Models\User;

it('sends guests to the login page', function () {
    $this->get('/')->assertRedirect('/login');
});

it('serves the PWA shell to the signed-in user', function () {
    $this->withoutVite()->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertSee('rel="manifest"', false)
        ->assertSee('id="view"', false);
});

it('logs the user out', function () {
    $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
});

it('issues the API cookie to a signed-in web user so the PWA can call the API', function () {
    $this->withoutVite()->actingAs(User::factory()->create())
        ->get('/')
        ->assertCookie('laravel_token');
});

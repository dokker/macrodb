<?php

use App\Models\User;

it('rejects unauthenticated REST and MCP requests', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/foods?q=zab'],
    ['POST', '/api/meals'],
    ['POST', '/api/meals/photo'],
    ['GET', '/api/summary/daily?date=2026-09-28'],
    ['POST', '/mcp'],
]);

it('accepts a personal API token issued by api:token', function () {
    User::factory()->create();
    $this->artisan('passport:client', ['--personal' => true, '--name' => 'Personal', '--provider' => 'users', '--no-interaction' => true])->assertSuccessful();

    $this->artisan('api:token')->assertSuccessful();

    $token = $this->app->make(User::class)->first()->createToken('rest')->accessToken;

    $this->withToken($token)->getJson('/api/foods?q=zab')->assertOk();
});

it('fails to issue a token without a user', function () {
    $this->artisan('api:token')->assertFailed();
});

it('logs the account in through the login form', function () {
    $user = User::factory()->create(['password' => 'secret-pass']);

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass'])->assertRedirect('/');
    $this->assertAuthenticatedAs($user);
});

it('advertises the MCP OAuth metadata', function () {
    $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertOk();
});

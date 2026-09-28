<?php

use App\Models\DailyTarget;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(fn () => Passport::actingAs(User::factory()->create()));

it('creates a target, replaces it for the same date and lists the history with the current one', function () {
    $payload = ['valid_from' => '2026-01-01', 'kcal' => 2000, 'protein' => 120, 'carbs' => 220, 'fat' => 65];

    $this->postJson('/api/targets', $payload)->assertCreated()->assertJsonPath('data.kcal', 2000);
    $this->postJson('/api/targets', [...$payload, 'kcal' => 1900])->assertOk();
    $this->postJson('/api/targets', [...$payload, 'valid_from' => '2999-01-01', 'kcal' => 1500])->assertCreated();

    expect(DailyTarget::count())->toBe(2);

    $this->getJson('/api/targets')
        ->assertOk()
        ->assertJsonPath('data.current.kcal', 1900)
        ->assertJsonPath('data.history.0.valid_from', '2999-01-01')
        ->assertJsonCount(2, 'data.history');
});

it('has no current target before any is set', function () {
    $this->getJson('/api/targets')->assertOk()->assertJsonPath('data.current', null);
});

it('validates a target', function (array $override) {
    $this->postJson('/api/targets', [...['valid_from' => '2026-01-01', 'kcal' => 2000, 'protein' => 1, 'carbs' => 1, 'fat' => 1], ...$override])
        ->assertUnprocessable();
})->with([
    'bad date' => [['valid_from' => 'tomorrow']],
    'tiny kcal' => [['kcal' => 5]],
    'negative protein' => [['protein' => -1]],
]);

it('deletes a target', function () {
    $target = DailyTarget::create(['valid_from' => '2026-01-01', 'kcal' => 2000, 'protein' => 1, 'carbs' => 1, 'fat' => 1]);

    $this->deleteJson("/api/targets/{$target->id}")->assertNoContent();

    expect(DailyTarget::count())->toBe(0);
});

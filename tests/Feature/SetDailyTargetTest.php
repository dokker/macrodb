<?php

use App\Models\DailyTarget;

it('sets and replaces the target for a date', function () {
    $this->artisan('targets:set 2000 120 220 70 --from=2026-09-01')->assertSuccessful();
    $this->artisan('targets:set 1900 130 200 60 --from=2026-09-01')->assertSuccessful();

    expect(DailyTarget::count())->toBe(1)
        ->and(DailyTarget::first()->kcal)->toBe(1900);
});

it('rejects a malformed date', function () {
    $this->artisan('targets:set 2000 120 220 70 --from=tomorrow')->assertFailed();

    expect(DailyTarget::count())->toBe(0);
});

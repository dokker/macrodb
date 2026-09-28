<?php

use App\Support\Nutrients;

it('scales and sums micronutrients', function () {
    $a = new Nutrients(100, 1, 1, 1, micros: ['sodium_mg' => 200.0, 'iron_mg' => 1.0]);

    $sum = $a->scale(2)->plus($a);

    expect($sum->micros['sodium_mg'])->toBe(600.0)
        ->and($sum->micros['iron_mg'])->toBe(3.0);
});

it('makes a micronutrient unknown when any source lacks it', function () {
    $known = new Nutrients(100, 1, 1, 1, micros: ['sodium_mg' => 200.0, 'iron_mg' => 1.0]);
    $partial = new Nutrients(100, 1, 1, 1, micros: ['sodium_mg' => 50.0]);

    $sum = Nutrients::zero()->plus($known)->plus($partial)->toArray();

    expect($sum['micros']['sodium_mg'])->toBe(250.0)
        ->and($sum['micros']['iron_mg'])->toBeNull()
        ->and($sum['micros']['calcium_mg'])->toBeNull();
});

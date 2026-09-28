<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Feature', 'Unit');

pest()->use(RefreshDatabase::class)
    ->in('Feature');

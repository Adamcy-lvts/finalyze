<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Pest Test Configuration
|--------------------------------------------------------------------------
|
| This is the base configuration for Pest tests. It sets up the base test case
| and adds useful testing helpers and traits.
|
*/

pest()->extend(TestCase::class)->in('Feature', 'Unit');

pest()->use(RefreshDatabase::class)->in('Feature');

beforeEach(function () {
    // Seed reference data (universities/faculties/departments, categories, etc.) for feature tests
    if (method_exists($this, 'seed')) {
        $this->seed();
    }
});

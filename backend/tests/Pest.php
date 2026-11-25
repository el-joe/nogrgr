<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettings;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run against the central DB (RefreshDatabase, transaction per
| test) and may provision tenants with InteractsWithTenancy. The suite is
| parallel-safe: run it with `php artisan test --parallel`.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class, InteractsWithTenancy::class, InteractsWithSettings::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit/Shared');

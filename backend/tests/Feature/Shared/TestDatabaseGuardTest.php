<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabaseGuard;

it('accepts only test-marked database names', function (?string $name, bool $safe): void {
    expect(TestDatabaseGuard::isSafeName($name))->toBe($safe);
})->with([
    'dev database' => ['nogrgr', false],
    'real tenant database' => ['nogrgr_tenant_1', false],
    'empty' => ['', false],
    'null' => [null, false],
    'serial test db' => ['nogrgr_testing', true],
    'parallel test db' => ['nogrgr_testing_test_3', true],
    'serial tenant db' => ['nogrgr_tenant_7_testing', true],
    'parallel tenant db' => ['nogrgr_tenant_7_test_2', true],
    'sqlite memory' => [':memory:', true],
    'sqlite tenant file' => ['/srv/app/database/nogrgr_tenant_7_testing.sqlite', true],
    'sqlite real file' => ['/srv/app/database/database.sqlite', false],
]);

it('runs against a test-marked central database', function (): void {
    expect(TestDatabaseGuard::isSafeName(DB::connection()->getDatabaseName()))->toBeTrue()
        ->and(DB::connection()->getDatabaseName())->not->toBe('nogrgr');
});

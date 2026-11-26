<?php

use App\Shared\Tenancy\TenantDatabaseName;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Schema;

it('registers every module listed in config/modules.php', function (): void {
    foreach (config('modules.modules') as $provider) {
        expect(app()->getProvider($provider))->not->toBeNull();
    }

    expect(config('modules.modules'))->toHaveCount(19);
});

it('registers module view and translation namespaces', function (): void {
    expect(view()->exists('platform::admin.dashboard'))->toBeTrue()
        ->and(__('platform::panel.dashboard'))->toBe('Dashboard');
});

it('keeps central and tenant migrations apart', function (): void {
    expect(Schema::hasTable('admins'))->toBeTrue()
        ->and(Schema::hasTable('vendor_staff'))->toBeFalse()
        ->and(config('tenancy.migration_parameters.--path'))
        ->toContain(app_path('Modules/Identity/Database/migrations/tenant'));

    $tenant = $this->createTenant('acme');
    $this->initializeTenancy($tenant);

    expect(Schema::hasTable('vendor_staff'))->toBeTrue()
        ->and(Schema::hasTable('customers'))->toBeTrue()
        ->and(Schema::hasTable('permissions'))->toBeTrue()
        ->and(Schema::hasTable('admins'))->toBeFalse();

    $this->endTenancy();
});

it('names tenant databases nogrgr_tenant_{id} with a per-process test suffix', function (): void {
    $name = TenantDatabaseName::for(42);

    expect($name)->toStartWith('nogrgr_tenant_42');

    if ($token = ParallelTesting::token()) {
        expect($name)->toBe('nogrgr_tenant_42_test_'.$token);
    } else {
        $suffix = (string) Env::get('TEST_DB_SUFFIX', '');
        expect($name)->toBe('nogrgr_tenant_42'.($suffix !== '' ? '_'.$suffix : '').'_testing');
    }
});

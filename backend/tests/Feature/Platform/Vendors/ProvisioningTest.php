<?php

use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Support\PermissionCatalog;
use App\Modules\Localization\Models\TenantLanguage;
use App\Modules\Platform\DTOs\ProvisionTenantData;
use App\Modules\Platform\Enums\DomainRequestStatus;
use App\Modules\Platform\Enums\PlanAssignment;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Events\TenantProvisioned;
use App\Modules\Platform\Events\TenantProvisioningFailed;
use App\Modules\Platform\Exceptions\PlatformRuleException;
use App\Modules\Platform\Jobs\ProvisionTenant;
use App\Modules\Platform\Models\DomainRequest;
use App\Modules\Platform\Services\TenantProvisioningService;
use App\Modules\Platform\Support\PartialMigrationCleaner;
use App\Modules\Platform\Support\TenantDatabaseProvisioner;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

require_once __DIR__.'/VendorHelpers.php';

/*
| TenantProvisioningService: tenant + sub-domain + custom-domain request, queued run
| (DB create/migrate/seed, ar/en, owner, plan), idempotency, failure and retry.
*/

function provisionData(array $overrides = []): ProvisionTenantData
{
    return ProvisionTenantData::fromArray(array_merge([
        'store_name' => 'Desert Rose Perfumes',
        'slug' => 'desertrose',
        'owner_name' => 'Reem Al-Qahtani',
        'owner_email' => 'Reem@DesertRose.sa',
        'owner_phone' => '+966552201144',
        'owner_password' => 'secret-pass-123',
        'custom_domain' => 'desertrose.sa',
        'profit_percent_bps' => 6500,
    ], $overrides));
}

it('creates the tenant, sub-domain and custom-domain request, then queues provisioning', function (): void {
    Queue::fake();

    $tenant = app(TenantProvisioningService::class)->provision(provisionData());

    expect($tenant->status)->toBe(TenantStatus::Pending)
        ->and($tenant->slug)->toBe('desertrose')
        ->and($tenant->owner_email)->toBe('reem@desertrose.sa')
        ->and($tenant->profit_percent_bps)->toBe(6500)
        ->and($tenant->domains()->pluck('domain')->all())->toBe(['desertrose'])
        ->and(DomainRequest::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->value('host'))->toBe('desertrose.sa');

    Queue::assertPushedOn(TenantProvisioningService::QUEUE, ProvisionTenant::class, fn (ProvisionTenant $job): bool => $job->tenantId === $tenant->id);
});

it('refuses a taken slug or custom domain', function (): void {
    Queue::fake();
    $service = app(TenantProvisioningService::class);
    $service->provision(provisionData());

    expect(fn () => $service->provision(provisionData(['custom_domain' => null])))->toThrow(PlatformRuleException::class)
        ->and(fn () => $service->provision(provisionData(['slug' => 'other-store'])))->toThrow(PlatformRuleException::class);
});

it('provisions the store database, languages, owner and trial plan', function (): void {
    cleanUpProvisionedTenants();
    Event::fake([TenantProvisioned::class]);
    $plan = Plan::factory()->create(['trial_days' => 14]);

    $tenant = app(TenantProvisioningService::class)->provision(provisionData([
        'plan_id' => $plan->id,
        'term' => BillingTerm::Monthly,
        'plan_assignment' => PlanAssignment::Trial,
    ]));

    $tenant->refresh();

    expect($tenant->status)->toBe(TenantStatus::Active)
        ->and($tenant->provisioned_at)->not->toBeNull()
        ->and($tenant->provisioning_attempts)->toBe(1)
        ->and($tenant->getAttribute(TenantProvisioningService::PAYLOAD))->not->toHaveKey('owner_password_hash');

    $tenant->run(function (): void {
        $owner = VendorStaff::query()->where('email', 'reem@desertrose.sa')->firstOrFail();

        expect($owner->is_owner)->toBeTrue()
            ->and($owner->hasRole('owner'))->toBeTrue()
            ->and($owner->getAllPermissions()->pluck('name')->sort()->values()->all())
            ->toBe(collect(PermissionCatalog::tenant()['vendor'])->sort()->values()->all())
            ->and(password_verify('secret-pass-123', (string) $owner->password))->toBeTrue()
            ->and(TenantLanguage::query()->where('is_enabled', true)->pluck('language_code')->sort()->values()->all())->toBe(['ar', 'en']);
    });

    expect(Subscription::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->value('status'))->toBe(SubscriptionStatus::Trial);
    Event::assertDispatched(TenantProvisioned::class);

    // Idempotent: running again on an active store changes nothing.
    app(TenantProvisioningService::class)->run($tenant);
    expect(Subscription::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(1);
});

it('records a failure and lets an admin retry it', function (): void {
    Queue::fake();
    Event::fake([TenantProvisioningFailed::class]);
    $tenant = app(TenantProvisioningService::class)->provision(provisionData(['custom_domain' => null]));

    app()->instance(TenantDatabaseProvisioner::class, new class(app(PartialMigrationCleaner::class)) extends TenantDatabaseProvisioner
    {
        public function ensure($tenant): void
        {
            throw new RuntimeException('Database server unavailable');
        }
    });

    app(TenantProvisioningService::class)->run($tenant);

    expect($tenant->refresh()->status)->toBe(TenantStatus::Failed)
        ->and($tenant->provisioning_error)->toBe('Database server unavailable');
    Event::assertDispatched(TenantProvisioningFailed::class);

    app(TenantProvisioningService::class)->retry($tenant);
    Queue::assertPushed(ProvisionTenant::class, 2);

    $tenant->update(['status' => TenantStatus::Active]);
    expect(fn () => app(TenantProvisioningService::class)->retry($tenant))->toThrow(PlatformRuleException::class);
});

it('never resolves a pending custom domain', function (): void {
    Queue::fake();
    $tenant = app(TenantProvisioningService::class)->provision(provisionData());

    expect(DomainRequest::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->value('status'))->toBe(DomainRequestStatus::Pending)
        ->and($tenant->domains()->where('domain', 'desertrose.sa')->exists())->toBeFalse();
});

it('resumes a run that crashed in the middle of a migration (QA-P2-006)', function (): void {
    cleanUpProvisionedTenants();
    Event::fake([TenantProvisioned::class, TenantProvisioningFailed::class]);
    config([
        'tenancy.migration_parameters.--path' => [...(array) config('tenancy.migration_parameters.--path'), base_path('tests/Fixtures/tenant-migrations')],
        'testing.flaky_tenant_migration' => true,
    ]);
    $service = app(TenantProvisioningService::class);

    $tenant = $service->provision(provisionData(['custom_domain' => null]))->refresh();

    expect($tenant->status)->toBe(TenantStatus::Failed)
        ->and($tenant->provisioning_error)->toContain('Simulated crash');
    Event::assertDispatched(TenantProvisioningFailed::class);

    // Earlier migrations stay recorded; the half-run one left nothing behind.
    $tenant->run(function (): void {
        expect(Schema::hasTable('permissions'))->toBeTrue()
            ->and(Schema::hasTable('flaky_provisioning_a'))->toBeFalse()
            ->and(Schema::hasTable('flaky_provisioning_b'))->toBeFalse();
    });

    config(['testing.flaky_tenant_migration' => false]);
    $service->run($tenant);
    $tenant->refresh();

    expect($tenant->status)->toBe(TenantStatus::Active)
        ->and($tenant->provisioning_attempts)->toBe(2)
        ->and($tenant->provisioning_error)->toBeNull();
    Event::assertDispatched(TenantProvisioned::class);

    $tenant->run(function (): void {
        expect(Schema::hasTable('flaky_provisioning_b'))->toBeTrue()
            ->and(Permission::query()->where('guard_name', 'vendor')->count())->toBe(count(PermissionCatalog::tenant()['vendor']))
            ->and(VendorStaff::query()->where('email', 'reem@desertrose.sa')->count())->toBe(1);
    });

    // Seeders are idempotent: a full re-run of the database step adds no duplicates.
    app(TenantDatabaseProvisioner::class)->ensure($tenant);
    $tenant->run(function (): void {
        expect(Permission::query()->where('guard_name', 'vendor')->count())->toBe(count(PermissionCatalog::tenant()['vendor']));
    });
});

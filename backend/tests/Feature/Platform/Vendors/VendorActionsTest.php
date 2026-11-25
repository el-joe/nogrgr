<?php

use App\Modules\Platform\DTOs\ProvisionTenantData;
use App\Modules\Platform\Enums\AuditAction;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Jobs\ProvisionTenant;
use App\Modules\Platform\Models\AuditEvent;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\TenantProvisioningService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Stancl\Tenancy\Events\TenantDeleted;

require_once __DIR__.'/VendorHelpers.php';

/*
| Store lifecycle (activate, suspend, retry, delete) and audited impersonation.
*/

it('protects the lifecycle and impersonation endpoints', function (): void {
    $tenant = vendorsTenant();
    $base = "/admin/vendors/{$tenant->id}";

    $this->postJson($this->centralUrl("{$base}/suspend"))->assertUnauthorized();

    $this->actingAs(vendorsAdmin(['vendors.view']), 'admin');
    $this->postJson($this->centralUrl("{$base}/activate"))->assertForbidden();
    $this->postJson($this->centralUrl("{$base}/suspend"))->assertForbidden();
    $this->postJson($this->centralUrl("{$base}/retry"))->assertForbidden();
    $this->deleteJson($this->centralUrl($base))->assertForbidden();
    $this->postJson($this->centralUrl("{$base}/impersonate"), ['reason' => 'onboarding'])->assertForbidden();

    $this->actingAs(vendorsAdmin(['vendors.view', 'vendors.manage']), 'admin');
    $this->deleteJson($this->centralUrl($base))->assertForbidden();
    $this->postJson($this->centralUrl("{$base}/impersonate"), ['reason' => 'onboarding'])->assertForbidden();
});

it('redirects guests on the HTML pages to the admin login', function (): void {
    $tenant = vendorsTenant();

    $this->post($this->centralUrl("/admin/vendors/{$tenant->id}/suspend"))->assertRedirect($this->centralUrl('/admin/login'));
});

it('suspends and re-activates a store', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $tenant = vendorsTenant();

    $this->postJson($this->centralUrl("/admin/vendors/{$tenant->id}/suspend"))->assertOk();
    expect($tenant->refresh()->status)->toBe(TenantStatus::Suspended)
        ->and($tenant->suspended_at)->not->toBeNull();

    $this->postJson($this->centralUrl("/admin/vendors/{$tenant->id}/suspend"))
        ->assertUnprocessable()
        ->assertJsonPath('success', false);

    $this->postJson($this->centralUrl("/admin/vendors/{$tenant->id}/activate"))->assertOk();
    expect($tenant->refresh()->status)->toBe(TenantStatus::Active)
        ->and($tenant->suspended_at)->toBeNull();
});

it('refuses to activate or suspend a store that is not provisioned', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $tenant = vendorsTenant(['status' => TenantStatus::Pending]);

    $this->postJson($this->centralUrl("/admin/vendors/{$tenant->id}/activate"))->assertUnprocessable();
    $this->postJson($this->centralUrl("/admin/vendors/{$tenant->id}/suspend"))->assertUnprocessable();
    expect($tenant->refresh()->status)->toBe(TenantStatus::Pending);
});

it('retries provisioning only for pending or failed stores', function (): void {
    Queue::fake();
    $this->actingAs(vendorsAdmin(), 'admin');
    $failed = vendorsTenant(['status' => TenantStatus::Failed]);
    $active = vendorsTenant();

    $this->postJson($this->centralUrl("/admin/vendors/{$failed->id}/retry"))->assertOk();
    Queue::assertPushedOn(TenantProvisioningService::QUEUE, ProvisionTenant::class, fn (ProvisionTenant $job): bool => $job->tenantId === $failed->id);

    $this->postJson($this->centralUrl("/admin/vendors/{$active->id}/retry"))
        ->assertUnprocessable()
        ->assertJsonPath('message', __('platform::messages.cannot_retry'));
});

it('deletes a store and records it in the audit log', function (): void {
    Event::fake([TenantDeleted::class]);
    $this->actingAs(vendorsAdmin(['vendors.view', 'vendors.delete']), 'admin');
    $tenant = vendorsTenant(['name' => 'Gone Store']);

    $this->deleteJson($this->centralUrl("/admin/vendors/{$tenant->id}"))
        ->assertOk()
        ->assertJsonPath('redirect', route('admin.vendors.index'));

    expect(Tenant::query()->whereKey($tenant->id)->exists())->toBeFalse()
        ->and(AuditEvent::query()->where('action', AuditAction::Deleted->value)->where('subject_id', (string) $tenant->id)->exists())->toBeTrue();
    Event::assertDispatched(TenantDeleted::class);
});

it('validates the impersonation reason', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $tenant = vendorsTenant();

    $this->postJson($this->centralUrl("/admin/vendors/{$tenant->id}/impersonate"), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');

    $this->postJson($this->centralUrl("/admin/vendors/{$tenant->id}/impersonate"), ['reason' => 'other'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('details');

    $this->postJson($this->centralUrl("/admin/vendors/{$tenant->id}/impersonate"), ['reason' => 'because'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');
});

it('refuses impersonation of a store without an owner', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $tenant = vendorsTenant(['status' => TenantStatus::Pending]);

    $this->postJson($this->centralUrl("/admin/vendors/{$tenant->id}/impersonate"), ['reason' => 'onboarding'])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('platform::messages.no_owner'));
});

it('impersonates the owner of a provisioned store and audits the reason', function (): void {
    cleanUpProvisionedTenants();
    $tenant = app(TenantProvisioningService::class)->provision(ProvisionTenantData::fromArray([
        'store_name' => 'Desert Rose Perfumes',
        'slug' => 'rose-'.Str::lower(Str::random(6)),
        'owner_name' => 'Reem Al-Qahtani',
        'owner_email' => 'reem@desertrose.sa',
        'owner_password' => 'secret-pass-123',
    ]));
    expect($tenant->refresh()->status)->toBe(TenantStatus::Active);

    $this->actingAs(vendorsAdmin(), 'admin');

    $response = $this->postJson($this->centralUrl("/admin/vendors/{$tenant->id}/impersonate"), [
        'reason' => 'support_ticket',
        'details' => 'Ticket T-4410: payment option missing.',
    ])->assertOk();

    expect($response->json('redirect'))->toContain('/tenant/'.$tenant->slug);

    $audit = AuditEvent::query()->where('action', AuditAction::Impersonated->value)->where('subject_id', (string) $tenant->id)->firstOrFail();
    expect(json_encode($audit->getAttributes()))->toContain('support_ticket');
});

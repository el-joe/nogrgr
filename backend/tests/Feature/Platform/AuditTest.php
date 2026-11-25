<?php

use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Platform\Enums\AuditAction;
use App\Modules\Platform\Http\Middleware\AuditRequests;
use App\Modules\Platform\Models\AuditEvent;
use App\Modules\Platform\Services\Auditor;
use Illuminate\Support\Facades\Route;

it('records actor, guard, subject, changes, ip and user agent', function (): void {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');

    $event = app(Auditor::class)->record(AuditAction::Impersonated, $admin, ['password' => 'secret', 'reason' => 'support']);

    expect($event->fresh())
        ->guard->toBe('admin')
        ->actor_id->toBe($admin->id)
        ->actor_type->toBe($admin->getMorphClass())
        ->action->toBe('auth.impersonated')
        ->subject_id->toBe((string) $admin->id)
        ->tenant_id->toBeNull()
        ->ip_address->toBe('127.0.0.1')
        ->changes->toEqual(['password' => '[redacted]', 'reason' => 'support']);
});

it('stamps the tenant and scopes reads to it in tenant context', function (): void {
    $tenant = $this->createTenant('acme');
    app(Auditor::class)->record('central.thing');

    $this->initializeTenancy($tenant);
    $staff = VendorStaff::factory()->create();
    $this->actingAs($staff, 'vendor');
    app(Auditor::class)->record('vendor.thing');

    expect(AuditEvent::query()->pluck('action')->all())->toBe(['vendor.thing'])
        ->and(AuditEvent::query()->first()?->tenant_id)->toBe($tenant->id)
        ->and(AuditEvent::query()->first()?->guard)->toBe('vendor');

    $this->endTenancy();

    expect(AuditEvent::query()->count())->toBe(2);
});

it('records model changes through the Auditable trait without hidden attributes', function (): void {
    $admin = Admin::query()->create(['name' => 'Old', 'email' => 'a@example.test', 'password' => 'secret']);
    $admin->update(['name' => 'New']);

    $events = AuditEvent::query()->orderBy('id')->get();

    expect($events->pluck('action')->all())->toBe(['admin.created', 'admin.updated'])
        ->and($events[0]->changes['after'])->not->toHaveKey('password')
        ->and($events[1]->changes)->toEqual(['before' => ['name' => 'Old'], 'after' => ['name' => 'New']]);
});

it('records state-changing panel requests via middleware', function (): void {
    Route::post('/_audit-probe', fn () => 'ok')->middleware(['web', AuditRequests::class])->name('audit.probe');

    $this->post('/_audit-probe', ['title' => 'x', 'password' => 'y'])->assertOk();
    $this->get('/up');

    $event = AuditEvent::query()->sole();

    expect($event->action)->toBe('http.audit.probe')
        ->and($event->changes['input_keys'])->toBe(['title', 'password'])
        ->and($event->changes['status'])->toBe(200);
});

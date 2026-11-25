<?php

use App\Modules\Identity\Models\Admin;
use App\Modules\Platform\Models\AuditEvent;
use App\Modules\Platform\Models\Tenant;

require_once __DIR__.'/AdminTestHelpers.php';

/*
| Admin › Audit log (ADM-112 / 112a): list with actor / tenant / event / date
| filters, detail with the before/after diff, CSV export.
*/

function auditEvent(array $attributes = []): AuditEvent
{
    return AuditEvent::query()->create(array_replace([
        'tenant_id' => null,
        'guard' => 'admin',
        'actor_type' => (new Admin)->getMorphClass(),
        'actor_id' => null,
        'action' => 'plan.updated',
        'subject_type' => 'App\\Modules\\Billing\\Models\\Plan',
        'subject_id' => '2',
        'changes' => ['before' => ['ai_calls_limit' => 4000], 'after' => ['ai_calls_limit' => 5000]],
        'ip_address' => '37.0.0.12',
        'url' => 'https://example.test/admin/plans/2',
    ], $attributes));
}

dataset('audit endpoints', [
    'index' => ['get', '/admin/system/audit', 'audit.view'],
    'data' => ['get', '/admin/system/audit/data', 'audit.view'],
    'tenants' => ['get', '/admin/system/audit/tenants', 'audit.view'],
    'show' => ['get', '/admin/system/audit/{id}', 'audit.view'],
    'export' => ['get', '/admin/system/audit/export', 'audit.export'],
]);

it('redirects guests to the admin login', function (string $method, string $path): void {
    $event = auditEvent();

    $this->{$method}($this->centralUrl(str_replace('{id}', (string) $event->id, $path)))
        ->assertRedirect($this->centralUrl('/admin/login'));
})->with('audit endpoints');

it('forbids admins without the audit permission', function (string $method, string $path, string $permission): void {
    $event = auditEvent();
    $actor = adminWith($permission === 'audit.export' ? ['audit.view'] : []);

    $this->actingAs($actor, 'admin')
        ->{$method.'Json'}($this->centralUrl(str_replace('{id}', (string) $event->id, $path)))
        ->assertForbidden();
})->with('audit endpoints');

it('renders the audit page with filters and export', function (): void {
    $this->actingAs(superAdmin(), 'admin')
        ->get($this->centralUrl('/admin/system/audit'))
        ->assertOk()
        ->assertSee(__('identity::audit.export'))
        ->assertSee(route('admin.system.audit.tenants'), false)
        ->assertSee('id="audit-modal"', false);
});

it('filters events by actor, tenant, event and date', function (): void {
    $actor = superAdmin(['name' => 'Sara Admin']);
    $other = Admin::factory()->create(['name' => 'Lina Fares']);
    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::factory()->create(['name' => 'Megz Store']));

    auditEvent(['actor_id' => $actor->id]);
    auditEvent(['actor_id' => $other->id, 'tenant_id' => $tenant->id, 'action' => 'auth.impersonated']);
    auditEvent(['actor_id' => $other->id, 'created_at' => now()->subDays(10)]);

    $base = '/admin/system/audit/data?draw=1&start=0&length=10';
    $as = $this->actingAs($actor, 'admin');

    $as->getJson($this->centralUrl($base.'&actor='.$other->id))->assertOk()->assertJsonPath('recordsFiltered', 2);
    $as->getJson($this->centralUrl($base.'&tenant='.$tenant->id))
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.action', fn (string $html) => str_contains($html, __('identity::audit.actions.auth.impersonated')))
        ->assertJsonPath('data.0.subject', fn (string $html) => str_contains($html, 'Megz Store'))
        ->assertJsonPath('data.0.actor', fn (string $html) => str_contains($html, 'Lina Fares'));
    $as->getJson($this->centralUrl($base.'&event=plan.updated'))->assertJsonPath('recordsFiltered', 2);
    $as->getJson($this->centralUrl($base.'&date_from='.now()->subDay()->toDateString()))
        ->assertJsonPath('recordsFiltered', fn (int $count) => $count >= 2 && $count < AuditEvent::query()->count());
});

it('searches tenants for the store filter', function (): void {
    Tenant::withoutEvents(fn (): Tenant => Tenant::factory()->create(['name' => 'Megz Store']));
    Tenant::withoutEvents(fn (): Tenant => Tenant::factory()->create(['name' => 'Other Shop']));

    $this->actingAs(superAdmin(), 'admin')
        ->getJson($this->centralUrl('/admin/system/audit/tenants?q=megz'))
        ->assertOk()
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('pagination.more', false);
});

it('shows an event with its before / after diff', function (): void {
    $actor = superAdmin(['name' => 'Sara Admin']);
    $event = auditEvent(['actor_id' => $actor->id]);

    $this->actingAs($actor, 'admin')
        ->get($this->centralUrl("/admin/system/audit/{$event->id}"))
        ->assertOk()
        ->assertSee('ai_calls_limit')
        ->assertSee('4000')
        ->assertSee('5000')
        ->assertSee(__('identity::audit.before'))
        ->assertSee('Sara Admin');

    $this->actingAs($actor, 'admin')
        ->get($this->centralUrl('/admin/system/audit/999999'))
        ->assertNotFound();
});

it('exports the filtered events as CSV and audits the export', function (): void {
    $actor = superAdmin();
    auditEvent(['actor_id' => $actor->id, 'action' => 'role.updated']);
    auditEvent(['actor_id' => $actor->id, 'action' => 'plan.updated']);

    $response = $this->actingAs($actor, 'admin')
        ->get($this->centralUrl('/admin/system/audit/export?event=role.updated'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();
    $lines = array_values(array_filter(explode("\n", trim($csv))));

    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toContain('created_at')
        ->and($lines[1])->toContain('role.updated')
        ->and(AuditEvent::query()->where('action', 'audit.exported')->exists())->toBeTrue();

    $this->actingAs($actor, 'admin')
        ->getJson($this->centralUrl('/admin/system/audit/export?date_from=2026-02-10&date_to=2026-01-01'))
        ->assertUnprocessable()->assertJsonValidationErrors(['date_to']);
});

it('audits admin actions of this panel batch', function (): void {
    $actor = superAdmin();

    $this->actingAs($actor, 'admin')
        ->postJson($this->centralUrl('/admin/system/roles'), ['name' => 'Support', 'permissions' => ['notifications.view']])
        ->assertCreated();

    expect(AuditEvent::query()->where('action', 'http.admin.system.roles.store')->where('actor_id', $actor->id)->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'role.created')->exists())->toBeTrue();
});

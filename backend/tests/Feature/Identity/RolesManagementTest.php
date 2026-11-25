<?php

use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Support\PermissionMatrix;
use App\Modules\Platform\Models\AuditEvent;
use Spatie\Permission\Models\Role;

require_once __DIR__.'/AdminTestHelpers.php';

/*
| Admin › System › Roles (ADM-111 / 111a): list, matrix editor grouped by module,
| "manage implies view", locked super-admin, assigned roles cannot be deleted.
*/

dataset('role endpoints', [
    'index' => ['get', '/admin/system/roles', 'roles.view'],
    'data' => ['get', '/admin/system/roles/data', 'roles.view'],
    'create' => ['get', '/admin/system/roles/create', 'roles.manage'],
    'store' => ['post', '/admin/system/roles', 'roles.manage'],
    'edit' => ['get', '/admin/system/roles/{id}/edit', 'roles.manage'],
    'update' => ['put', '/admin/system/roles/{id}', 'roles.manage'],
    'destroy' => ['delete', '/admin/system/roles/{id}', 'roles.manage'],
]);

it('redirects guests to the admin login', function (string $method, string $path): void {
    $role = Role::findOrCreate('editor', 'admin');

    $this->{$method}($this->centralUrl(str_replace('{id}', (string) $role->id, $path)))
        ->assertRedirect($this->centralUrl('/admin/login'));
})->with('role endpoints');

it('forbids admins without the roles permission', function (string $method, string $path, string $permission): void {
    $actor = adminWith($permission === 'roles.manage' ? ['roles.view'] : []);
    $role = Role::findOrCreate('editor', 'admin');

    $this->actingAs($actor, 'admin')
        ->{$method.'Json'}($this->centralUrl(str_replace('{id}', (string) $role->id, $path)))
        ->assertForbidden();
})->with('role endpoints');

it('groups the admin catalog by module in the matrix', function (): void {
    $matrix = PermissionMatrix::for('admin');

    expect($matrix['abilities'])->toContain('view', 'manage', 'export')
        ->and($matrix['modules']['identity'])->toHaveKeys(['admins', 'roles', 'audit'])
        ->and($matrix['modules']['localization']['localization.languages'])->toBe(['view' => 'localization.languages.view', 'manage' => 'localization.languages.manage'])
        ->and($matrix['modules']['vendors'])->toHaveKey('tenants')
        ->and(PermissionMatrix::moduleOf('vendors.kyc'))->toBe('vendors')
        ->and(PermissionMatrix::moduleOf('something.unknown'))->toBe('other');
});

it('lists roles with admin and permission counts', function (): void {
    $actor = superAdmin();
    Role::findOrCreate('editor', 'admin')->syncPermissions(['dashboard.view', 'vendors.view']);

    $this->actingAs($actor, 'admin')
        ->get($this->centralUrl('/admin/system/roles'))
        ->assertOk()->assertSee(__('identity::roles.add'));

    $this->actingAs($actor, 'admin')
        ->getJson($this->centralUrl('/admin/system/roles/data?draw=1&start=0&length=10&search=edit'))
        ->assertOk()
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.permissions_count', trans_choice('identity::roles.permissions_count', 2, ['count' => 2]));

    $this->actingAs($actor, 'admin')
        ->getJson($this->centralUrl('/admin/system/roles/data?draw=1&start=0&length=10&search=super'))
        ->assertJsonPath('data.0.users_count', trans_choice('identity::roles.admins_count', 1, ['count' => 1]));
});

it('renders the matrix editor, the read-only super admin role and duplicates', function (): void {
    $actor = superAdmin();
    $editor = Role::findOrCreate('editor', 'admin');
    $editor->syncPermissions(['vendors.view']);

    $this->actingAs($actor, 'admin')->get($this->centralUrl('/admin/system/roles/create'))
        ->assertOk()->assertSee('data-role-matrix', false)->assertSee(__('identity::roles.modules.localization'));

    $this->actingAs($actor, 'admin')->get($this->centralUrl('/admin/system/roles/create?from='.$editor->id))
        ->assertOk()->assertSee(__('identity::roles.copy_name', ['name' => 'editor']));

    $super = Role::findByName('super-admin', 'admin');
    $this->actingAs($actor, 'admin')->get($this->centralUrl("/admin/system/roles/{$super->id}/edit"))
        ->assertOk()->assertSee(__('identity::roles.locked'))->assertDontSee('form="role-form"', false);
});

it('creates a role with implied view permissions and audits it', function (): void {
    $actor = superAdmin();

    $this->actingAs($actor, 'admin')
        ->postJson($this->centralUrl('/admin/system/roles'), ['name' => 'Operations', 'permissions' => ['vendors.manage', 'plans.manage', 'audit.export']])
        ->assertCreated();

    $role = Role::findByName('Operations', 'admin');

    expect($role->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['audit.export', 'plans.manage', 'plans.view', 'vendors.manage', 'vendors.view'])
        ->and(AuditEvent::query()->where('action', 'role.created')->where('subject_id', (string) $role->id)->exists())->toBeTrue();
});

it('validates role names and permission names', function (): void {
    $actor = superAdmin();

    $this->actingAs($actor, 'admin')
        ->postJson($this->centralUrl('/admin/system/roles'), ['name' => 'super-admin', 'permissions' => ['made.up']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'permissions.0']);
});

it('updates a role, accepts an empty matrix and records the diff', function (): void {
    $actor = superAdmin();
    $role = Role::findOrCreate('editor', 'admin');
    $role->syncPermissions(['vendors.view']);

    $this->actingAs($actor, 'admin')
        ->putJson($this->centralUrl("/admin/system/roles/{$role->id}"), ['name' => 'Editors', 'permissions' => ''])
        ->assertOk();

    $role->refresh();
    $event = AuditEvent::query()->where('action', 'role.updated')->sole();

    expect($role->name)->toBe('Editors')
        ->and($role->permissions)->toHaveCount(0)
        ->and($event->changes['before']['permissions'])->toBe(['vendors.view'])
        ->and($event->changes['after']['permissions'])->toBe([]);
});

it('locks the super admin role', function (): void {
    $actor = superAdmin();
    $super = Role::findByName('super-admin', 'admin');

    $this->actingAs($actor, 'admin')
        ->putJson($this->centralUrl("/admin/system/roles/{$super->id}"), ['name' => 'root', 'permissions' => []])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('identity::roles.rules.locked'));

    $this->actingAs($actor, 'admin')
        ->deleteJson($this->centralUrl("/admin/system/roles/{$super->id}"))
        ->assertUnprocessable();

    expect(Role::query()->whereKey($super->id)->exists())->toBeTrue();
});

it('refuses to delete an assigned role and deletes a free one', function (): void {
    $actor = superAdmin();
    $role = Role::findOrCreate('editor', 'admin');
    Admin::factory()->create()->assignRole('editor');

    $this->actingAs($actor, 'admin')
        ->deleteJson($this->centralUrl("/admin/system/roles/{$role->id}"))
        ->assertUnprocessable()
        ->assertJsonPath('message', trans_choice('identity::roles.rules.in_use', 1, ['count' => 1]));

    $free = Role::findOrCreate('temporary', 'admin');

    $this->actingAs($actor, 'admin')
        ->deleteJson($this->centralUrl("/admin/system/roles/{$free->id}"))
        ->assertOk();

    expect(Role::query()->whereKey($free->id)->exists())->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'role.deleted')->exists())->toBeTrue();
});

it('applies manage-implies-view to admins holding a role', function (): void {
    $actor = superAdmin();

    $this->actingAs($actor, 'admin')
        ->postJson($this->centralUrl('/admin/system/roles'), ['name' => 'Auditors', 'permissions' => ['roles.manage']])
        ->assertCreated();

    $member = Admin::factory()->create();
    $member->assignRole('Auditors');

    $this->actingAs($member, 'admin')
        ->get($this->centralUrl('/admin/system/roles'))
        ->assertOk();
});

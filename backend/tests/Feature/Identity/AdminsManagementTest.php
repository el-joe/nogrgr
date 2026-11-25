<?php

use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Notifications\AdminTeamMemberAddedNotification;
use App\Modules\Identity\Notifications\AdminWelcomeNotification;
use App\Modules\Platform\Models\AuditEvent;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

require_once __DIR__.'/AdminTestHelpers.php';

/*
| Admin › System › Admins (ADM-110 / 110a): list JSON, modal create / edit, status
| switch, delete and the guards (self, last super-admin, super-admin only).
*/

const STRONG_PASSWORD = 'Str0ng!Passw0rd';

function adminPayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'Yasmin Ali',
        'email' => 'yasmin@example.test',
        'role' => 'editor',
        'status' => 'active',
        'password' => STRONG_PASSWORD,
        'password_confirmation' => STRONG_PASSWORD,
    ], $overrides);
}

function editorRole(): void
{
    Role::findOrCreate('editor', 'admin')->syncPermissions(['dashboard.view', 'vendors.view']);
}

dataset('admin endpoints', [
    'index' => ['get', '/admin/system/admins', 'admins.view'],
    'data' => ['get', '/admin/system/admins/data', 'admins.view'],
    'create' => ['get', '/admin/system/admins/create', 'admins.manage'],
    'store' => ['post', '/admin/system/admins', 'admins.manage'],
    'edit' => ['get', '/admin/system/admins/{id}/edit', 'admins.manage'],
    'update' => ['put', '/admin/system/admins/{id}', 'admins.manage'],
    'toggle' => ['patch', '/admin/system/admins/{id}/active', 'admins.manage'],
    'destroy' => ['delete', '/admin/system/admins/{id}', 'admins.manage'],
]);

it('redirects guests to the admin login', function (string $method, string $path): void {
    $target = Admin::factory()->create();

    $this->{$method}($this->centralUrl(str_replace('{id}', (string) $target->id, $path)))
        ->assertRedirect($this->centralUrl('/admin/login'));
})->with('admin endpoints');

it('forbids admins without the admins permission', function (string $method, string $path, string $permission): void {
    $target = Admin::factory()->create();
    $actor = adminWith($permission === 'admins.manage' ? ['admins.view'] : []);

    $this->actingAs($actor, 'admin')
        ->{$method.'Json'}($this->centralUrl(str_replace('{id}', (string) $target->id, $path)))
        ->assertForbidden();
})->with('admin endpoints');

it('renders the admins page with tabs and the invite modal', function (): void {
    $this->actingAs(superAdmin(), 'admin')
        ->get($this->centralUrl('/admin/system/admins'))
        ->assertOk()
        ->assertSee(__('identity::admins.add'))
        ->assertSee('data-datatable', false)
        ->assertSee('id="admin-modal"', false)
        ->assertSee(route('admin.system.roles.index'), false);
});

it('lists admins with search, role and status filters', function (): void {
    $actor = superAdmin(['name' => 'Sara Admin']);
    editorRole();
    Admin::factory()->create(['name' => 'Omar Haddad', 'email' => 'omar@example.test'])->assignRole('editor');
    Admin::factory()->suspended()->create(['name' => 'Lina Fares']);

    $base = '/admin/system/admins/data?draw=1&start=0&length=10';

    $this->actingAs($actor, 'admin')->getJson($this->centralUrl($base.'&search[value]=&search=omar'))
        ->assertOk()->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.admin', fn (string $html) => str_contains($html, 'Omar Haddad'));

    $this->actingAs($actor, 'admin')->getJson($this->centralUrl($base.'&role=editor'))
        ->assertJsonPath('recordsFiltered', 1);

    $this->actingAs($actor, 'admin')->getJson($this->centralUrl($base.'&status=suspended'))
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.admin', fn (string $html) => str_contains($html, 'Lina Fares'));
});

it('serves the create and edit modal forms', function (): void {
    $actor = superAdmin();
    $target = Admin::factory()->create(['name' => 'Omar Haddad']);

    $this->actingAs($actor, 'admin')->get($this->centralUrl('/admin/system/admins/create'))
        ->assertOk()->assertSee('id="admin-form"', false)->assertSee('name="password"', false);

    $this->actingAs($actor, 'admin')->get($this->centralUrl("/admin/system/admins/{$target->id}/edit"))
        ->assertOk()->assertSee('Omar Haddad')->assertSee(__('identity::admins.fields.password_keep'));
});

it('creates an admin with a role, audits it and notifies', function (): void {
    Notification::fake();
    $actor = superAdmin();
    $otherSuper = Admin::factory()->create();
    $otherSuper->assignRole('super-admin');
    editorRole();

    $this->actingAs($actor, 'admin')
        ->postJson($this->centralUrl('/admin/system/admins'), adminPayload())
        ->assertCreated();

    $admin = Admin::query()->where('email', 'yasmin@example.test')->sole();

    expect($admin->hasRole('editor'))->toBeTrue()
        ->and($admin->status)->toBe(AccountStatus::Active)
        ->and(password_verify(STRONG_PASSWORD, (string) $admin->password))->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'admin.created')->where('subject_id', (string) $admin->id)->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'admin.role_changed')->where('subject_id', (string) $admin->id)->exists())->toBeTrue();

    Notification::assertSentTo($admin, AdminWelcomeNotification::class);
    Notification::assertSentTo($otherSuper, AdminTeamMemberAddedNotification::class);
    Notification::assertNotSentTo($actor, AdminTeamMemberAddedNotification::class);
});

it('validates the admin form', function (): void {
    $actor = superAdmin();

    $this->actingAs($actor, 'admin')
        ->postJson($this->centralUrl('/admin/system/admins'), adminPayload([
            'name' => '',
            'email' => $actor->email,
            'role' => 'missing-role',
            'status' => 'nope',
            'password' => 'short',
            'password_confirmation' => 'other',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'role', 'status', 'password']);
});

it('updates an admin and keeps the password when left empty', function (): void {
    $actor = superAdmin();
    editorRole();
    $target = Admin::factory()->create();
    $target->assignRole('editor');
    $hash = $target->password;

    $this->actingAs($actor, 'admin')
        ->putJson($this->centralUrl("/admin/system/admins/{$target->id}"), adminPayload(['name' => 'Renamed', 'email' => $target->email, 'password' => '', 'password_confirmation' => '']))
        ->assertOk();

    expect($target->refresh()->name)->toBe('Renamed')->and($target->password)->toBe($hash);
});

it('activates and deactivates another admin', function (): void {
    $actor = superAdmin();
    $target = Admin::factory()->create();

    $this->actingAs($actor, 'admin')
        ->patchJson($this->centralUrl("/admin/system/admins/{$target->id}/active"), ['is_active' => 0])
        ->assertOk();
    expect($target->refresh()->status)->toBe(AccountStatus::Suspended);

    $this->actingAs($actor, 'admin')
        ->patchJson($this->centralUrl("/admin/system/admins/{$target->id}/active?is_active=1"))
        ->assertOk();
    expect($target->refresh()->status)->toBe(AccountStatus::Active);
});

it('never lets an admin deactivate or delete themselves', function (): void {
    $actor = superAdmin();
    $backup = Admin::factory()->create();
    $backup->assignRole('super-admin');

    $this->actingAs($actor, 'admin')
        ->patchJson($this->centralUrl("/admin/system/admins/{$actor->id}/active"), ['is_active' => 0])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('identity::admins.rules.deactivate_self'));

    $this->actingAs($actor, 'admin')
        ->putJson($this->centralUrl("/admin/system/admins/{$actor->id}"), adminPayload(['email' => $actor->email, 'role' => 'super-admin', 'status' => 'suspended', 'password' => '', 'password_confirmation' => '']))
        ->assertUnprocessable();

    $this->actingAs($actor, 'admin')
        ->deleteJson($this->centralUrl("/admin/system/admins/{$actor->id}"))
        ->assertUnprocessable()
        ->assertJsonPath('message', __('identity::admins.rules.delete_self'));

    expect($actor->refresh()->status)->toBe(AccountStatus::Active);
});

it('protects the last active super admin', function (): void {
    $first = superAdmin();
    $second = Admin::factory()->create();
    $second->assignRole('super-admin');

    $this->actingAs($second, 'admin')
        ->deleteJson($this->centralUrl("/admin/system/admins/{$first->id}"))
        ->assertOk();

    // $second is now the only active super admin.
    editorRole();
    $this->actingAs($second, 'admin')
        ->putJson($this->centralUrl("/admin/system/admins/{$second->id}"), adminPayload(['email' => $second->email, 'role' => 'editor', 'password' => '', 'password_confirmation' => '']))
        ->assertUnprocessable()
        ->assertJsonPath('message', __('identity::admins.rules.last_super_admin'));

    $dormant = Admin::factory()->suspended()->create();
    $dormant->assignRole('super-admin');

    $this->actingAs($dormant, 'admin')
        ->deleteJson($this->centralUrl("/admin/system/admins/{$second->id}"))
        ->assertUnprocessable()
        ->assertJsonPath('message', __('identity::admins.rules.last_super_admin'));

    $this->actingAs($dormant, 'admin')
        ->patchJson($this->centralUrl("/admin/system/admins/{$second->id}/active"), ['is_active' => 0])
        ->assertUnprocessable();

    expect($second->refresh()->hasRole('super-admin'))->toBeTrue()
        ->and($second->status)->toBe(AccountStatus::Active);
});

it('keeps super admin accounts and the role for super admins only', function (): void {
    superAdmin();
    $super = Admin::query()->sole();
    $manager = adminWith(['admins.manage']);

    $this->actingAs($manager, 'admin')
        ->deleteJson($this->centralUrl("/admin/system/admins/{$super->id}"))
        ->assertForbidden();

    $this->actingAs($manager, 'admin')
        ->postJson($this->centralUrl('/admin/system/admins'), adminPayload(['role' => 'super-admin']))
        ->assertUnprocessable()
        ->assertJsonPath('message', __('identity::admins.rules.super_admin_only'));
});

it('deletes another admin and audits it', function (): void {
    $actor = superAdmin();
    $target = Admin::factory()->create();

    $this->actingAs($actor, 'admin')
        ->deleteJson($this->centralUrl("/admin/system/admins/{$target->id}"))
        ->assertOk();

    expect(Admin::query()->find($target->id))->toBeNull()
        ->and(AuditEvent::query()->where('action', 'admin.deleted')->where('subject_id', (string) $target->id)->exists())->toBeTrue();
});

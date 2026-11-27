<?php

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Notifications\VendorStaffInvitationNotification;
use App\Modules\Identity\Notifications\VendorTeamMemberJoinedNotification;
use App\Modules\Identity\Support\PermissionMatrix;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
| VEN-076 / 076a / 077 / 077a: vendor Settings › Staff (invite by e-mail, edit, activate /
| deactivate, resend, delete, owner untouchable, self, plan seats, invite acceptance) and
| Roles & permissions (tenant guard matrix, owner role locked, assigned role kept).
*/

beforeEach(function (): void {
    $this->tenant = $this->createTenant('teamshop');
    $this->owner = $this->createVendorOwner($this->tenant, ['name' => 'Mostafa Owner']);
    $this->staffUrl = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/settings/staff'.$path);
    $this->rolesUrl = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/settings/roles'.$path);
    $this->role = fn (string $name, array $permissions = []): Role => $this->tenant->run(function () use ($name, $permissions): Role {
        $role = Role::findOrCreate($name, 'vendor');
        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role;
    });
    $this->member = fn (array $attributes = [], ?string $role = null): VendorStaff => $this->tenant->run(function () use ($attributes, $role): VendorStaff {
        $member = VendorStaff::factory()->create($attributes);

        if ($role !== null) {
            $member->assignRole($role);
        }

        return $member;
    });
});

it('sends guests to the login and forbids staff without the staff / roles permissions', function (): void {
    $this->get(($this->staffUrl)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));
    $this->get(($this->rolesUrl)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    ($this->role)('viewer', ['dashboard.view', 'settings.staff.view', 'settings.roles.view']);
    $viewer = ($this->member)([], 'viewer');
    $other = ($this->member)(['email' => 'other@teamshop.test']);
    $this->actingAsVendor($viewer, $this->tenant);

    $this->get(($this->staffUrl)())->assertOk()->assertSee(__('identity::staff.subtitle'))->assertDontSee(__('identity::staff.invite_title'));
    $this->getJson(($this->staffUrl)('/data?draw=1&start=0&length=10'))->assertOk()->assertJsonPath('recordsTotal', 3);
    $this->getJson(($this->staffUrl)('/'.$other->id))->assertOk();
    $this->get(($this->rolesUrl)())->assertOk();
    $this->getJson(($this->staffUrl)('/create'))->assertForbidden();
    $this->postJson(($this->staffUrl)(), ['name' => 'X'])->assertForbidden();
    $this->putJson(($this->staffUrl)('/'.$other->id), [])->assertForbidden();
    $this->patchJson(($this->staffUrl)('/'.$other->id.'/active'), ['is_active' => 0])->assertForbidden();
    $this->postJson(($this->staffUrl)('/'.$other->id.'/resend'))->assertForbidden();
    $this->deleteJson(($this->staffUrl)('/'.$other->id))->assertForbidden();
    $this->get(($this->rolesUrl)('/create'))->assertForbidden();
    $this->postJson(($this->rolesUrl)(), ['name' => 'X'])->assertForbidden();

    ($this->role)('nobody', ['dashboard.view']);
    $this->actingAsVendor(($this->member)(['email' => 'none@teamshop.test'], 'nobody'), $this->tenant);

    foreach ([($this->staffUrl)(), ($this->staffUrl)('/data'), ($this->rolesUrl)(), ($this->rolesUrl)('/data')] as $url) {
        $this->getJson($url)->assertForbidden();
    }
});

it('invites a member who becomes active and notifies the owner once they set a password', function (): void {
    Notification::fake();
    $packer = ($this->role)('packer', ['dashboard.view', 'orders.view']);
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->postJson(($this->staffUrl)(), ['name' => 'Ali Fahd', 'email' => 'ALI@megz.test', 'phone' => '0501234567', 'phone_country' => 'sa', 'role' => 'packer'])
        ->assertCreated()->assertJsonPath('message', __('identity::staff.invited', ['name' => 'Ali Fahd', 'email' => 'ali@megz.test']));

    $member = $this->tenant->run(fn (): VendorStaff => VendorStaff::query()->where('email', 'ali@megz.test')->firstOrFail());
    expect($member->status)->toBe(AccountStatus::Pending)
        ->and($member->phone)->toBe('+966501234567')
        ->and($this->tenant->run(fn () => $member->hasRole($packer)))->toBeTrue();

    $url = null;
    Notification::assertSentTo($member, VendorStaffInvitationNotification::class, function (VendorStaffInvitationNotification $notification) use (&$url): bool {
        $url = $notification->url;

        return str_contains($notification->url, '/invitation/') && $notification->roleLabel === 'Packer';
    });

    $this->get(($this->staffUrl)())->assertOk()->assertSee(__('identity::staff.seats_unlimited'))
        // QA-P8-005: counts, seats, limit notice and Invite refresh in place after invite / toggle / delete.
        ->assertSee('data-live="staff-counts"', false)->assertSee('data-live="staff-limit"', false)->assertSee('data-live="staff-invite"', false);
    $row = collect($this->getJson(($this->staffUrl)('/data?draw=1&start=0&length=10&status=pending'))->assertOk()->json('data'))->sole();
    expect($row['status'])->toContain(__('identity::staff.invited_status'))->and($row['actions'])->toContain(__('identity::staff.resend'));

    $this->postJson(($this->staffUrl)('/'.$member->id.'/resend'))->assertOk();
    $this->patchJson(($this->staffUrl)('/'.$member->id.'/active'), ['is_active' => 1])->assertStatus(422)->assertJsonPath('message', __('identity::staff.rules.pending'));

    /* Accept: the invitation token is a vendor_staff password-reset token. */
    $token = $this->tenant->run(fn (): string => Password::broker('vendor_staff')->createToken($member));
    expect($url)->not->toBeNull();
    $this->flushSession();
    app('auth')->forgetGuards();
    /* QA-P8-008: the link opens a welcome / set-password page, not the generic reset page. */
    $this->get($this->tenantUrl($this->tenant, 'invitation/'.$token.'?email=ali%40megz.test'))
        ->assertOk()
        ->assertSee(__('identity::vendor_auth.invitation.title', ['store' => $this->tenant->name]))
        ->assertSee(__('identity::vendor_auth.invitation.submit'))
        ->assertSee('name="invitation" value="1"', false)
        ->assertDontSee(__('identity::vendor_auth.reset.title'))
        ->assertDontSee('data-invitation-invalid', false);
    $this->get($this->tenantUrl($this->tenant, 'invitation/bad-token?email=ali%40megz.test'))->assertOk()->assertSee('data-invitation-invalid', false);
    $this->post($this->tenantUrl($this->tenant, 'reset-password'), ['token' => $token, 'email' => 'ali@megz.test', 'invitation' => 1, 'password' => 'N3w-strong-pass!', 'password_confirmation' => 'N3w-strong-pass!'])
        ->assertSessionHasNoErrors()->assertRedirect($this->tenantUrl($this->tenant, 'login'))
        ->assertSessionHas('success', __('identity::vendor_auth.invitation.done'));

    $member = $this->tenant->run(fn (): VendorStaff => $member->fresh());
    expect($member->status)->toBe(AccountStatus::Active)->and($member->email_verified_at)->not->toBeNull();
    Notification::assertSentTo($this->owner, VendorTeamMemberJoinedNotification::class);
    $this->actingAsVendor($this->owner, $this->tenant);
    $this->postJson(($this->staffUrl)('/'.$member->id.'/resend'))->assertStatus(422);
});

it('validates the invite and never grants the owner role', function (): void {
    Notification::fake();
    ($this->role)('packer', ['orders.view']);
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->postJson(($this->staffUrl)(), [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'role']);
    $this->postJson(($this->staffUrl)(), ['name' => 'X', 'email' => $this->owner->email, 'role' => 'owner'])->assertUnprocessable()->assertJsonValidationErrors(['email', 'role']);
    $this->postJson(($this->staffUrl)(), ['name' => 'X', 'email' => 'x@megz.test', 'role' => 'missing', 'phone' => '123', 'phone_country' => 'SA'])->assertUnprocessable()->assertJsonValidationErrors(['role', 'phone']);
    $this->getJson(($this->staffUrl)('/create'))->assertOk()->assertSee('packer')->assertDontSee('value="owner"', false);
    Notification::assertNothingSent();
});

it('enforces the plan staff seats', function (): void {
    Notification::fake();
    ($this->role)('packer', ['orders.view']);
    $plan = Plan::factory()->create(['limits' => ['staff' => 2]]);
    Subscription::factory()->create(['tenant_id' => $this->tenant->id, 'plan_id' => $plan->id]);
    ($this->member)(['email' => 'two@megz.test'], 'packer');
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->get(($this->staffUrl)())->assertOk()->assertSee(__('identity::staff.seats', ['used' => 2, 'limit' => 2]));
    $this->postJson(($this->staffUrl)(), ['name' => 'Three', 'email' => 'three@megz.test', 'role' => 'packer'])
        ->assertStatus(422)->assertJsonPath('message', trans_choice('identity::staff.rules.limit', 2, ['count' => 2]));
});

it('edits, deactivates, reactivates and deletes a member but never the owner or yourself', function (): void {
    Notification::fake();
    ($this->role)('packer', ['orders.view']);
    ($this->role)('manager', ['orders.view', 'orders.manage', 'settings.staff.view', 'settings.staff.manage']);
    $member = ($this->member)(['email' => 'hala@megz.test', 'status' => AccountStatus::Active], 'packer');
    $manager = ($this->member)(['email' => 'boss@megz.test', 'status' => AccountStatus::Active], 'manager');
    $this->actingAsVendor($manager, $this->tenant);

    $this->getJson(($this->staffUrl)('/'.$member->id.'/edit'))->assertOk()->assertSee('hala@megz.test');
    $this->putJson(($this->staffUrl)('/'.$member->id), ['name' => 'Hala Saeed', 'email' => 'hala@megz.test', 'role' => 'manager'])->assertOk();
    expect($this->tenant->run(fn () => $member->fresh()->getRoleNames()->all()))->toBe(['manager']);

    $this->patchJson(($this->staffUrl)('/'.$member->id.'/active'), ['is_active' => 0])->assertOk();
    expect($this->tenant->run(fn () => $member->fresh()->status))->toBe(AccountStatus::Suspended);
    $this->patchJson(($this->staffUrl)('/'.$member->id.'/active'), ['is_active' => 1])->assertOk();
    expect($this->tenant->run(fn () => $member->fresh()->status))->toBe(AccountStatus::Active);

    /* Owner untouchable, self not editable here. */
    $this->getJson(($this->staffUrl)('/'.$this->owner->id.'/edit'))->assertForbidden();
    $this->putJson(($this->staffUrl)('/'.$this->owner->id), ['name' => 'X', 'email' => $this->owner->email, 'role' => 'packer'])->assertStatus(422)->assertJsonPath('message', __('identity::staff.rules.owner_locked'));
    $this->patchJson(($this->staffUrl)('/'.$this->owner->id.'/active'), ['is_active' => 0])->assertStatus(422);
    $this->deleteJson(($this->staffUrl)('/'.$this->owner->id))->assertStatus(422);
    $this->deleteJson(($this->staffUrl)('/'.$manager->id))->assertStatus(422)->assertJsonPath('message', __('identity::staff.rules.self'));

    $this->deleteJson(($this->staffUrl)('/'.$member->id))->assertOk();
    expect($this->tenant->run(fn () => VendorStaff::query()->whereKey($member->id)->exists()))->toBeFalse();
    $this->getJson(($this->staffUrl)('/'.$member->id))->assertNotFound();
});

it('signs a deactivated member out on the next request', function (): void {
    ($this->role)('packer', ['dashboard.view']);
    $member = ($this->member)(['status' => AccountStatus::Suspended], 'packer');
    $this->actingAsVendor($member, $this->tenant);

    $this->get($this->tenantUrl($this->tenant, 'admin/dashboard'))->assertRedirect($this->tenantUrl($this->tenant, 'login'));
});

it('keeps each store team in its own database', function (): void {
    $other = $this->createTenant('othershop');
    $outsider = $this->createVendorOwner($other, ['email' => 'outsider@other.test']);
    $this->actingAsVendor($this->owner, $this->tenant);

    $rows = $this->getJson(($this->staffUrl)('/data?draw=1&start=0&length=10'))->assertOk()->json('data');
    expect(collect($rows)->pluck('member')->join(' '))->not->toContain('outsider@other.test');

    $this->actingAsVendor($this->owner, $this->tenant);
    $this->get($this->tenantUrl($other, 'admin/settings/staff'))->assertNotFound();
    expect($outsider->id)->toBeInt();
});

it('creates, edits and deletes store roles with the tenant matrix and locks the owner role', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->get(($this->rolesUrl)('/create'))->assertOk()->assertSee(__('identity::roles.module'))->assertSee('settings.staff.manage');
    $this->postJson(($this->rolesUrl)(), ['name' => 'Packer', 'permissions' => ['orders.manage', 'returns.view', 'vendors.manage']])
        ->assertUnprocessable()->assertJsonValidationErrors(['permissions.2']);
    $this->postJson(($this->rolesUrl)(), ['name' => 'Packer', 'permissions' => ['orders.manage', 'returns.view']])->assertCreated();

    $role = $this->tenant->run(fn (): Role => Role::findByName('Packer', 'vendor'));
    expect($this->tenant->run(fn () => $role->permissions->pluck('name')->sort()->values()->all()))->toBe(['orders.manage', 'orders.view', 'returns.view']);

    $rows = collect($this->getJson(($this->rolesUrl)('/data?draw=1&start=0&length=10'))->assertOk()->json('data'));
    expect($rows)->toHaveCount(2);

    $this->get(($this->rolesUrl)('/'.$role->id.'/edit'))->assertOk()->assertSee('Packer');
    $this->putJson(($this->rolesUrl)('/'.$role->id), ['name' => 'Packer', 'permissions' => ['orders.view']])->assertOk();

    /* Assigned roles stay; the owner role is locked. */
    ($this->member)(['email' => 'p@megz.test'], 'Packer');
    $this->deleteJson(($this->rolesUrl)('/'.$role->id))->assertStatus(422)->assertJsonPath('message', trans_choice('identity::roles.rules.in_use_staff', 1, ['count' => 1]));
    $owner = $this->tenant->run(fn (): Role => Role::findByName('owner', 'vendor'));
    $this->get(($this->rolesUrl)('/'.$owner->id.'/edit'))->assertOk()->assertSee(__('identity::staff.roles.owner_locked'));
    // QA-P8: the store gets the store-owner message, not the admin "super admin" one.
    $this->putJson(($this->rolesUrl)('/'.$owner->id), ['name' => 'owner', 'permissions' => []])->assertStatus(422)->assertJsonPath('message', __('identity::roles.rules.locked_owner'));
    $this->deleteJson(($this->rolesUrl)('/'.$owner->id))->assertStatus(422)->assertJsonPath('message', __('identity::roles.rules.locked_owner'));

    $spare = ($this->role)('spare');
    $this->deleteJson(($this->rolesUrl)('/'.$spare->id))->assertOk();
    $this->get(($this->rolesUrl)('/999999/edit'))->assertNotFound();
});

it('groups the vendor permission matrix by store panel area', function (): void {
    $matrix = PermissionMatrix::for('vendor');

    expect($matrix['modules'])->toHaveKeys(['overview', 'catalog', 'sales', 'customers', 'store', 'marketing', 'billing', 'settings'])
        ->and($matrix['modules']['settings'])->toHaveKey('settings.staff')
        ->and(PermissionMatrix::moduleOf('coupons', 'vendor'))->toBe('marketing')
        ->and(PermissionMatrix::moduleOf('vendors.kyc'))->toBe('vendors');
});

it('shows a status badge on every staff row and keeps activate / deactivate in the row menu (QA-P8-011)', function (): void {
    ($this->role)('QA Support', ['orders.view']);
    ($this->member)(['email' => 'active@teamshop.test', 'status' => AccountStatus::Active], 'QA Support');
    ($this->member)(['email' => 'off@teamshop.test', 'status' => AccountStatus::Suspended], 'QA Support');
    $this->actingAsVendor($this->owner, $this->tenant);

    $rows = collect($this->getJson(($this->staffUrl)('/data?draw=1&start=0&length=10'))->assertOk()->json('data'));
    $active = $rows->first(fn (array $row): bool => str_contains($row['member'], 'active@teamshop.test'));
    $off = $rows->first(fn (array $row): bool => str_contains($row['member'], 'off@teamshop.test'));

    expect($active['status'])->not->toContain('role="switch"')->toContain(AccountStatus::Active->label())
        ->and($active['actions'])->toContain(__('identity::staff.deactivate'))
        ->and($off['status'])->not->toContain('role="switch"')->toContain(AccountStatus::Suspended->label())
        ->and($off['actions'])->toContain(__('identity::staff.activate'))
        // QA-P8-007: the role keeps the casing the store typed.
        ->and($active['role'])->toContain('QA Support')->not->toContain('Qa Support');
});

it('shows a translated, friendly 403 page instead of the permission package message', function (string $locale): void {
    ($this->role)('nobody', ['dashboard.view']);
    $this->actingAsVendor(($this->member)(['email' => 'none@teamshop.test'], 'nobody'), $this->tenant);

    $this->get(($this->staffUrl)('?lang='.$locale))->assertForbidden()
        ->assertSee(trans('ui.http.errors.403_text', [], $locale))
        ->assertDontSee('User does not have the right permissions.');
})->with(['en', 'ar']);

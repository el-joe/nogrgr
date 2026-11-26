<?php

use App\Modules\Identity\Jobs\SyncOwnerStaffPassword;
use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Notifications\VendorVerifyEmailNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/*
| VEN-070 Vendor › Settings › Account: every member edits only themself (profile, phone,
| language, password); the store owner's e-mail stays with the owner account, the owner's
| password change reaches the owner account, and the owner sees the linked sign-in methods.
*/

beforeEach(function (): void {
    $this->ownerAccount = Owner::factory()->create(['email' => 'owner-'.Str::lower(Str::random(6)).'@megz.test', 'google_id' => 'g-123']);
    $this->tenant = $this->createTenant('acc-'.Str::lower(Str::random(6)), ['owner_id' => $this->ownerAccount->id]);
    $this->owner = $this->createVendorOwner($this->tenant, ['email' => $this->ownerAccount->email, 'password' => 'Secret123']);
    $this->member = $this->tenant->run(function (): VendorStaff {
        $member = VendorStaff::factory()->create(['email' => 'hala-'.Str::lower(Str::random(6)).'@megz.test', 'password' => 'Secret123', 'is_owner' => false]);
        $member->givePermissionTo('dashboard.view');

        return $member;
    });
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/settings/account'.$path);
});

function accountPayload(array $overrides = []): array
{
    return array_merge(['name' => 'Hala Saeed', 'email' => 'hala.new@megz.test', 'phone' => '0501234567', 'phone_country' => 'sa', 'locale' => 'en'], $overrides);
}

it('requires a signed-in member with dashboard access', function (): void {
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));
    $this->putJson(($this->url)(), accountPayload())->assertUnauthorized();

    $bare = $this->tenant->run(fn (): VendorStaff => VendorStaff::factory()->create());
    $this->actingAsVendor($bare, $this->tenant);
    $this->get(($this->url)())->assertForbidden();
    $this->putJson(($this->url)('/password'), [])->assertForbidden();
});

it('validates and saves the member profile and re-confirms a new e-mail', function (): void {
    Notification::fake();
    $this->actingAsVendor($this->member, $this->tenant);
    $this->get(($this->url)())->assertOk()->assertSee($this->member->email)->assertDontSee(__('identity::vendor_account.linked'));

    $this->putJson(($this->url)(), accountPayload(['name' => '', 'email' => $this->owner->email, 'phone' => '12', 'locale' => 'xx']))
        ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'phone', 'locale']);

    $this->putJson(($this->url)(), accountPayload())->assertOk()
        ->assertJsonPath('message', __('identity::vendor_account.updated_verify', ['email' => 'hala.new@megz.test']));

    $member = $this->tenant->run(fn () => VendorStaff::query()->find($this->member->id));
    expect($member->name)->toBe('Hala Saeed')
        ->and($member->email)->toBe('hala.new@megz.test')
        ->and($member->phone)->toBe('+966501234567')
        ->and($member->email_verified_at)->toBeNull();
    Notification::assertSentTo($member, VendorVerifyEmailNotification::class);
});

it('keeps the store owner e-mail and shows the owner sign-in methods', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);
    $this->get(($this->url)())->assertOk()->assertSee(__('identity::vendor_account.linked'))->assertSee(__('identity::vendor_account.linked_badge'))
        ->assertSee(__('identity::vendor_account.fields.email_owner_help'));

    $this->putJson(($this->url)(), accountPayload(['email' => 'hijack@megz.test', 'name' => 'Mostafa Gamal']))->assertOk();
    $owner = $this->tenant->run(fn () => VendorStaff::query()->find($this->owner->id));
    expect($owner->email)->toBe($this->ownerAccount->email)->and($owner->name)->toBe('Mostafa Gamal');
});

it('changes the password with the current one', function (): void {
    $this->actingAsVendor($this->member, $this->tenant);

    $this->putJson(($this->url)('/password'), ['current_password' => 'wrong', 'password' => 'short', 'password_confirmation' => 'nope'])
        ->assertUnprocessable()->assertJsonValidationErrors(['current_password', 'password']);
    $this->putJson(($this->url)('/password'), ['current_password' => 'Secret123', 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456'])->assertOk();

    expect(Hash::check('NewPass456', $this->tenant->run(fn () => VendorStaff::query()->find($this->member->id)->password)))->toBeTrue();
});

it('sends the owner password change to the owner account and all its stores', function (): void {
    Queue::fake();
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->putJson(($this->url)('/password'), ['current_password' => 'Secret123', 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456'])->assertOk();

    expect(Hash::check('NewPass456', $this->ownerAccount->fresh()->password))->toBeTrue()
        ->and(Hash::check('NewPass456', $this->tenant->run(fn () => VendorStaff::query()->find($this->owner->id)->password)))->toBeTrue();
    Queue::assertPushed(SyncOwnerStaffPassword::class);
});

it('never reaches another store account page', function (): void {
    $other = $this->createTenant('acc-o-'.Str::lower(Str::random(6)));
    $this->actingAsVendor($this->member, $this->tenant);

    $this->get($this->tenantUrl($other, 'admin/settings/account'))->assertNotFound();
});

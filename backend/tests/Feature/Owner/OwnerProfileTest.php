<?php

use App\Modules\Identity\Jobs\SyncOwnerStaffPassword;
use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Support\Social\GoogleOAuthClient;
use App\Modules\Platform\Enums\TenantStatus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/OwnerHelpers.php';

/*
| OWN-006 Profile & password, linking / unlinking Google and Apple. An owner never
| loses the last way to sign in.
*/

beforeEach(function (): void {
    config([
        'services.google' => ['enabled' => true, 'client_id' => 'google-client', 'client_secret' => 'google-secret'],
        'services.apple.enabled' => false,
    ]);
});

it('renders the profile with the linked accounts', function (): void {
    $owner = Owner::factory()->create(['name' => 'Mohamed Saleh', 'google_id' => 'g-1']);
    $this->actingAs($owner, 'owner');

    $this->get(route('owner.profile.edit'))
        ->assertOk()
        ->assertSee('Mohamed Saleh')
        ->assertSee(route('owner.profile.social.unlink', 'google'), false)
        ->assertSee(__('identity::owner_profile.change_password'));
});

it('validates and saves the personal information', function (): void {
    $owner = Owner::factory()->create(['email' => 'owner@example.test']);
    $this->actingAs($owner, 'owner');

    $this->put(route('owner.profile.update'), ['name' => '', 'phone' => '123', 'phone_country' => 'SA', 'locale' => 'xx'])
        ->assertSessionHasErrors(['name', 'phone', 'locale']);

    $this->put(route('owner.profile.update'), ['name' => 'Mohamed Saleh', 'phone' => '0501234567', 'phone_country' => 'sa', 'locale' => 'en', 'email' => 'hijack@example.test'])
        ->assertRedirect(route('owner.profile.edit'))
        ->assertSessionHas('success');

    $owner->refresh();
    expect($owner->name)->toBe('Mohamed Saleh')
        ->and($owner->phone)->toBe('+966501234567')
        ->and($owner->locale)->toBe('en')
        ->and($owner->email)->toBe('owner@example.test');
});

it('changes the password after checking the current one and syncs every store', function (): void {
    Queue::fake();
    $owner = Owner::factory()->create(['password' => 'old-password-1']);
    $store = ownerStore($owner);
    ownerStore($owner, ['status' => TenantStatus::Provisioning]);
    ownerStore(Owner::factory()->create());
    $this->actingAs($owner, 'owner');

    $this->put(route('owner.profile.password'), ['current_password' => 'wrong', 'password' => 'new-password-2', 'password_confirmation' => 'new-password-2'])
        ->assertSessionHasErrors('current_password');
    $this->put(route('owner.profile.password'), ['current_password' => 'old-password-1', 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertSessionHasErrors('password');

    $this->put(route('owner.profile.password'), ['current_password' => 'old-password-1', 'password' => 'new-password-2', 'password_confirmation' => 'new-password-2'])
        ->assertRedirect(route('owner.profile.edit'))
        ->assertSessionHas('success');

    expect(Hash::check('new-password-2', $owner->refresh()->password))->toBeTrue();
    Queue::assertPushed(SyncOwnerStaffPassword::class, 1);
    Queue::assertPushed(SyncOwnerStaffPassword::class, fn (SyncOwnerStaffPassword $job): bool => $job->tenantId === $store->id && Hash::check('new-password-2', $job->passwordHash));
});

it('lets a social-only owner set a first password', function (): void {
    Queue::fake();
    $owner = Owner::factory()->withoutPassword()->create(['google_id' => 'g-1']);
    $this->actingAs($owner, 'owner');

    $this->get(route('owner.profile.edit'))->assertOk()->assertSee(__('identity::owner_profile.set_password'))->assertDontSee('name="current_password"', false);

    $this->put(route('owner.profile.password'), ['password' => 'first-password-1', 'password_confirmation' => 'first-password-1'])
        ->assertSessionHasNoErrors();

    expect(Hash::check('first-password-1', $owner->refresh()->password))->toBeTrue();
});

it('copies the new password to the owner staff account of a store', function (): void {
    $owner = Owner::factory()->create(['email' => 'boss@example.test']);
    $acme = $this->createTenant('acme', ['owner_id' => $owner->id]);
    $this->createVendorOwner($acme, ['email' => 'boss@example.test']);

    (new SyncOwnerStaffPassword($acme->id, 'boss@example.test', Hash::make('synced-pass-9')))->handle();

    $acme->run(function (): void {
        expect(Hash::check('synced-pass-9', (string) VendorStaff::query()->where('email', 'boss@example.test')->value('password')))->toBeTrue();
    });
});

it('never unlinks the last sign-in method', function (): void {
    $owner = Owner::factory()->withoutPassword()->create(['google_id' => 'g-1']);
    $this->actingAs($owner, 'owner');

    $this->get(route('owner.profile.edit'))->assertOk();

    $this->delete(route('owner.profile.social.unlink', 'google'))
        ->assertRedirect(route('owner.profile.edit'))
        ->assertSessionHasErrors('provider');

    expect($owner->refresh()->google_id)->toBe('g-1');
});

it('unlinks a provider while another method remains', function (): void {
    $owner = Owner::factory()->withoutPassword()->create(['google_id' => 'g-1', 'apple_id' => 'a-1']);
    $this->actingAs($owner, 'owner');

    $this->delete(route('owner.profile.social.unlink', 'google'))->assertSessionHasNoErrors()->assertSessionHas('success');
    expect($owner->refresh()->google_id)->toBeNull();

    // Apple is now the only method left.
    $this->delete(route('owner.profile.social.unlink', 'apple'))->assertSessionHasErrors('provider');
    expect($owner->refresh()->apple_id)->toBe('a-1');
});

it('unlinks a provider when a password is set', function (): void {
    $owner = Owner::factory()->create(['google_id' => 'g-1']);
    $this->actingAs($owner, 'owner');

    $this->delete(route('owner.profile.social.unlink', 'google'))->assertSessionHasNoErrors();
    expect($owner->refresh()->google_id)->toBeNull();
});

it('rejects unlinking a provider that is not linked', function (): void {
    $this->actingAs(Owner::factory()->create(), 'owner');

    $this->delete(route('owner.profile.social.unlink', 'google'))->assertSessionHasErrors('provider');
});

it('links Google to the signed-in owner through the shared callback', function (): void {
    $owner = Owner::factory()->create(['email' => 'owner@example.test']);
    $this->actingAs($owner, 'owner');

    $location = (string) $this->post(route('owner.profile.social.link', 'google'))->assertRedirect()->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    Http::fake([
        GoogleOAuthClient::TOKEN_URL => Http::response(['access_token' => 'ya29.token', 'id_token' => 'x.y.z', 'expires_in' => 3599]),
        GoogleOAuthClient::USERINFO_URL => Http::response(['sub' => 'g-linked', 'email' => 'other-address@gmail.com', 'email_verified' => true]),
    ]);

    $this->get(route('central.social.callback', ['provider' => 'google', 'code' => 'c', 'state' => $query['state']]))
        ->assertOk()
        ->assertSee(route('owner.profile.edit'), false);

    expect($owner->refresh()->google_id)->toBe('g-linked')
        ->and(Owner::query()->count())->toBe(1);
});

it('refuses a Google account already linked to another owner', function (): void {
    Owner::factory()->create(['google_id' => 'g-taken']);
    $owner = Owner::factory()->create();
    $this->actingAs($owner, 'owner');

    $location = (string) $this->post(route('owner.profile.social.link', 'google'))->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    Http::fake([
        GoogleOAuthClient::TOKEN_URL => Http::response(['access_token' => 'ya29.token', 'id_token' => 'x.y.z', 'expires_in' => 3599]),
        GoogleOAuthClient::USERINFO_URL => Http::response(['sub' => 'g-taken', 'email' => 'x@gmail.com', 'email_verified' => true]),
    ]);

    $this->get(route('central.social.callback', ['provider' => 'google', 'code' => 'c', 'state' => $query['state']]))->assertOk();

    expect($owner->refresh()->google_id)->toBeNull();
});

it('hides a disabled provider', function (): void {
    config(['services.google.enabled' => false]);
    $this->actingAs(Owner::factory()->create(), 'owner');

    $this->post(route('owner.profile.social.link', 'google'))->assertNotFound();
});

<?php

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use App\Modules\Identity\Notifications\VendorVerifyEmailNotification;
use App\Modules\Platform\Models\AuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

/*
| VEN-001…004: store-host vendor sign-in, forgot / reset password, e-mail verification.
*/

describe('login (VEN-001)', function (): void {
    it('shows the store sign-in page with the store name', function (): void {
        $tenant = $this->createTenant('acme', ['name' => 'Acme Store']);

        $this->get($this->tenantUrl($tenant, 'login'))
            ->assertOk()
            ->assertSee('Acme Store')
            ->assertSee(__('identity::vendor_auth.login.title'))
            ->assertSee($this->tenantUrl($tenant, 'forgot-password'), false);
    });

    it('validates the form', function (): void {
        $tenant = $this->createTenant('acme');

        $this->post($this->tenantUrl($tenant, 'login'), ['email' => 'not-an-email'])
            ->assertSessionHasErrors(['email', 'password']);
    });

    it('signs in with remember me and lands on the dashboard', function (): void {
        $tenant = $this->createTenant('acme');
        $this->createVendorOwner($tenant, ['email' => 'owner@acme.test', 'password' => 'secret-pass-1']);

        $response = $this->post($this->tenantUrl($tenant, 'login'), [
            'email' => 'owner@acme.test',
            'password' => 'secret-pass-1',
            'remember' => '1',
        ]);

        $response->assertRedirect($this->tenantUrl($tenant, 'admin/dashboard'));
        $response->assertCookie(Auth::guard('vendor')->getRecallerName());
        $this->assertAuthenticated('vendor');
    });

    it('throttles repeated failures per e-mail and IP', function (): void {
        $tenant = $this->createTenant('acme');
        $this->createVendorOwner($tenant, ['email' => 'owner@acme.test']);

        foreach (range(1, 5) as $attempt) {
            $this->post($this->tenantUrl($tenant, 'login'), ['email' => 'owner@acme.test', 'password' => 'wrong-'.$attempt])
                ->assertSessionHasErrors('email');
        }

        $this->postJson($this->tenantUrl($tenant, 'login'), ['email' => 'owner@acme.test', 'password' => 'wrong-again'])
            ->assertStatus(429)
            ->assertJsonValidationErrors('email');
    });

    it('redirects guests of the panel to the store login', function (): void {
        $tenant = $this->createTenant('acme');

        $this->get($this->tenantUrl($tenant, 'admin/dashboard'))->assertRedirect($this->tenantUrl($tenant, 'login'));
        $this->get($this->tenantUrl($tenant, 'admin/onboarding'))->assertRedirect($this->tenantUrl($tenant, 'login'));
    });
});

describe('forgot / reset password (VEN-002, VEN-003)', function (): void {
    it('mails a reset link to the store host and shows the sent state', function (): void {
        Notification::fake();
        $tenant = $this->createTenant('acme');
        $owner = $this->createVendorOwner($tenant, ['email' => 'owner@acme.test']);

        $this->get($this->tenantUrl($tenant, 'forgot-password'))->assertOk()->assertSee(__('identity::vendor_auth.forgot.title'));

        $this->post($this->tenantUrl($tenant, 'forgot-password'), ['email' => 'owner@acme.test'])
            ->assertRedirect($this->tenantUrl($tenant, 'forgot-password'));

        $this->get($this->tenantUrl($tenant, 'forgot-password'))
            ->assertOk()
            ->assertSee('data-reset-sent', false)
            ->assertSee('owner@acme.test');

        Notification::assertSentTo($owner, ResetPasswordNotification::class, fn (ResetPasswordNotification $notification): bool => str_starts_with($notification->url, $this->tenantUrl($tenant, 'reset-password/')));
    });

    it('shows the same sent state for an unknown e-mail', function (): void {
        Notification::fake();
        $tenant = $this->createTenant('acme');

        $this->post($this->tenantUrl($tenant, 'forgot-password'), ['email' => 'nobody@acme.test'])->assertRedirect();
        $this->get($this->tenantUrl($tenant, 'forgot-password'))->assertSee('data-reset-sent', false);

        Notification::assertNothingSent();
    });

    it('shows the invalid-token state for a bad link', function (): void {
        $tenant = $this->createTenant('acme');
        $this->createVendorOwner($tenant, ['email' => 'owner@acme.test']);

        $this->get($this->tenantUrl($tenant, 'reset-password/not-a-token?email=owner@acme.test'))
            ->assertOk()
            ->assertSee('data-reset-invalid', false);

        $this->post($this->tenantUrl($tenant, 'reset-password'), [
            'token' => 'not-a-token',
            'email' => 'owner@acme.test',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertSessionHasErrors('email')->assertSessionHas('vendor_reset_invalid', true);
    });

    it('resets the password with a valid token', function (): void {
        $tenant = $this->createTenant('acme');
        $owner = $this->createVendorOwner($tenant, ['email' => 'owner@acme.test']);
        $token = $tenant->run(fn (): string => Password::broker('vendor_staff')->createToken($owner));

        $this->get($this->tenantUrl($tenant, 'reset-password/'.$token.'?email=owner@acme.test'))
            ->assertOk()
            ->assertDontSee('data-reset-invalid', false);

        $this->post($this->tenantUrl($tenant, 'reset-password'), [
            'token' => $token,
            'email' => 'owner@acme.test',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertRedirect($this->tenantUrl($tenant, 'login'));

        expect($tenant->run(fn (): bool => Hash::check('new-password-1', (string) VendorStaff::query()->findOrFail($owner->id)->password)))->toBeTrue();
    });
});

describe('verify e-mail (VEN-004)', function (): void {
    /**
     * Signed URL built for the store host (the signature covers the host).
     */
    function vendorVerifyUrl(string $root, VendorStaff $staff, int $minutes = 60): string
    {
        URL::forceRootUrl(rtrim($root, '/'));
        $url = URL::temporarySignedRoute('vendor.verification.verify', now()->addMinutes($minutes), ['id' => $staff->id, 'hash' => sha1($staff->email)]);
        URL::forceRootUrl(null);

        return $url;
    }

    it('verifies the address and audits it', function (): void {
        $tenant = $this->createTenant('acme');
        $owner = $this->createVendorOwner($tenant, ['email' => 'owner@acme.test']);

        $this->get(vendorVerifyUrl($this->tenantUrl($tenant, ''), $owner))
            ->assertOk()
            ->assertSee('data-verify-state="verified"', false)
            ->assertSee('owner@acme.test');

        expect($tenant->run(fn () => VendorStaff::query()->findOrFail($owner->id)->email_verified_at))->not->toBeNull()
            ->and(AuditEvent::withoutTenantScope()->where('action', 'auth.email_verified')->count())->toBe(1);
    });

    it('shows the expired state for an expired or tampered link', function (): void {
        $tenant = $this->createTenant('acme');
        $owner = $this->createVendorOwner($tenant, ['email' => 'owner@acme.test']);
        $url = vendorVerifyUrl($this->tenantUrl($tenant, ''), $owner);

        $this->travel(61)->minutes();
        $this->get($url)->assertStatus(410)->assertSee('data-verify-state="expired"', false);

        $this->travelBack();
        $this->get($url.'x')->assertStatus(410);
        expect($tenant->run(fn () => VendorStaff::query()->findOrFail($owner->id)->email_verified_at))->toBeNull();
    });

    it('sends a new link to a signed-in unverified member', function (): void {
        Notification::fake();
        $tenant = $this->createTenant('acme');
        $owner = $this->createVendorOwner($tenant, ['email_verified_at' => null]);

        $this->actingAsVendor($owner, $tenant)
            ->post($this->tenantUrl($tenant, 'email/verification-notification'))
            ->assertRedirect();

        Notification::assertSentTo($owner, VendorVerifyEmailNotification::class);
    });
});

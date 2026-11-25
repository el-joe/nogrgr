<?php

use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Notifications\StoreLoginPasswordResetNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/*
| Central store login (WEB-022) and its forgot / reset password flow (WEB-023 / 024).
*/

it('shows the central store login form', function (): void {
    $this->get($this->centralUrl('/login?lang=en'))
        ->assertOk()
        ->assertSee(route('central.tenant.login.store'), false)
        ->assertSee(route('central.password.request'), false);
});

it('sends a reset link to a known e-mail only, without revealing which', function (): void {
    Notification::fake();
    Owner::factory()->create(['email' => 'owner@example.com']);

    $this->post($this->centralUrl('/forgot-password'), ['email' => 'owner@example.com'])
        ->assertRedirect($this->centralUrl('/forgot-password'))
        ->assertSessionHas('status')
        ->assertSessionHas('email', 'owner@example.com');

    $this->post($this->centralUrl('/forgot-password'), ['email' => 'nobody@example.com'])->assertSessionHas('status');

    Notification::assertSentOnDemandTimes(StoreLoginPasswordResetNotification::class, 1);
    Notification::assertSentOnDemand(StoreLoginPasswordResetNotification::class, fn ($notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'owner@example.com');

    $this->post($this->centralUrl('/forgot-password'), ['email' => 'bad'])->assertSessionHasErrors('email');
});

it('resets the password with a valid token and rejects an invalid one', function (): void {
    Notification::fake();
    $owner = Owner::factory()->create(['email' => 'owner@example.com']);
    $token = null;

    $this->post($this->centralUrl('/forgot-password'), ['email' => 'owner@example.com']);
    Notification::assertSentOnDemand(StoreLoginPasswordResetNotification::class, function (StoreLoginPasswordResetNotification $notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });

    $this->get($this->centralUrl('/reset-password/'.str_repeat('x', 64).'?email=owner@example.com&lang=en'))
        ->assertOk()
        ->assertSee(__('content::site.auth.invalid_alert', [], 'en'));

    $this->get($this->centralUrl("/reset-password/{$token}?email=owner@example.com"))->assertOk()->assertSee($token);

    $this->post($this->centralUrl('/reset-password'), ['token' => $token, 'email' => 'owner@example.com', 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertSessionHasErrors('password');

    $this->post($this->centralUrl('/reset-password'), ['token' => $token, 'email' => 'owner@example.com', 'password' => 'NewPassw0rd', 'password_confirmation' => 'NewPassw0rd'])
        ->assertRedirect($this->centralUrl('/login'));

    expect(Hash::check('NewPassw0rd', (string) $owner->refresh()->password))->toBeTrue();

    $this->post($this->centralUrl('/reset-password'), ['token' => $token, 'email' => 'owner@example.com', 'password' => 'Another1pass', 'password_confirmation' => 'Another1pass'])
        ->assertRedirect();
    expect(Hash::check('NewPassw0rd', (string) $owner->refresh()->password))->toBeTrue();
});

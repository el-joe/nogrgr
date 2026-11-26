<?php

use App\Modules\Identity\Notifications\CustomerEmailCodeNotification;
use Illuminate\Support\Facades\Notification;

/*
| SF-ECO-025c: storefront customer e-mail confirmation with a 6-digit code (sent on open,
| resend cool-down, wrong codes, verified → redirect), and the account banner.
*/

beforeEach(function (): void {
    Notification::fake();
    $this->tenant = $this->createTenant('acme');
    $this->customer = $this->createCustomer($this->tenant, ['email' => 'sara@example.test', 'email_verified_at' => null]);
    $this->url = fn (string $path): string => $this->tenantUrl($this->tenant, $path);
});

it('sends a code on open and verifies it', function (): void {
    $this->actingAsCustomer($this->customer, $this->tenant)
        ->get(($this->url)('/account/verify-email'))
        ->assertOk()
        ->assertSee('data-verify-digit', false)
        ->assertSee('s•••••@example.test');

    $code = null;
    Notification::assertSentTo($this->customer, CustomerEmailCodeNotification::class, function (CustomerEmailCodeNotification $notification) use (&$code): bool {
        $code = $notification->code;

        return strlen($notification->code) === 6;
    });

    $this->actingAsCustomer($this->customer, $this->tenant)
        ->postJson(($this->url)('/account/verify-email'), ['code' => $code === '000000' ? '111111' : '000000'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    $this->actingAsCustomer($this->customer, $this->tenant)
        ->postJson(($this->url)('/account/verify-email'), ['digits' => str_split((string) $code)])
        ->assertOk()
        ->assertJson(['success' => true, 'redirect' => ($this->url)('/account/profile')]);

    expect($this->tenant->run(fn () => $this->customer->fresh()->email_verified_at))->not->toBeNull();
});

it('throttles resending and shows the banner until verified', function (): void {
    $this->actingAsCustomer($this->customer, $this->tenant)
        ->get(($this->url)('/account/profile'))
        ->assertOk()
        ->assertSee('data-verify-banner', false);

    $this->actingAsCustomer($this->customer, $this->tenant)->postJson(($this->url)('/account/verify-email/resend'))->assertOk();
    $this->actingAsCustomer($this->customer, $this->tenant)->postJson(($this->url)('/account/verify-email/resend'))->assertOk()->assertJsonPath('data.resend_in', fn ($seconds) => $seconds > 0);

    Notification::assertSentToTimes($this->customer, CustomerEmailCodeNotification::class, 1);
});

it('locks the code after five wrong tries', function (): void {
    $this->actingAsCustomer($this->customer, $this->tenant)->postJson(($this->url)('/account/verify-email/resend'))->assertOk();

    $code = null;
    Notification::assertSentTo($this->customer, CustomerEmailCodeNotification::class, function ($notification) use (&$code): bool {
        $code = $notification->code;

        return true;
    });

    foreach (range(1, 5) as $try) {
        $this->actingAsCustomer($this->customer, $this->tenant)->postJson(($this->url)('/account/verify-email'), ['code' => $code === '123456' ? '654321' : '123456']);
    }

    $this->actingAsCustomer($this->customer, $this->tenant)
        ->postJson(($this->url)('/account/verify-email'), ['code' => $code])
        ->assertUnprocessable()
        ->assertJsonPath('errors.code.0', __st('auth.code_locked'));
});

it('sends verified customers and guests on', function (): void {
    $verified = $this->createCustomer($this->tenant, ['email_verified_at' => now()]);

    $this->actingAsCustomer($verified, $this->tenant)->get(($this->url)('/account/verify-email'))->assertRedirect(($this->url)('/account/profile'));
    $this->app['auth']->forgetGuards();
    $this->get(($this->url)('/account/verify-email'))->assertRedirect();
});

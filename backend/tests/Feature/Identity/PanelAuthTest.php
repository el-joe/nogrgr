<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\Affiliate;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use App\Modules\Identity\Services\SessionAuthService;
use App\Modules\Platform\Enums\AuditAction;
use App\Modules\Platform\Models\AuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/*
| Admin ({central}/admin/login) and affiliate ({central}/affiliate/login) sign-in,
| sign-out and password reset (QA-P0-003).
*/

dataset('panels', [
    'admin' => ['admin', Admin::class],
    'affiliate' => ['affiliate', Affiliate::class],
]);

/**
 * @param  class-string<Admin|Affiliate>  $model
 * @param  array<string, mixed>  $attributes
 */
function panelUser(string $surface, string $model, array $attributes = []): Admin|Affiliate
{
    test()->seed(CentralPermissionSeeder::class);
    $user = $model::factory()->create(['email' => $surface.'@nogrgr.test', ...$attributes]);
    $user->assignRole($surface === 'admin' ? 'super-admin' : 'affiliate');

    return $user->fresh();
}

it('renders the sign-in form posting to the login route with a forgot-password link', function (string $surface): void {
    $this->get($this->centralUrl($surface.'/login'))
        ->assertOk()
        ->assertSee('action="'.$this->centralUrl($surface.'/login').'"', false)
        ->assertSee('href="'.$this->centralUrl($surface.'/forgot-password').'"', false)
        ->assertDontSee(__('identity::auth.google'));
})->with('panels');

it('signs in, remembers the user and audits the login', function (string $surface, string $model): void {
    $user = panelUser($surface, $model);

    $this->post($this->centralUrl($surface.'/login'), ['email' => strtoupper($surface).'@NOGRGR.test', 'password' => 'password', 'remember' => '1'])
        ->assertRedirect(route($surface.'.dashboard'));

    expect(Auth::guard($surface)->id())->toBe($user->id)
        ->and($user->fresh()->last_login_at)->not->toBeNull()
        ->and($user->fresh()->getRememberToken())->not->toBeNull()
        ->and(AuditEvent::withoutTenantScope()->where('action', AuditAction::Login->value)->where('guard', $surface)->exists())->toBeTrue();
})->with('panels');

it('rejects a wrong password with the generic message and audits the failure', function (string $surface, string $model): void {
    panelUser($surface, $model);

    $this->from($this->centralUrl($surface.'/login'))
        ->post($this->centralUrl($surface.'/login'), ['email' => $surface.'@nogrgr.test', 'password' => 'wrong'])
        ->assertRedirect($this->centralUrl($surface.'/login'))
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->from($this->centralUrl($surface.'/login'))
        ->post($this->centralUrl($surface.'/login'), ['email' => 'nobody@nogrgr.test', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $failure = AuditEvent::withoutTenantScope()->where('action', AuditAction::LoginFailed->value)->latest('id')->firstOrFail();

    expect(Auth::guard($surface)->check())->toBeFalse()
        ->and($failure->changes)->toMatchArray(['guard' => $surface, 'reason' => 'invalid_credentials']);
})->with('panels');

it('validates the sign-in form', function (string $surface): void {
    $this->from($this->centralUrl($surface.'/login'))
        ->post($this->centralUrl($surface.'/login'), ['email' => 'not-an-email'])
        ->assertSessionHasErrors(['email', 'password']);
})->with('panels');

it('refuses an inactive account once the password matched', function (string $surface, string $model): void {
    panelUser($surface, $model, ['status' => 'suspended']);

    $this->from($this->centralUrl($surface.'/login'))
        ->post($this->centralUrl($surface.'/login'), ['email' => $surface.'@nogrgr.test', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('identity::auth.inactive')]);

    expect(Auth::guard($surface)->check())->toBeFalse();
})->with('panels');

it('throttles after five failed attempts per email and IP', function (string $surface, string $model): void {
    panelUser($surface, $model);

    foreach (range(1, SessionAuthService::MAX_ATTEMPTS) as $attempt) {
        $this->post($this->centralUrl($surface.'/login'), ['email' => $surface.'@nogrgr.test', 'password' => 'wrong'.$attempt]);
    }

    $this->postJson($this->centralUrl($surface.'/login'), ['email' => $surface.'@nogrgr.test', 'password' => 'password'])
        ->assertStatus(429)
        ->assertJsonValidationErrors('email');

    expect(Auth::guard($surface)->check())->toBeFalse()
        ->and(AuditEvent::withoutTenantScope()->where('action', AuditAction::LoginFailed->value)->get()->pluck('changes.reason'))->toContain('throttled');
})->with('panels');

it('signs out from the user menu and audits it', function (string $surface, string $model): void {
    $user = panelUser($surface, $model);

    $this->actingAs($user, $surface)
        ->get(route($surface.'.dashboard'))
        ->assertOk()
        ->assertSee('action="'.$this->centralUrl($surface.'/logout').'"', false);

    $this->actingAs($user, $surface)
        ->post($this->centralUrl($surface.'/logout'))
        ->assertRedirect($this->centralUrl($surface.'/login'));

    expect(Auth::guard($surface)->check())->toBeFalse()
        ->and(AuditEvent::withoutTenantScope()->where('action', AuditAction::Logout->value)->where('actor_id', $user->id)->exists())->toBeTrue();
})->with('panels');

it('keeps the other central guard signed in on logout', function (): void {
    $admin = panelUser('admin', Admin::class);
    $affiliate = Affiliate::factory()->create()->fresh();

    $this->actingAs($admin, 'admin')->actingAs($affiliate, 'affiliate')
        ->post($this->centralUrl('affiliate/logout'))
        ->assertRedirect($this->centralUrl('affiliate/login'));

    expect(Auth::guard('admin')->check())->toBeTrue();
});

it('requires authentication to sign out', function (string $surface): void {
    $this->post($this->centralUrl($surface.'/logout'))->assertRedirect($this->centralUrl($surface.'/login'));
})->with('panels');

it('resets a forgotten password through the mailed link', function (string $surface, string $model): void {
    Notification::fake();
    $user = panelUser($surface, $model);

    $this->get($this->centralUrl($surface.'/forgot-password'))->assertOk()->assertSee(__('identity::auth.reset.send_link'));

    $this->from($this->centralUrl($surface.'/forgot-password'))
        ->post($this->centralUrl($surface.'/forgot-password'), ['email' => $surface.'@nogrgr.test'])
        ->assertRedirect($this->centralUrl($surface.'/forgot-password'))
        ->assertSessionHas('status', __('identity::auth.reset.link_sent'));

    $token = null;
    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token, $surface): bool {
        $token = basename((string) parse_url($notification->url, PHP_URL_PATH));

        return str_starts_with($notification->url, $this->centralUrl($surface.'/reset-password/'))
            && $notification->via(new stdClass) === ['mail'];
    });

    $this->get($this->centralUrl($surface.'/reset-password/'.$token.'?email='.$surface.'@nogrgr.test'))
        ->assertOk()
        ->assertSee('value="'.$token.'"', false);

    $this->post($this->centralUrl($surface.'/reset-password'), [
        'token' => $token,
        'email' => $surface.'@nogrgr.test',
        'password' => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ])->assertRedirect($this->centralUrl($surface.'/login'))->assertSessionHas('status', __('identity::auth.reset.done'));

    expect(Hash::check('new-password-1', (string) $user->fresh()->password))->toBeTrue()
        ->and(AuditEvent::withoutTenantScope()->where('action', AuditAction::PasswordReset->value)->exists())->toBeTrue();
})->with('panels');

it('does not reveal unknown emails and rejects bad reset tokens', function (string $surface, string $model): void {
    Notification::fake();
    panelUser($surface, $model);

    $this->from($this->centralUrl($surface.'/forgot-password'))
        ->post($this->centralUrl($surface.'/forgot-password'), ['email' => 'nobody@nogrgr.test'])
        ->assertSessionHas('status', __('identity::auth.reset.link_sent'));

    Notification::assertNothingSent();

    $this->from($this->centralUrl($surface.'/reset-password/bad'))
        ->post($this->centralUrl($surface.'/reset-password'), ['token' => 'bad', 'email' => $surface.'@nogrgr.test', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
        ->assertSessionHasErrors(['email' => __('passwords.token')]);

    $this->from($this->centralUrl($surface.'/reset-password/bad'))
        ->post($this->centralUrl($surface.'/reset-password'), ['token' => 'bad', 'email' => 'nobody@nogrgr.test', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
        ->assertSessionHasErrors(['email' => __('passwords.token')]);

    $this->from($this->centralUrl($surface.'/reset-password/bad'))
        ->post($this->centralUrl($surface.'/reset-password'), ['token' => 'bad', 'email' => $surface.'@nogrgr.test', 'password' => 'short', 'password_confirmation' => 'other'])
        ->assertSessionHasErrors('password');
})->with('panels');

it('keeps admin and affiliate reset tokens apart for a shared email', function (): void {
    Notification::fake();
    $admin = Admin::factory()->create(['email' => 'shared@nogrgr.test']);
    $affiliate = Affiliate::factory()->create(['email' => 'shared@nogrgr.test']);

    $this->post($this->centralUrl('admin/forgot-password'), ['email' => 'shared@nogrgr.test']);
    $this->post($this->centralUrl('affiliate/forgot-password'), ['email' => 'shared@nogrgr.test']);

    Notification::assertSentTo($admin, ResetPasswordNotification::class);
    Notification::assertSentTo($affiliate, ResetPasswordNotification::class);
});

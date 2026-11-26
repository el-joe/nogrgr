<?php

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Notifications\Mail\StoreTestMail;
use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Models\StoreSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/*
| VEN-081 Vendor › Settings › Mail (SMTP): permissions, validation, save (password stored
| encrypted, never shown, kept when left empty), "Send test email" with the result, back to
| platform mail, and the store's e-mails going through its own SMTP (StoreAwareMailManager).
*/

beforeEach(function (): void {
    $this->tenant = $this->createTenant('mail-'.Str::lower(Str::random(6)), ['name' => 'Megz']);
    $this->owner = $this->createVendorOwner($this->tenant, ['email' => 'owner-'.Str::lower(Str::random(6)).'@megz.test']);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/settings/mail'.$path);
    $this->stored = fn (): mixed => $this->tenant->run(fn () => StoreSetting::query()->where('key', StoreSettingKey::MailSmtp->value)->first()?->value);
});

function storeMailPayload(array $overrides = []): array
{
    return array_merge([
        'host' => 'smtp.zoho.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'care@megz.sa',
        'password' => 'app-secret-123',
        'from_address' => 'care@megz.sa',
        'from_name' => 'Megz Store',
    ], $overrides);
}

it('sends guests to the login and guards every endpoint with settings.mail permissions', function (): void {
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    [$none, $viewer] = $this->tenant->run(function (): array {
        $none = VendorStaff::factory()->create();
        $none->givePermissionTo(['dashboard.view']);
        $viewer = VendorStaff::factory()->create();
        $viewer->givePermissionTo(['dashboard.view', 'settings.mail.view']);

        return [$none, $viewer];
    });

    $this->actingAsVendor($none, $this->tenant);
    $this->getJson(($this->url)())->assertForbidden();

    $this->actingAsVendor($viewer, $this->tenant);
    $this->get(($this->url)())->assertOk()->assertSee(__('notifications::vendor_mail.status.platform'))->assertDontSee(__('notifications::vendor_mail.send_test'));
    $this->putJson(($this->url)(), storeMailPayload())->assertForbidden();
    $this->deleteJson(($this->url)())->assertForbidden();
    $this->postJson(($this->url)('/test'), storeMailPayload(['test_email' => 'a@b.test']))->assertForbidden();
});

it('validates and saves the SMTP server with an encrypted password that is never shown', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->putJson(($this->url)(), storeMailPayload(['host' => 'not a host', 'port' => 70000, 'encryption' => 'starttls', 'from_address' => 'nope']))
        ->assertUnprocessable()->assertJsonValidationErrors(['host', 'port', 'encryption', 'from_address']);

    $this->putJson(($this->url)(), storeMailPayload())->assertOk();
    $stored = ($this->stored)();
    expect($stored['host'])->toBe('smtp.zoho.com')
        ->and($stored['password'])->not->toBe('app-secret-123')
        ->and(Crypt::decryptString($stored['password']))->toBe('app-secret-123');

    $this->get(($this->url)())->assertOk()
        ->assertSee(__('notifications::vendor_mail.status.own', ['address' => 'care@megz.sa', 'host' => 'smtp.zoho.com']))
        ->assertSee(__('notifications::vendor_mail.fields.password_saved'))
        ->assertDontSee('app-secret-123');

    // An empty password keeps the saved one.
    $this->putJson(($this->url)(), storeMailPayload(['password' => '', 'port' => 465, 'encryption' => 'ssl']))->assertOk();
    $stored = ($this->stored)();
    expect($stored['port'])->toBe(465)->and(Crypt::decryptString($stored['password']))->toBe('app-secret-123');

    $this->deleteJson(($this->url)())->assertOk();
    expect(($this->stored)())->toBeNull();
    $this->get(($this->url)())->assertOk()->assertSee(__('notifications::vendor_mail.status.platform'));
});

it('sends a test e-mail with the values on the page and reports the result', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->postJson(($this->url)('/test'), storeMailPayload())->assertUnprocessable()->assertJsonValidationErrors(['test_email']);

    Mail::fake();
    $this->postJson(($this->url)('/test'), storeMailPayload(['test_email' => 'mostafa@megz.test']))
        ->assertOk()->assertJsonPath('data.delivered', true)
        ->assertJsonPath('data.message', fn (string $message) => str_contains($message, 'mostafa@megz.test'));
    Mail::assertSent(StoreTestMail::class, fn (StoreTestMail $mail): bool => $mail->hasTo('mostafa@megz.test') && $mail->storeName === 'Megz');
    // Testing does not save anything.
    expect(($this->stored)())->toBeNull();
});

it('reports an SMTP failure of the test e-mail', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->postJson(($this->url)('/test'), storeMailPayload(['host' => '127.0.0.1', 'port' => 1, 'encryption' => 'none', 'test_email' => 'mostafa@megz.test']))
        ->assertUnprocessable()
        ->assertJsonPath('data.delivered', false)
        ->assertJsonPath('message', fn (string $message) => str_starts_with($message, __('notifications::vendor_mail.test_failed', ['error' => ''])));
});

it('sends the store e-mails through the store SMTP and other stores through the platform mail', function (): void {
    $other = $this->createTenant('mail-o-'.Str::lower(Str::random(6)));
    $this->actingAsVendor($this->owner, $this->tenant);
    $this->putJson(($this->url)(), storeMailPayload())->assertOk();

    $transport = fn () => app('mail.manager')->mailer()->getSymfonyTransport();

    $mine = $this->tenant->run(fn () => [$transport(), app('mail.manager')->mailer()]);
    expect($mine[0])->toBeInstanceOf(EsmtpTransport::class)
        ->and($mine[0]->getStream()->getHost())->toBe('smtp.zoho.com')
        ->and($mine[0]->getStream()->getPort())->toBe(587)
        ->and($mine[0]->getUsername())->toBe('care@megz.sa')
        ->and($mine[0]->getPassword())->toBe('app-secret-123');

    $theirs = $other->run(fn () => $transport());
    expect($theirs instanceof EsmtpTransport && $theirs->getStream()->getHost() === 'smtp.zoho.com')->toBeFalse();

    $this->endTenancy();
    $central = $transport();
    expect($central instanceof EsmtpTransport && $central->getStream()->getHost() === 'smtp.zoho.com')->toBeFalse();
});

<?php

use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Models\StoreSetting;
use Illuminate\Support\Str;

/*
| RQA-VEN-012: Settings › Mail (SMTP) saves with every server field empty (= Nogrgr mail,
| clears the store server); host / from address are only required for an own server.
*/

beforeEach(function (): void {
    $this->tenant = $this->createTenant('mailf-'.Str::lower(Str::random(6)), ['name' => 'Megz']);
    $this->owner = $this->createVendorOwner($this->tenant, ['email' => 'owner-'.Str::lower(Str::random(6)).'@megz.test']);
    $this->url = $this->tenantUrl($this->tenant, 'admin/settings/mail');
    $this->stored = fn (): mixed => $this->tenant->run(fn () => StoreSetting::query()->where('key', StoreSettingKey::MailSmtp->value)->first()?->value);
    $this->emptyForm = ['host' => '', 'port' => '587', 'encryption' => 'tls', 'username' => '', 'password' => '', 'from_name' => '', 'from_address' => ''];
});

it('saves the empty page as Nogrgr mail', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->putJson($this->url, $this->emptyForm)->assertOk()->assertJsonPath('message', __('notifications::vendor_mail.cleared'));
    expect(($this->stored)())->toBeNull();
});

it('clears a saved store server when the fields are emptied', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);
    $this->putJson($this->url, ['host' => 'smtp.zoho.com', 'port' => 587, 'encryption' => 'tls', 'username' => 'care@megz.sa', 'password' => 'secret-1', 'from_address' => 'care@megz.sa'])->assertOk();
    expect(($this->stored)())->toBeArray();

    $this->putJson($this->url, $this->emptyForm)->assertOk();
    expect(($this->stored)())->toBeNull();
    $this->get($this->url)->assertOk()->assertSee(__('notifications::vendor_mail.status.platform'));
});

it('still requires host and from address once any server field is filled', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->putJson($this->url, array_merge($this->emptyForm, ['username' => 'care@megz.sa']))
        ->assertUnprocessable()->assertJsonValidationErrors(['host', 'from_address']);
});

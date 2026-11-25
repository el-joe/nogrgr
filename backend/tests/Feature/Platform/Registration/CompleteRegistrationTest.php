<?php

use App\Modules\Platform\Enums\RegistrationStatus;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Jobs\ProvisionTenant;
use App\Modules\Platform\Models\DomainRequest;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Models\VendorRegistration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

require_once __DIR__.'/../Vendors/VendorHelpers.php';

/*
| WEB-018 / 018a: the signed completion link (invalid / expired / used states), the
| account step, the store step and the live availability check.
*/

/**
 * @param  array<string, mixed>  $attributes
 * @return array{0: VendorRegistration, 1: string}
 */
function completionRegistration(array $attributes = []): array
{
    $token = Str::random(48);
    $registration = VendorRegistration::factory()->create(array_merge([
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addDays(3),
        'owner_name' => null,
        'store_name' => null,
        'slug' => null,
    ], $attributes));

    return [$registration, $token];
}

function completionLink(string $token, ?Carbon $expiresAt = null): string
{
    return URL::temporarySignedRoute('central.register.complete', $expiresAt ?? now()->addDays(3), ['token' => $token]);
}

/**
 * @return array{0: VendorRegistration, 1: string}
 */
function accountReadyRegistration(array $attributes = []): array
{
    return completionRegistration(array_merge([
        'status' => RegistrationStatus::Incomplete,
        'owner_name' => 'Mohamed Saleh',
        'password' => 'secret-pass-123',
    ], $attributes));
}

/**
 * @return array<string, mixed>
 */
function storePayload(string $token, array $overrides = []): array
{
    return array_merge([
        'token' => $token,
        'store_name' => 'Megz Kids',
        'slug' => 'megzkids-'.Str::lower(Str::random(5)),
        'domain_type' => 'subdomain',
        'custom_domain' => '',
        'profit_percent' => '25.5',
    ], $overrides);
}

describe('completion link', function (): void {
    it('renders the invalid state for a tampered signature', function (): void {
        [, $token] = completionRegistration();

        $this->get(completionLink($token).'x')
            ->assertOk()
            ->assertSee('data-link-state="invalid"', false)
            ->assertSee(__('platform::register.link.invalid.alert'));
    });

    it('renders the invalid state for an unsigned link', function (): void {
        [, $token] = completionRegistration();

        $this->get(route('central.register.complete', ['token' => $token]))
            ->assertOk()
            ->assertSee('data-link-state="invalid"', false);
    });

    it('renders the invalid state for an unknown token', function (): void {
        $this->get(completionLink(Str::random(48)))
            ->assertOk()
            ->assertSee('data-link-state="invalid"', false);
    });

    it('renders the expired state for an expired registration or link', function (): void {
        [, $token] = completionRegistration(['expires_at' => now()->subHour()]);

        $this->get(completionLink($token, now()->subHour()))
            ->assertOk()
            ->assertSee('data-link-state="expired"', false)
            ->assertSee(__('platform::register.link.expired.alert'));
    });

    it('renders the used state for a completed registration', function (): void {
        [, $token] = completionRegistration(['status' => RegistrationStatus::Completed, 'slug' => 'megz-used']);

        $this->get(completionLink($token))
            ->assertOk()
            ->assertSee('data-link-state="used"', false)
            ->assertSee(route('central.tenant.login', ['store' => 'megz-used']), false);
    });

    it('marks the e-mail verified and shows the account step on first visit', function (): void {
        [$registration, $token] = completionRegistration(['phone' => '+966551234567']);

        $this->get(completionLink($token).'&lang=ar')
            ->assertOk()
            ->assertSee(__('platform::register.account.title', [], 'ar'))
            ->assertSee($registration->email)
            ->assertSee('+966551234567')
            ->assertSee('dir="rtl"', false)
            ->assertSee('data-page="register-account"', false);

        $registration->refresh();
        expect($registration->status)->toBe(RegistrationStatus::Incomplete)
            ->and($registration->email_verified_at)->not->toBeNull();
    });

    it('shows the store step once the account is saved', function (): void {
        [, $token] = accountReadyRegistration();

        $this->get(completionLink($token))
            ->assertOk()
            ->assertSee(__('platform::register.store.title'))
            ->assertSee('data-page="register-store"', false)
            ->assertDontSee('categor', false);
    });
});

describe('account step', function (): void {
    it('validates name, password confirmation and terms', function (): void {
        [$registration, $token] = completionRegistration();
        $link = completionLink($token);

        $this->from($link)->post(route('central.register.complete.account'), [
            'token' => $token,
            'name' => '',
            'password' => 'short',
            'password_confirmation' => 'other',
        ])->assertRedirect($link)->assertSessionHasErrors(['name', 'password', 'terms']);

        $this->from($link)->post(route('central.register.complete.account'), [
            'token' => $token,
            'name' => str_repeat('a', 121),
            'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-124',
            'terms' => '1',
        ])->assertSessionHasErrors(['name', 'password']);

        expect($registration->refresh()->password)->toBeNull();
    });

    it('saves the owner name and a hashed password, then shows the store step', function (): void {
        [$registration, $token] = completionRegistration(['status' => RegistrationStatus::Incomplete]);

        $response = $this->post(route('central.register.complete.account'), [
            'token' => $token,
            'name' => 'Mohamed Saleh',
            'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-123',
            'terms' => '1',
        ]);

        $response->assertRedirect()->assertSessionHas('success');
        $registration->refresh();

        expect($registration->owner_name)->toBe('Mohamed Saleh')
            ->and($registration->getRawOriginal('password'))->not->toBe('secret-pass-123')
            ->and(Hash::check('secret-pass-123', $registration->getRawOriginal('password')))->toBeTrue();

        $this->get($response->headers->get('Location'))->assertOk()->assertSee(__('platform::register.store.title'));
    });

    it('sends an expired registration back to the expired state', function (): void {
        [$registration, $token] = completionRegistration(['expires_at' => now()->subMinute()]);

        $response = $this->post(route('central.register.complete.account'), [
            'token' => $token,
            'name' => 'Mohamed Saleh',
            'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-123',
            'terms' => '1',
        ]);

        $response->assertRedirect();
        expect($registration->refresh()->owner_name)->toBeNull();
        $this->get($response->headers->get('Location'))->assertSee('data-link-state="expired"', false);
    });

    it('sends an unknown token to the invalid state', function (): void {
        $response = $this->post(route('central.register.complete.account'), [
            'token' => Str::random(48),
            'name' => 'Mohamed Saleh',
            'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-123',
            'terms' => '1',
        ]);

        $this->get($response->assertRedirect()->headers->get('Location'))->assertSee('data-link-state="invalid"', false);
    });
});

describe('store step', function (): void {
    it('requires the store fields and a valid profit percent', function (): void {
        [, $token] = accountReadyRegistration();

        $this->post(route('central.register.complete.store'), ['token' => $token, 'domain_type' => 'subdomain'])
            ->assertSessionHasErrors(['store_name', 'slug', 'profit_percent']);

        foreach (['100.5', '-1', '12.345', 'abc'] as $profit) {
            $this->post(route('central.register.complete.store'), storePayload($token, ['profit_percent' => $profit]))
                ->assertSessionHasErrors('profit_percent');
        }

        $this->post(route('central.register.complete.store'), storePayload($token, ['domain_type' => 'other']))
            ->assertSessionHasErrors('domain_type');
    });

    it('refuses a slug taken by a store or reserved', function (): void {
        [, $token] = accountReadyRegistration();
        vendorsTenant(['slug' => 'megz']);

        $this->post(route('central.register.complete.store'), storePayload($token, ['slug' => 'megz']))
            ->assertSessionHasErrors(['slug' => __('platform::register.store.taken_try', [
                'host' => 'megz.'.config('tenancy.base_domain'),
                'suggestions' => 'megz-store '.__('platform::register.store.or').' megz-shop',
            ])]);

        $this->post(route('central.register.complete.store'), storePayload($token, ['slug' => 'login']))
            ->assertSessionHasErrors('slug');

        $this->post(route('central.register.complete.store'), storePayload($token, ['slug' => '-bad-']))
            ->assertSessionHasErrors('slug');

        expect(Tenant::query()->count())->toBe(1);
    });

    it('validates the custom domain', function (): void {
        [, $token] = accountReadyRegistration();
        $tenant = vendorsTenant();
        DomainRequest::query()->create(['tenant_id' => $tenant->id, 'host' => 'taken-shop.com', 'verification_token' => Str::random(32)]);

        $this->post(route('central.register.complete.store'), storePayload($token, ['domain_type' => 'custom', 'custom_domain' => '']))
            ->assertSessionHasErrors('custom_domain');
        $this->post(route('central.register.complete.store'), storePayload($token, ['domain_type' => 'custom', 'custom_domain' => 'https://Taken-Shop.com/']))
            ->assertSessionHasErrors(['custom_domain' => __('platform::register.store.domain_taken', ['host' => 'taken-shop.com'])]);
        $this->post(route('central.register.complete.store'), storePayload($token, ['domain_type' => 'custom', 'custom_domain' => 'not a domain']))
            ->assertSessionHasErrors('custom_domain');
        $this->post(route('central.register.complete.store'), storePayload($token, ['domain_type' => 'custom', 'custom_domain' => 'shop.'.config('tenancy.base_domain')]))
            ->assertSessionHasErrors('custom_domain');
    });

    it('provisions the store and redirects to the provisioning page', function (): void {
        Queue::fake();
        [$registration, $token] = accountReadyRegistration();

        $response = $this->post(route('central.register.complete.store'), storePayload($token, [
            'slug' => 'MegzKids',
            'domain_type' => 'custom',
            'custom_domain' => 'Shop.MegzKids.com',
        ]));

        $registration->refresh();
        $tenant = Tenant::query()->findOrFail($registration->tenant_id);

        expect($response->headers->get('Location'))->toContain('/register/provisioning/'.$registration->id)->toContain('signature=')
            ->and($registration->status)->toBe(RegistrationStatus::Completed)
            ->and($registration->slug)->toBe('megzkids')
            ->and($registration->custom_domain)->toBe('shop.megzkids.com')
            ->and($registration->profit_percent_bps)->toBe(2550)
            ->and($tenant->status)->toBe(TenantStatus::Pending)
            ->and($tenant->owner_name)->toBe('Mohamed Saleh');

        Queue::assertPushed(ProvisionTenant::class, fn (ProvisionTenant $job): bool => $job->tenantId === $tenant->id);
    });

    it('ignores the custom domain on the sub-domain option', function (): void {
        Queue::fake();
        [$registration, $token] = accountReadyRegistration();

        $this->post(route('central.register.complete.store'), storePayload($token, ['custom_domain' => 'ignored.com']))
            ->assertRedirect();

        expect($registration->refresh()->custom_domain)->toBeNull();
    });

    it('returns to the form with the message when the account step is missing', function (): void {
        Queue::fake();
        [, $token] = completionRegistration(['status' => RegistrationStatus::Incomplete]);
        $link = completionLink($token);

        $this->from($link)->post(route('central.register.complete.store'), storePayload($token))
            ->assertRedirect($link)
            ->assertSessionHas('error', __('platform::messages.account_step_missing'));

        Queue::assertNothingPushed();
    });

    it('sends an expired registration back to the expired state', function (): void {
        Queue::fake();
        [, $token] = accountReadyRegistration(['expires_at' => now()->subMinute()]);

        $response = $this->post(route('central.register.complete.store'), storePayload($token));

        $this->get($response->assertRedirect()->headers->get('Location'))->assertSee('data-link-state="expired"', false);
        Queue::assertNothingPushed();
    });
});

describe('availability', function (): void {
    it('reports a free, taken or invalid store address', function (): void {
        [, $token] = accountReadyRegistration();
        vendorsTenant(['slug' => 'megz']);
        $base = config('tenancy.base_domain');

        $this->getJson(route('central.register.availability', ['token' => $token, 'slug' => 'MegzKids']))
            ->assertOk()
            ->assertExactJson(['available' => true, 'message' => __('platform::register.store.available', ['host' => 'megzkids.'.$base])]);

        $this->getJson(route('central.register.availability', ['token' => $token, 'slug' => 'megz']))
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'megz-store'));

        $this->getJson(route('central.register.availability', ['token' => $token, 'slug' => 'login']))
            ->assertJsonPath('available', false);
    });

    it('reports custom domain availability and the DNS records', function (): void {
        [, $token] = accountReadyRegistration();
        $base = config('tenancy.base_domain');
        fakeDns(['shop.megzkids.com|CNAME' => ['megzkids.'.$base]]);

        $this->getJson(route('central.register.availability', ['token' => $token, 'domain' => 'shop.megzkids.com', 'check' => 'dns', 'for' => 'megzkids']))
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('subdomain', 'megzkids.'.$base)
            ->assertJsonPath('records.0.type', 'CNAME')
            ->assertJsonPath('records.0.found', true);

        $this->getJson(route('central.register.availability', ['token' => $token, 'domain' => 'shop.'.$base]))
            ->assertJsonPath('available', false);
    });

    it('refuses an unknown or closed registration and a missing query', function (): void {
        [, $token] = completionRegistration(['status' => RegistrationStatus::Completed]);

        $this->getJson(route('central.register.availability', ['token' => Str::random(48), 'slug' => 'megzkids']))->assertForbidden();
        $this->getJson(route('central.register.availability', ['token' => $token, 'slug' => 'megzkids']))->assertForbidden();
        $this->getJson(route('central.register.availability', ['token' => $token]))->assertUnprocessable();
    });
});

<?php

use App\Modules\Billing\Enums\AddonPurchaseStatus;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Models\AddonPurchase;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Catalog\Enums\TenantChangeType;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Requests\TenantChangeRequest;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Models\Language;
use App\Modules\Platform\Enums\SetupStep;
use App\Modules\Platform\Enums\SetupStepState;
use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Models\StoreSetting;
use App\Modules\Platform\Services\OnboardingService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/*
| VEN-007 setup wizard (82:99932): countries (direct first time, change request after),
| languages (ar / en on, buying a language through the hosted checkout), theme (available
| after themes), launch; the import-products checklist step follows store_products.
*/

beforeEach(function (): void {
    Notification::fake();
    $this->sa = Country::query()->where('iso2', 'SA')->first() ?? Country::factory()->iso('SA')->create();
    $this->ae = Country::query()->where('iso2', 'AE')->first() ?? Country::factory()->iso('AE')->create();
    $this->tenant = $this->createTenant('acme', ['selling_country_ids' => null]);
    $this->owner = $this->createVendorOwner($this->tenant);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/onboarding/setup'.$path);
    $this->as = fn () => $this->actingAsVendor($this->owner, $this->tenant);
});

it('guards the wizard by login and permission', function (): void {
    $this->get(($this->url)(''))->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    $staff = $this->tenant->run(function (): VendorStaff {
        $staff = VendorStaff::factory()->create();
        $staff->givePermissionTo('dashboard.view');

        return $staff;
    });

    $this->actingAsVendor($staff, $this->tenant)->get(($this->url)(''))->assertForbidden();
    $this->actingAsVendor($staff, $this->tenant)->post(($this->url)('/launch'))->assertForbidden();
});

it('saves the first countries directly and validates the default country', function (): void {
    ($this->as)()->get(($this->url)(''))->assertOk()->assertSee(__('platform::setup.countries.title'))->assertSee('Saudi Arabia');

    ($this->as)()->post(($this->url)('/countries'), ['country_ids' => [], 'default_country_id' => $this->sa->id])->assertSessionHasErrors('country_ids');
    ($this->as)()->post(($this->url)('/countries'), ['country_ids' => [$this->sa->id], 'default_country_id' => $this->ae->id])->assertSessionHasErrors('default_country_id');

    ($this->as)()->post(($this->url)('/countries'), ['country_ids' => [$this->sa->id, $this->ae->id], 'default_country_id' => $this->ae->id])
        ->assertRedirect(($this->url)('?step=languages'))
        ->assertSessionHas('success');

    $this->tenant->refresh();
    expect($this->tenant->selling_country_ids)->toBe([$this->sa->id, $this->ae->id])
        ->and($this->tenant->default_country_id)->toBe($this->ae->id)
        ->and(TenantChangeRequest::withoutTenantScope()->count())->toBe(0);
});

it('turns a later countries change into an admin change request', function (): void {
    $this->tenant->update(['selling_country_ids' => [$this->sa->id], 'default_country_id' => $this->sa->id]);

    ($this->as)()->get(($this->url)('?step=countries'))->assertOk()->assertSee(__('platform::setup.countries.approval_note'));

    ($this->as)()->post(($this->url)('/countries'), ['country_ids' => [$this->sa->id, $this->ae->id], 'default_country_id' => $this->sa->id])
        ->assertSessionHas('info', __('platform::setup.countries.requested'));

    $change = TenantChangeRequest::withoutTenantScope()->sole();
    expect($change->type)->toBe(TenantChangeType::Countries)
        ->and($change->requested_ids)->toBe([$this->sa->id, $this->ae->id])
        ->and($this->tenant->fresh()->selling_country_ids)->toBe([$this->sa->id]);

    // A second change while one is pending is refused.
    ($this->as)()->post(($this->url)('/countries'), ['country_ids' => [$this->ae->id], 'default_country_id' => $this->ae->id])->assertSessionHas('error');
});

it('shows ar / en on and buys a paid language through the hosted checkout', function (): void {
    $this->tenant->update(['selling_country_ids' => [$this->sa->id]]);
    $french = Language::query()->where('code', 'fr')->first() ?? Language::factory()->create(['code' => 'fr', 'name' => ['en' => 'French', 'ar' => 'الفرنسية'], 'native_name' => 'Français']);
    $french->update(['is_active' => true, 'price_minor' => 4900, 'country_ids' => null]);

    $gateway = PaymentGateway::factory()->create(['code' => PaymentGatewayCode::Stripe, 'is_active' => true, 'sort_order' => 0, 'fee_percent_bps' => 0, 'fee_fixed_minor' => 0]);
    $gateway->configs()->create(['purpose' => GatewayPurpose::VendorPayments, 'is_enabled' => true, 'mode' => GatewayMode::Test, 'credentials' => ['publishable_key' => 'pk_test_1', 'secret_key' => 'sk_test_1', 'webhook_secret' => 'whsec_test_secret']]);
    Http::fake(['https://api.stripe.com/v1/checkout/sessions*' => fn (HttpRequest $request) => $request->method() === 'POST'
        ? Http::response(['id' => 'cs_test_lang', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_lang'])
        : Http::response(['id' => 'cs_test_lang', 'status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => 'pi_lang', 'amount_total' => 4900, 'currency' => 'usd'])]);

    ($this->as)()->get(($this->url)('?step=languages'))->assertOk()
        ->assertSee(__('platform::setup.languages.free'))
        ->assertSee('French')
        ->assertSee(__('platform::setup.languages.buy', ['price' => '$49.00']));

    ($this->as)()->post(($this->url)('/languages/'.$french->id.'/purchase'), ['gateway' => 'tap'])->assertSessionHasErrors('gateway');
    ($this->as)()->post(($this->url)('/languages/999999/purchase'), ['gateway' => 'stripe'])->assertSessionHas('error');

    ($this->as)()->post(($this->url)('/languages/'.$french->id.'/purchase'), ['gateway' => 'stripe'])
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_lang');

    $purchase = AddonPurchase::query()->withoutGlobalScopes()->sole();
    $log = PaymentLog::query()->withoutGlobalScopes()->sole();
    expect($purchase->status)->toBe(AddonPurchaseStatus::PendingPayment)->and($log->amount_minor)->toBe(4900);

    ($this->as)()->get(($this->url)('/languages/payments/'.$log->id.'/result'))
        ->assertRedirect(($this->url)('?step=languages'))
        ->assertSessionHas('success');

    expect($log->fresh()->status)->toBe(PaymentLogStatus::Captured)
        ->and($purchase->fresh()->status)->toBe(AddonPurchaseStatus::Paid)
        ->and($log->fresh()->invoice->status)->toBe(InvoiceStatus::Paid);

    // Another store cannot read this payment.
    $beta = $this->createTenant('beta');
    $betaOwner = $this->createVendorOwner($beta);
    $this->actingAsVendor($betaOwner, $beta)->get($this->tenantUrl($beta, 'admin/onboarding/setup/languages/payments/'.$log->id.'/result'))->assertNotFound();
});

it('says themes come later and launches the store once countries are set', function (): void {
    ($this->as)()->get(($this->url)('?step=theme'))->assertOk()->assertSee(__('platform::onboarding.steps.theme.available_after'));

    ($this->as)()->post(($this->url)('/launch'))->assertRedirect(($this->url)('?step=countries'))->assertSessionHas('error');

    ($this->as)()->post(($this->url)('/countries'), ['country_ids' => [$this->sa->id], 'default_country_id' => $this->sa->id])->assertSessionHas('success');
    ($this->as)()->post(($this->url)('/launch'))->assertRedirect($this->tenantUrl($this->tenant, 'admin/dashboard'));

    expect($this->tenant->run(fn () => StoreSetting::query()->where('key', StoreSettingKey::LaunchedAt->value)->value('value')))->not->toBeNull();
    ($this->as)()->get(($this->url)('?step=launch'))->assertOk()->assertSee(__('platform::setup.launch.dashboard'));
});

it('computes the import-products checklist step from the store\'s products', function (): void {
    $state = fn (): SetupStepState => $this->tenant->run(fn (): SetupStepState => collect(app(OnboardingService::class)->progress($this->tenant)->steps)
        ->first(fn ($item): bool => $item->step === SetupStep::ImportProducts)->state);

    expect($state())->not->toBe(SetupStepState::Done);

    $central = Product::factory()->create();
    $this->tenant->run(fn () => StoreProduct::query()->create(['source' => StoreProductSource::Central, 'central_product_id' => $central->id, 'is_active' => true, 'imported_at' => now()]));

    expect($state())->toBe(SetupStepState::Done);
});

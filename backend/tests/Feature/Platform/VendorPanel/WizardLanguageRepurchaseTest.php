<?php

use App\Modules\Billing\Enums\AddonPurchaseStatus;
use App\Modules\Billing\Enums\AddonType;
use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Models\AddonPurchase;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Modules\Localization\Enums\TranslationRunStatus;
use App\Modules\Localization\Enums\TranslationRunTrigger;
use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Models\StoreTranslationRun;
use App\Modules\Localization\Services\LanguageService;
use App\Modules\Localization\Services\TenantLanguageService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/*
| RQA-VEN-001: a language the store already paid for (paid add-on purchase, or a queued /
| translating run) is never offered again — not in the setup wizard, not in Settings ›
| Languages — and both purchase endpoints refuse a second purchase server-side (422).
| The wizard shows it as "Paid — <run status>" and flashes success after the payment returns.
*/

beforeEach(function (): void {
    Notification::fake();
    $this->tenant = $this->createTenant('rqa-'.Str::lower(Str::random(6)), ['selling_country_ids' => null]);
    $this->tenant->run(fn () => app(TenantLanguageService::class)->enableCoreLanguages());
    $this->owner = $this->createVendorOwner($this->tenant, ['email' => 'owner-'.Str::lower(Str::random(6)).'@megz.test']);
    $this->wizard = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/onboarding/setup'.$path);
    $this->settings = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/settings/languages'.$path);
    $this->as = fn () => $this->actingAsVendor($this->owner, $this->tenant);
    $this->french = Language::query()->where('code', 'fr')->first() ?? Language::factory()->create(['code' => 'fr', 'name' => ['en' => 'French', 'ar' => 'الفرنسية'], 'native_name' => 'Français']);
    $this->french->update(['is_active' => true, 'price_minor' => 500, 'country_ids' => null]);

    $gateway = PaymentGateway::factory()->create(['code' => PaymentGatewayCode::Stripe, 'is_active' => true, 'sort_order' => 0, 'fee_percent_bps' => 0, 'fee_fixed_minor' => 0]);
    $gateway->configs()->create(['purpose' => GatewayPurpose::VendorPayments, 'is_enabled' => true, 'mode' => GatewayMode::Test, 'credentials' => ['publishable_key' => 'pk_test_1', 'secret_key' => 'sk_test_1', 'webhook_secret' => 'whsec_test_secret']]);
    Http::fake(['https://api.stripe.com/v1/checkout/sessions*' => fn (HttpRequest $request) => $request->method() === 'POST'
        ? Http::response(['id' => 'cs_test_rqa', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_rqa'])
        : Http::response(['id' => 'cs_test_rqa', 'status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => 'pi_rqa', 'amount_total' => 500, 'currency' => 'usd'])]);
});

it('stops offering a language once its purchase is paid and refuses a second purchase on both endpoints', function (): void {
    AddonPurchase::factory()->create(['tenant_id' => $this->tenant->id, 'type' => AddonType::Language, 'item_code' => 'fr', 'status' => AddonPurchaseStatus::Paid, 'amount_minor' => 500]);
    $message = __('localization::vendor_languages.errors.already_purchased');

    expect(app(LanguageService::class)->availableToPurchase($this->tenant)->pluck('code')->all())->not->toContain('fr');

    ($this->as)()->get(($this->wizard)('?step=languages'))->assertOk()
        ->assertSee('data-bought', false)
        ->assertSee(__('localization::vendor_languages.paid'))
        ->assertSee(TranslationRunStatus::Queued->label())
        ->assertDontSee(__('platform::setup.languages.buy', ['price' => '$5.00']));

    ($this->as)()->postJson(($this->wizard)('/languages/'.$this->french->id.'/purchase'), ['gateway' => 'stripe'])
        ->assertStatus(422)->assertJsonPath('message', $message);
    ($this->as)()->post(($this->wizard)('/languages/'.$this->french->id.'/purchase'), ['gateway' => 'stripe'])
        ->assertRedirect(($this->wizard)('?step=languages'))->assertSessionHas('error', $message);
    ($this->as)()->postJson(($this->settings)('/'.$this->french->id.'/purchase'), ['gateway' => 'stripe'])
        ->assertStatus(422)->assertJsonPath('message', $message);

    expect(AddonPurchase::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(1)
        ->and(PaymentLog::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(0);
});

it('shows the translating run of a bought language in the wizard and never offers it again', function (): void {
    StoreTranslationRun::query()->withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'language_id' => $this->french->id,
        'language_code' => 'fr',
        'trigger' => TranslationRunTrigger::Purchase,
        'status' => TranslationRunStatus::Translating,
        'queued_at' => now(),
    ]);

    ($this->as)()->get(($this->wizard)('?step=languages'))->assertOk()
        ->assertSee(__('localization::vendor_languages.paid'))
        ->assertSee(TranslationRunStatus::Translating->label())
        ->assertDontSee(__('platform::setup.languages.buy', ['price' => '$5.00']));

    ($this->as)()->postJson(($this->wizard)('/languages/'.$this->french->id.'/purchase'), ['gateway' => 'stripe'])
        ->assertStatus(422)->assertJsonPath('message', __('localization::vendor_languages.errors.already_purchased'));
});

it('flashes success on the wizard after the payment returns and shows the paid language', function (): void {
    ($this->as)()->post(($this->wizard)('/languages/'.$this->french->id.'/purchase'), ['gateway' => 'stripe'])
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_rqa');
    $log = PaymentLog::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->sole();

    ($this->as)()->followingRedirects()->get(($this->wizard)('/languages/payments/'.$log->id.'/result'))
        ->assertOk()
        ->assertSee(__('platform::setup.languages.paid', ['language' => 'French']))
        ->assertSee('data-bought', false)
        ->assertDontSee(__('platform::setup.languages.buy', ['price' => '$5.00']));

    expect($log->fresh()->status)->toBe(PaymentLogStatus::Captured);
});

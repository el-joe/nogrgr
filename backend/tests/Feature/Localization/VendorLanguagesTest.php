<?php

use App\Modules\Billing\Enums\AddonPurchaseStatus;
use App\Modules\Billing\Models\AddonPurchase;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Modules\Localization\Enums\TranslationRunStatus;
use App\Modules\Localization\Events\StoreTranslationRunQueued;
use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Models\StoreTranslationRun;
use App\Modules\Localization\Models\TenantLanguage;
use App\Modules\Localization\Services\TenantLanguageService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/*
| VEN-073 / 073a / 073b Vendor › Settings › Languages: permissions, default / on / order,
| buying a language through the shared invoice checkout (USD), the paid translation run
| queued on payment with its status + retry, purchases history, isolation.
*/

beforeEach(function (): void {
    Notification::fake();
    $this->tenant = $this->createTenant('lng-'.Str::lower(Str::random(6)), ['selling_country_ids' => null]);
    $this->tenant->run(fn () => app(TenantLanguageService::class)->enableCoreLanguages());
    $this->owner = $this->createVendorOwner($this->tenant, ['email' => 'owner-'.Str::lower(Str::random(6)).'@megz.test']);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/settings/languages'.$path);
    $this->as = fn () => $this->actingAsVendor($this->owner, $this->tenant);
    $this->french = Language::query()->where('code', 'fr')->first() ?? Language::factory()->create(['code' => 'fr', 'name' => ['en' => 'French', 'ar' => 'الفرنسية'], 'native_name' => 'Français']);
    $this->french->update(['is_active' => true, 'price_minor' => 4900, 'country_ids' => null]);
});

function stripeForLanguages(): void
{
    $gateway = PaymentGateway::factory()->create(['code' => PaymentGatewayCode::Stripe, 'is_active' => true, 'sort_order' => 0, 'fee_percent_bps' => 0, 'fee_fixed_minor' => 0]);
    $gateway->configs()->create(['purpose' => GatewayPurpose::VendorPayments, 'is_enabled' => true, 'mode' => GatewayMode::Test, 'credentials' => ['publishable_key' => 'pk_test_1', 'secret_key' => 'sk_test_1', 'webhook_secret' => 'whsec_test_secret']]);
    Http::fake(['https://api.stripe.com/v1/checkout/sessions*' => fn (HttpRequest $request) => $request->method() === 'POST'
        ? Http::response(['id' => 'cs_test_lng', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_lng'])
        : Http::response(['id' => 'cs_test_lng', 'status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => 'pi_lng', 'amount_total' => 4900, 'currency' => 'usd'])]);
}

it('guards the pages and actions by the settings.languages permissions', function (): void {
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    [$none, $viewer] = $this->tenant->run(function (): array {
        $none = VendorStaff::factory()->create();
        $none->givePermissionTo('dashboard.view');
        $viewer = VendorStaff::factory()->create();
        $viewer->givePermissionTo(['dashboard.view', 'settings.languages.view']);

        return [$none, $viewer];
    });

    $this->actingAsVendor($none, $this->tenant);
    $this->get(($this->url)())->assertForbidden();
    $this->getJson(($this->url)('/history'))->assertForbidden();

    $this->actingAsVendor($viewer, $this->tenant);
    $this->get(($this->url)())->assertOk()->assertSee('French')->assertSee(__('localization::vendor_languages.checkout.no_permission'));
    $this->putJson(($this->url)(), ['default' => 'ar'])->assertForbidden();
    $this->post(($this->url)('/'.$this->french->id.'/purchase'), ['gateway' => 'stripe'])->assertForbidden();
    $this->postJson(($this->url)('/fr/retry'))->assertForbidden();
});

it('saves the default language, switches and order, keeping ar / en on', function (): void {
    ($this->as)();
    $this->get(($this->url)())->assertOk()->assertSee(__('localization::vendor_languages.yours'))->assertSee('$49.00');

    $this->putJson(($this->url)(), [])->assertUnprocessable()->assertJsonValidationErrors('default');
    $this->putJson(($this->url)(), ['default' => 'fr', 'enabled' => ['ar', 'en']])->assertUnprocessable();

    $this->putJson(($this->url)(), ['default' => 'ar', 'enabled' => [], 'order' => ['ar', 'en']])->assertOk();

    $rows = $this->tenant->run(fn () => TenantLanguage::query()->orderBy('sort_order')->get());
    expect($rows->pluck('language_code')->all())->toBe(['ar', 'en'])
        ->and($rows->firstWhere('language_code', 'ar')->is_default)->toBeTrue()
        ->and($rows->every(fn ($row) => $row->is_enabled))->toBeTrue();
});

it('buys a language in USD, queues its translation run on payment and retries a failed run', function (): void {
    Event::fake([StoreTranslationRunQueued::class]);
    stripeForLanguages();
    ($this->as)();

    $this->post(($this->url)('/'.$this->french->id.'/purchase'), ['gateway' => 'tap'])->assertSessionHasErrors('gateway');
    $this->post(($this->url)('/999999/purchase'), ['gateway' => 'stripe'])->assertRedirect(($this->url)())->assertSessionHas('error');

    $this->post(($this->url)('/'.$this->french->id.'/purchase'), ['gateway' => 'stripe'])->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_lng');
    $purchase = AddonPurchase::query()->withoutGlobalScopes()->sole();
    $log = PaymentLog::query()->withoutGlobalScopes()->sole();
    expect($purchase->status)->toBe(AddonPurchaseStatus::PendingPayment)->and($purchase->currency)->toBe('USD')->and($log->amount_minor)->toBe(4900);
    $this->get(($this->url)())->assertSee(__('localization::vendor_languages.pending_payment'));

    $this->get(($this->url)('/payments/'.$log->id.'/result'))->assertRedirect(($this->url)())->assertSessionHas('success');

    $run = StoreTranslationRun::query()->withoutGlobalScopes()->sole();
    expect($purchase->fresh()->status)->toBe(AddonPurchaseStatus::Paid)
        ->and($run->status)->toBe(TranslationRunStatus::Queued)
        ->and($run->tenant_id)->toBe($this->tenant->id)
        ->and($run->language_code)->toBe('fr')
        ->and($run->addon_purchase_id)->toBe($purchase->id);
    Event::assertDispatched(StoreTranslationRunQueued::class, fn ($event): bool => $event->runId === $run->id);

    $this->get(($this->url)())->assertOk()->assertSee(__('localization::vendor_languages.run.queued', ['language' => 'French']))
        ->assertDontSee('data-purchasable="fr"', false);

    $this->postJson(($this->url)('/fr/retry'))->assertUnprocessable();
    $run->update(['status' => TranslationRunStatus::Translating, 'progress' => 40, 'done_items' => 40, 'total_items' => 100]);
    $this->get(($this->url)())->assertSee('aria-valuenow="40"', false);
    $run->update(['status' => TranslationRunStatus::Failed, 'last_error' => 'Provider timeout']);
    $this->get(($this->url)())->assertSee('Provider timeout')->assertSee(__('localization::vendor_languages.run.retry'));

    $this->postJson(($this->url)('/fr/retry'))->assertOk();
    expect($run->fresh()->status)->toBe(TranslationRunStatus::Queued)->and($run->fresh()->attempts)->toBe(1);

    $history = $this->getJson(($this->url)('/history').'?draw=1&start=0&length=10&order[0][column]=3&order[0][dir]=desc&columns[3][data]=created_at&columns[3][name]=created_at&columns[3][orderable]=true')->assertOk()->json('data');
    expect($history)->toHaveCount(1)->and($history[0]['purchase'])->toBe('French');

    // Another store sees neither the payment nor the purchase.
    $beta = $this->createTenant('lng-'.Str::lower(Str::random(6)));
    $betaOwner = $this->createVendorOwner($beta, ['email' => 'beta-'.Str::lower(Str::random(6)).'@megz.test']);
    $this->actingAsVendor($betaOwner, $beta);
    $this->get($this->tenantUrl($beta, 'admin/settings/languages/payments/'.$log->id.'/result'))->assertNotFound();
    expect($this->getJson($this->tenantUrl($beta, 'admin/settings/languages/history').'?draw=1&start=0&length=10')->json('data'))->toBe([]);
    $this->postJson($this->tenantUrl($beta, 'admin/settings/languages/fr/retry'))->assertUnprocessable();
});

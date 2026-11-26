<?php

use App\Modules\Ai\Enums\AiFeature;
use App\Modules\Ai\Enums\StoreTranslationSection;
use App\Modules\Ai\Enums\TranslationItemStatus;
use App\Modules\Ai\Models\AiUsageLog;
use App\Modules\Ai\Models\StoreTranslationItem;
use App\Modules\Ai\Services\StoreTranslation\AiTranslationOverviewService;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Catalog\Models\StoreCategory;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Modules\Localization\Enums\TranslationRunStatus;
use App\Modules\Localization\Enums\TranslationRunTrigger;
use App\Modules\Localization\Events\StoreTranslationRunQueued;
use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Models\StoreTranslationRun;
use App\Modules\Localization\Services\TenantLanguageService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/*
| VEN-074 / 074a Vendor › Settings › AI translation (decision #99): permissions, run
| stats (golden values), live per-section progress, run history (Yajra) with store
| isolation, Run again / Retry, "Pay & translate" through the shared language checkout.
*/

beforeEach(function (): void {
    Notification::fake();
    $this->tenant = $this->createTenant('ait-'.Str::lower(Str::random(6)), ['selling_country_ids' => null]);
    $this->tenant->run(fn () => app(TenantLanguageService::class)->enableCoreLanguages());
    $this->owner = $this->createVendorOwner($this->tenant, ['email' => 'owner-'.Str::lower(Str::random(6)).'@megz.test']);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/settings/ai-translation'.$path);
    $this->as = fn () => $this->actingAsVendor($this->owner, $this->tenant);
    $this->language = function (string $code, string $name): Language {
        $language = Language::query()->where('code', $code)->first() ?? Language::factory()->create(['code' => $code, 'name' => ['en' => $name, 'ar' => $name], 'native_name' => $name]);
        $language->update(['is_active' => true, 'price_minor' => 4900, 'country_ids' => null]);

        return $language;
    };
    $this->french = ($this->language)('fr', 'French');
    $this->german = ($this->language)('de', 'German');
    $this->run = function (array $attributes): StoreTranslationRun {
        $run = (new StoreTranslationRun)->forceFill($attributes + [
            'tenant_id' => $this->tenant->id,
            'status' => TranslationRunStatus::Completed,
            'queued_at' => now()->subDay(),
        ]);
        $run->save();

        return $run->fresh();
    };
    $this->history = fn (string $query = '') => $this->getJson(($this->url)('/history').'?draw=1&start=0&length=25&order[0][column]=6&order[0][dir]=desc&columns[6][data]=started_at&columns[6][name]=started_at&columns[6][orderable]=true'.$query);
});

function seedAiTranslationRuns(object $test): array
{
    $test->travelTo(now()->startOfMonth()->addDays(10)->setTime(12, 0));

    $german = ($test->run)(['language_id' => $test->german->id, 'language_code' => 'de', 'addon_purchase_id' => 9001, 'done_items' => 1180, 'failed_items' => 64, 'total_items' => 1244, 'prompt_tokens' => 200000, 'completion_tokens' => 14300, 'status' => TranslationRunStatus::Failed, 'started_at' => now()->subMonths(2), 'finished_at' => now()->subMonths(2), 'created_at' => now()->subMonths(2)]);
    $french = ($test->run)(['language_id' => $test->french->id, 'language_code' => 'fr', 'addon_purchase_id' => 9002, 'done_items' => 2296, 'failed_items' => 3, 'total_items' => 3104, 'progress' => 74, 'prompt_tokens' => 400000, 'completion_tokens' => 18200, 'status' => TranslationRunStatus::Translating, 'started_at' => now()->subHour()]);
    AiUsageLog::query()->withoutGlobalScopes()->create(['tenant_id' => $test->tenant->id, 'feature' => AiFeature::ProductTranslation, 'provider' => 'freellmapi', 'model' => 'auto', 'prompt_tokens' => 3000, 'completion_tokens' => 120, 'subject' => 'store_product:77']);
    AiUsageLog::query()->withoutGlobalScopes()->create(['tenant_id' => $test->tenant->id, 'feature' => AiFeature::StoreTranslation, 'provider' => 'openai', 'model' => 'gpt', 'prompt_tokens' => 999, 'completion_tokens' => 1, 'subject' => 'run:'.$french->id]);

    return [$german, $french];
}

it('guards the page and actions by the settings.languages permissions', function (): void {
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    [$none, $viewer] = $this->tenant->run(function (): array {
        $none = VendorStaff::factory()->create();
        $none->givePermissionTo('dashboard.view');
        $viewer = VendorStaff::factory()->create();
        $viewer->givePermissionTo(['dashboard.view', 'settings.languages.view']);

        return [$none, $viewer];
    });
    $run = ($this->run)(['language_code' => 'fr', 'language_id' => $this->french->id]);

    $this->actingAsVendor($none, $this->tenant);
    $this->get(($this->url)())->assertForbidden();
    $this->getJson(($this->url)('/status'))->assertForbidden();
    ($this->history)()->assertForbidden();

    $this->actingAsVendor($viewer, $this->tenant);
    $this->get(($this->url)())->assertOk()->assertSee(__('ai::ai_translation.purchase.no_permission'));
    $this->getJson(($this->url)('/status'))->assertOk();
    $this->post(($this->url)('/purchase'), ['gateway' => 'stripe', 'language_id' => $this->french->id])->assertForbidden();
    $this->postJson(($this->url)('/runs/'.$run->id.'/run-again'))->assertForbidden();
    $this->postJson(($this->url)('/runs/'.$run->id.'/retry'))->assertForbidden();
});

it('computes the run stats and per-language totals (golden values)', function (): void {
    seedAiTranslationRuns($this);

    $service = app(AiTranslationOverviewService::class);
    expect($service->stats($this->tenant))->toBe([
        'runs' => 3,
        'this_month' => 2,
        'active' => 1,
        'items' => 3476,
        'item_languages' => 2,
        'tokens' => 635620,
        'paid_languages' => 2,
    ]);

    $languages = $service->languages($this->tenant)->keyBy('code');
    expect($languages['fr']['items'])->toBe(2296)->and($languages['fr']['tokens'])->toBe(418200)
        ->and($languages['de']['runs'])->toBe(1)->and($languages['de']['run']->status)->toBe(TranslationRunStatus::Failed);

    ($this->as)();
    $this->get(($this->url)())->assertOk()
        ->assertSee('3,476')
        ->assertSee(__('ai::ai_translation.current.title', ['language' => 'French']))
        ->assertSee('2,296 / 3,104')
        ->assertSee(trans_choice('ai::ai_translation.current.failed_notice', 3, ['count' => 3]));
});

it('shows the live per-section progress of the current run', function (): void {
    [, $french] = seedAiTranslationRuns($this);
    $this->tenant->run(function () use ($french): void {
        $categories = StoreCategory::factory()->count(4)->create();
        StoreTranslationItem::query()->create(['run_id' => $french->id, 'section' => StoreTranslationSection::Categories, 'item_key' => (string) $categories[0]->id, 'locale' => 'fr', 'status' => TranslationItemStatus::Done, 'source_hash' => 'a']);
        StoreTranslationItem::query()->create(['run_id' => $french->id, 'section' => StoreTranslationSection::Categories, 'item_key' => (string) $categories[1]->id, 'locale' => 'fr', 'status' => TranslationItemStatus::Failed, 'error' => 'Placeholder lost']);
        StoreTranslationItem::query()->create(['run_id' => $french->id, 'section' => StoreTranslationSection::Categories, 'item_key' => (string) $categories[2]->id, 'locale' => 'de', 'status' => TranslationItemStatus::Done, 'source_hash' => 'b']);
    });

    $current = app(AiTranslationOverviewService::class)->current($this->tenant);
    $categories = collect($current['sections'])->firstWhere('key', 'categories');
    expect($current['run']->id)->toBe($french->id)
        ->and(collect($current['sections'])->pluck('key')->all())->toBe(array_keys(AiTranslationOverviewService::SECTION_GROUPS))
        ->and($categories)->toMatchArray(['done' => 1, 'failed' => 1, 'total' => 4, 'percent' => 25, 'state' => 'partial'])
        ->and($current['failed'])->toHaveCount(1);

    ($this->as)();
    $status = $this->getJson(($this->url)('/status'))->assertOk()->json('data');
    expect($status['run'])->toBe($french->id)->and($status['active'])->toBeTrue()->and($status['status'])->toBe('translating')
        ->and($status['html'])->toContain('Placeholder lost')->toContain('data-run-section="categories"');
});

it('lists full-store runs and product translations in the history, isolated per store', function (): void {
    [$german, $french] = seedAiTranslationRuns($this);
    ($this->as)();

    $rows = ($this->history)()->assertOk()->json('data');
    expect($rows)->toHaveCount(3)
        ->and(collect($rows)->pluck('DT_RowId')->all())->toBe(['product-'.AiUsageLog::query()->withoutGlobalScopes()->where('feature', 'product_translation')->value('id'), 'run-'.$french->id, 'run-'.$german->id])
        ->and($rows[1]['language'])->toContain('French')
        ->and($rows[0]['scope'])->toBe(__('ai::ai_translation.history.scope_product', ['product' => '#77']))
        ->and($rows[2]['actions'])->toContain(__('ai::ai_translation.actions.retry'));

    expect(($this->history)('&status=failed')->json('data'))->toHaveCount(1)
        ->and(($this->history)('&scope=product')->json('data'))->toHaveCount(1)
        ->and(($this->history)('&search=germ')->json('data'))->toHaveCount(1);

    $beta = $this->createTenant('ait-'.Str::lower(Str::random(6)));
    $betaOwner = $this->createVendorOwner($beta, ['email' => 'beta-'.Str::lower(Str::random(6)).'@megz.test']);
    $this->actingAsVendor($betaOwner, $beta);
    expect($this->getJson($this->tenantUrl($beta, 'admin/settings/ai-translation/history').'?draw=1&start=0&length=10')->json('data'))->toBe([]);
    $this->postJson($this->tenantUrl($beta, 'admin/settings/ai-translation/runs/'.$german->id.'/retry'))->assertUnprocessable();
    expect($german->fresh()->status)->toBe(TranslationRunStatus::Failed);
    $this->get($this->tenantUrl($beta, 'admin/settings/ai-translation'))->assertOk()->assertDontSee('2,296 / 3,104');
});

it('retries a failed run and runs a completed one again', function (): void {
    Event::fake([StoreTranslationRunQueued::class]);
    $old = ($this->run)(['language_code' => 'de', 'language_id' => $this->german->id, 'status' => TranslationRunStatus::Failed]);
    $failed = ($this->run)(['language_code' => 'de', 'language_id' => $this->german->id, 'status' => TranslationRunStatus::Failed, 'last_error' => 'Timeout']);
    $done = ($this->run)(['language_code' => 'fr', 'language_id' => $this->french->id, 'status' => TranslationRunStatus::Completed]);
    ($this->as)();

    $this->postJson(($this->url)('/runs/'.$old->id.'/retry'))->assertUnprocessable();
    $this->postJson(($this->url)('/runs/'.$done->id.'/retry'))->assertUnprocessable();
    $this->postJson(($this->url)('/runs/'.$failed->id.'/run-again'))->assertUnprocessable();

    $this->postJson(($this->url)('/runs/'.$failed->id.'/retry'))->assertOk();
    expect($failed->fresh()->status)->toBe(TranslationRunStatus::Queued)->and($failed->fresh()->attempts)->toBe(1);

    $this->postJson(($this->url)('/runs/'.$done->id.'/run-again'))->assertOk();
    $new = StoreTranslationRun::query()->withoutGlobalScopes()->latest('id')->first();
    expect($new->id)->not->toBe($done->id)
        ->and($new->language_code)->toBe('fr')
        ->and($new->trigger)->toBe(TranslationRunTrigger::Delta)
        ->and($new->status)->toBe(TranslationRunStatus::Queued);
    Event::assertDispatchedTimes(StoreTranslationRunQueued::class, 2);

    // The new run is now the latest of French: the old one cannot run again.
    $this->postJson(($this->url)('/runs/'.$done->id.'/run-again'))->assertUnprocessable();
});

it('buys a language with Pay & translate and returns to the page with the result banner', function (): void {
    Event::fake([StoreTranslationRunQueued::class]);
    $gateway = PaymentGateway::factory()->create(['code' => PaymentGatewayCode::Stripe, 'is_active' => true, 'sort_order' => 0, 'fee_percent_bps' => 0, 'fee_fixed_minor' => 0]);
    $gateway->configs()->create(['purpose' => GatewayPurpose::VendorPayments, 'is_enabled' => true, 'mode' => GatewayMode::Test, 'credentials' => ['publishable_key' => 'pk_test_1', 'secret_key' => 'sk_test_1', 'webhook_secret' => 'whsec_test_secret']]);
    Http::fake(['https://api.stripe.com/v1/checkout/sessions*' => fn (HttpRequest $request) => $request->method() === 'POST'
        ? Http::response(['id' => 'cs_test_ait', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_ait'])
        : Http::response(['id' => 'cs_test_ait', 'status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => 'pi_ait', 'amount_total' => 4900, 'currency' => 'usd'])]);
    ($this->as)();

    $this->get(($this->url)())->assertOk()->assertSee(__('ai::ai_translation.purchase.pay', ['price' => '$49.00']));
    $this->post(($this->url)('/purchase'), ['gateway' => 'stripe'])->assertSessionHasErrors('language_id');
    $this->post(($this->url)('/purchase'), ['gateway' => 'stripe', 'language_id' => 999999])->assertRedirect(($this->url)())->assertSessionHas('error');

    $this->post(($this->url)('/purchase'), ['gateway' => 'stripe', 'language_id' => $this->french->id])->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_ait');
    $log = PaymentLog::query()->withoutGlobalScopes()->sole();
    expect($log->amount_minor)->toBe(4900)->and($log->currency)->toBe('USD');

    $this->get(($this->url)('/payments/'.$log->id.'/result'))->assertRedirect(($this->url)());
    $this->get(($this->url)())->assertOk()
        ->assertSee(__('ai::ai_translation.result.badge.success'))
        ->assertSee(__('ai::ai_translation.result.paid', ['language' => 'French', 'amount' => '$49.00']));

    $run = StoreTranslationRun::query()->withoutGlobalScopes()->sole();
    expect($run->language_code)->toBe('fr')->and($run->status)->toBe(TranslationRunStatus::Queued);
});

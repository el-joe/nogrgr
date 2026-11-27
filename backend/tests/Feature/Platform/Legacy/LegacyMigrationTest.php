<?php

use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Models\PaymentGatewayConfig;
use App\Modules\Platform\Events\TenantProvisioned;
use App\Modules\Platform\Legacy\DTOs\LegacyRunOptions;
use App\Modules\Platform\Legacy\DTOs\LegacySourceConfig;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Services\LegacyMigrationService;
use App\Modules\Platform\Legacy\Support\LegacyReport;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Vendors/VendorHelpers.php';

/*
| legacy:migrate (P12): a small fixture copy of the OLD schema (tests/Fixtures/legacy,
| DDL dumped from the old system) is created per parallel worker in two throw-away
| databases, migrated into the test DB, then checked for mapping, idempotency, money
| golden values (ORDER_FORMULAS.md worked example, DB order #4) and decision #65.
*/

function legacyFixture(): object
{
    $token = ParallelTesting::token() ?: '0';
    $central = 'legacy_fixture_test_'.$token;
    $store = 'legacy_store_fixture_test_'.$token;
    $config = config('database.connections.'.config('database.default'));
    $pdo = new PDO(sprintf('mysql:host=%s;port=%s', $config['host'], $config['port']), $config['username'], $config['password']);
    $oldKey = Encrypter::generateKey('AES-256-CBC');
    $old = new Encrypter($oldKey, 'AES-256-CBC');
    $dir = base_path('tests/Fixtures/legacy');

    foreach ([$central => ['central-schema.sql', 'central-data.sql'], $store => ['store-schema.sql', 'store-data.sql']] as $database => $files) {
        $pdo->exec("DROP DATABASE IF EXISTS `{$database}`");
        $pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$database}`");

        foreach ($files as $file) {
            $sql = strtr((string) file_get_contents($dir.'/'.$file), [
                '{{STORE_DB}}' => $store,
                '{{MAIL_PASSWORD}}' => $old->encrypt('legacy-mail-secret', false),
                '{{STRIPE_CREDENTIALS}}' => $old->encrypt(json_encode(['key' => 'pk_test_legacy', 'secret' => 'sk_test_legacy', 'sandbox' => '1']), false),
            ]);
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0; '.$sql);
        }
    }

    test()->beforeApplicationDestroyed(static function () use ($pdo, $central, $store): void {
        $pdo->exec("DROP DATABASE IF EXISTS `{$central}`");
        $pdo->exec("DROP DATABASE IF EXISTS `{$store}`");
    });

    return (object) [
        'config' => new LegacySourceConfig($config['host'], (int) $config['port'], $central, $config['username'], (string) $config['password'], 'base64:'.base64_encode($oldKey), $dir.'/storage'),
    ];
}

function runLegacy(object $fixture, bool $dryRun = false, array $steps = []): LegacyReport
{
    return app(LegacyMigrationService::class)->run($fixture->config, new LegacyRunOptions(
        steps: $steps,
        dryRun: $dryRun,
    ));
}

function legacyTenant(): Tenant
{
    return Tenant::query()->where('slug', 'legacystore')->firstOrFail();
}

beforeEach(function (): void {
    cleanUpProvisionedTenants();
    Queue::fake();
    Storage::fake('public');
    Event::fake([TenantProvisioned::class]);
});

it('maps the central data and provisions the old store', function (): void {
    $fixture = legacyFixture();
    $report = runLegacy($fixture);

    $product = DB::table('products')->where('sku', 'LEG-101')->first();
    expect($product)->not->toBeNull()
        ->and(json_decode($product->name, true))->toEqual(['en' => 'New hot sale 65 pieces', 'ar' => 'عرض 65 قطعة'])
        ->and($product->base_price)->toBe(176)
        ->and($product->assignment_scope)->toBe('selected');

    $egypt = DB::table('countries')->where('iso2', 'EG')->value('id');
    expect(json_decode($product->fixed_shipping_costs, true))->toHaveKey((string) $egypt, 20000)
        ->and(DB::table('category_product')->where('product_id', $product->id)->count())->toBe(1)
        ->and(DB::table('catalog_media')->where('mediable_id', $product->id)->count())->toBe(1)
        ->and(DB::table('product_variants')->where('product_id', $product->id)->value('price'))->toBe(176)
        ->and(DB::table('categories')->where('slug', 'kitchen')->value('parent_id'))->toBe(DB::table('categories')->where('slug', 'home')->value('id'))
        ->and(DB::table('plans')->where('yearly_price_minor', 29900)->exists())->toBeTrue()
        ->and(DB::table('coupons')->where('code', 'WELCOME10')->value('min_spend_minor'))->toBe(5000)
        ->and(DB::table('affiliates')->where('email', 'aff@legacy.test')->value('commission_value'))->toBe(1200)
        ->and(DB::table('admins')->where('email', 'old.admin@legacy.test')->exists())->toBeTrue();

    $tenant = legacyTenant();
    expect($tenant->status->value)->toBe('active')
        ->and($tenant->profit_percent_bps)->toBe(2500)
        ->and(DB::table('product_tenant_assignments')->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(DB::table('support_tickets')->where('tenant_id', $tenant->id)->value('reference'))->toStartWith('T-');

    expect(collect($report->issues())->pluck('message')->all())
        ->toContain('Image file missing in old storage', 'City of an unknown country skipped (orphan)', 'Gateway not supported by the new system (skipped)');
});

it('decrypts old credentials and secrets with the old key and re-encrypts them with ours', function (): void {
    $fixture = legacyFixture();
    runLegacy($fixture, steps: [LegacyStep::Localization, LegacyStep::Settings, LegacyStep::Gateways]);

    $config = PaymentGatewayConfig::query()->whereHas('gateway', fn ($q) => $q->where('code', 'stripe'))->where('purpose', GatewayPurpose::VendorPayments)->firstOrFail();
    $raw = DB::table('payment_gateway_configs')->where('id', $config->id)->value('credentials');

    expect($config->credentials)->toBe(['publishable_key' => 'pk_test_legacy', 'secret_key' => 'sk_test_legacy'])
        ->and($raw)->not->toContain('sk_test_legacy')
        ->and(DB::table('payment_gateways')->where('code', 'stripe')->value('fee_percent_bps'))->toBe(290)
        ->and(DB::table('integration_settings')->where(['group' => 'mail', 'key' => 'password'])->value('value'))->not->toContain('legacy-mail-secret');
});

it('copies order money exactly as stored (ORDER_FORMULAS worked example, order #4)', function (): void {
    $fixture = legacyFixture();
    $report = runLegacy($fixture);
    $tenant = legacyTenant();

    expect(collect($report->issues())->filter(fn (array $issue): bool => $issue['level']->value === 'error')->pluck('message')->all())->toBe([]);

    tenancy()->initialize($tenant);
    $order = DB::connection('tenant')->table('orders')->where('number', '4')->first();
    $item = DB::connection('tenant')->table('order_items')->where('order_id', $order->id)->first();
    tenancy()->end();

    expect([$item->cost, $item->profit_amount, $item->price, $item->sub_total, $item->fixed_shipping, $item->discount, $item->grand_total])
        ->toBe([176, 44, 220, 220, 20000, 2022, 18198])
        ->and($item->product_source)->toBe(StoreProductSource::Central->value)
        ->and([$order->items_grand_total, $order->shipping_charge, $order->grand_total, $order->platform_profit, $order->vendor_profit])
        ->toBe([18198, 120, 18318, 18274, 44])
        ->and($order->platform_profit + $order->vendor_profit)->toBe($order->grand_total)
        ->and($order->uuid)->toBe('aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee')
        ->and($order->status)->toBe('delivered')
        ->and($order->payment_source)->toBe('central')
        ->and(DB::table('order_facts')->where('tenant_id', $tenant->id)->value('grand_total'))->toBe(18318);
});

it('keeps central data central: selections + overrides only, own products in full (#65)', function (): void {
    $fixture = legacyFixture();
    runLegacy($fixture);
    $tenant = legacyTenant();
    $centralId = DB::table('products')->where('sku', 'LEG-101')->value('id');
    $egypt = DB::table('countries')->where('iso2', 'EG')->value('id');
    $variantId = DB::table('product_variants')->where('sku', 'LEG-101-R')->value('id');

    tenancy()->initialize($tenant);
    $selection = DB::connection('tenant')->table('store_products')->where('source', 'central')->first();
    $own = DB::connection('tenant')->table('store_products')->where('source', 'own')->first();
    $override = DB::connection('tenant')->table('product_overrides')->where('product_id', $centralId)->first();
    $counts = [
        'customers' => DB::connection('tenant')->table('customers')->count(),
        'addresses' => DB::connection('tenant')->table('customer_addresses')->count(),
        'reviews' => DB::connection('tenant')->table('product_reviews')->count(),
        'favorites' => DB::connection('tenant')->table('customer_favorites')->count(),
        'coupons' => DB::connection('tenant')->table('store_coupons')->pluck('code')->all(),
    ];
    tenancy()->end();

    expect($selection->central_product_id)->toBe($centralId)
        ->and($selection->name)->toBeNull()
        ->and($selection->price)->toBeNull()
        ->and((bool) $selection->is_featured)->toBeTrue()
        ->and(json_decode($selection->markup, true))->toEqual(['default' => ['profit_type' => 'percentage', 'profit_value' => 25], (string) $egypt => ['profit_type' => 'fixed', 'profit_value' => 0.44]])
        ->and(json_decode($selection->variant_markup, true))->toHaveKey((string) $variantId)
        ->and(json_decode($override->name, true))->toBe(['en' => 'Store name for hot sale'])
        ->and(json_decode($own->name, true))->toBe(['en' => 'Own Mug'])
        ->and($own->price)->toBe(1500)
        ->and($counts)->toBe(['customers' => 1, 'addresses' => 1, 'reviews' => 1, 'favorites' => 1, 'coupons' => ['STORE5']]);
});

it('is idempotent: a second run updates instead of duplicating', function (): void {
    $fixture = legacyFixture();
    runLegacy($fixture);
    $before = [DB::table('products')->count(), DB::table('product_variants')->count(), DB::table('catalog_media')->count(), DB::table('tenants')->count(), DB::table('owners')->count(), DB::table('coupons')->count(), DB::table('order_facts')->count()];

    $second = runLegacy($fixture);
    $tenant = legacyTenant();
    tenancy()->initialize($tenant);
    $store = [DB::connection('tenant')->table('store_products')->count(), DB::connection('tenant')->table('orders')->count(), DB::connection('tenant')->table('order_items')->count(), DB::connection('tenant')->table('customers')->count()];
    tenancy()->end();

    expect([DB::table('products')->count(), DB::table('product_variants')->count(), DB::table('catalog_media')->count(), DB::table('tenants')->count(), DB::table('owners')->count(), DB::table('coupons')->count(), DB::table('order_facts')->count()])->toBe($before)
        ->and($store)->toBe([2, 1, 1, 1])
        ->and($second->count('central', 'product', 'created'))->toBe(0)
        ->and($second->count('central', 'product', 'updated'))->toBe(1)
        ->and($second->count('store:11111111-2222-3333-4444-555555555555', 'order', 'updated'))->toBe(1);
});

it('writes nothing in a dry run but reports counts and issues', function (): void {
    $fixture = legacyFixture();
    $products = DB::table('products')->count();
    $report = runLegacy($fixture, dryRun: true);

    expect(DB::table('products')->count())->toBe($products)
        ->and(DB::table('legacy_central_ids')->count())->toBe(0)
        ->and(Tenant::query()->where('slug', 'legacystore')->exists())->toBeFalse()
        ->and($report->count('central', 'product', 'created'))->toBe(1)
        ->and($report->count('store:11111111-2222-3333-4444-555555555555', 'orders', 'read'))->toBe(1)
        ->and($report->toMarkdown(['Mode' => 'dry run']))->toContain('| central | products | 1 |');
});

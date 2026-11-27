<?php

use App\Modules\Platform\Events\TenantProvisioned;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Services\LegacyReconciliationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Vendors/VendorHelpers.php';
require_once __DIR__.'/LegacyHelpers.php';

/*
| legacy:migrate P12 batch 2 (decision #113): central after-sales, billing, content,
| e-mail templates, shipping, settlements / payouts, catalogue requests and the finance
| reconciliation (golden values from tests/Fixtures/legacy/central-data.sql).
*/

beforeEach(function (): void {
    cleanUpProvisionedTenants();
    Queue::fake();
    Storage::fake('public');
    Storage::fake('central');
    Event::fake([TenantProvisioned::class]);
});

it('migrates returns and refunds in cents, linked to the migrated order', function (): void {
    $report = runLegacy(legacyFixture());
    $tenant = legacyTenant();

    $return = DB::table('return_requests')->where('tenant_id', $tenant->id)->sole();
    $refunds = DB::table('refunds')->where('tenant_id', $tenant->id)->orderBy('id')->get();

    expect([$return->order_uuid, $return->order_number, $return->status, $return->reason_code, $return->refund_amount, $return->inspection_result, $return->product_name])
        ->toBe(['aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', '4', 'refunded', 'changed_mind', 2022, 'accepted', 'New hot sale 65 pieces'])
        ->and(DB::table('return_request_events')->where('return_request_id', $return->id)->pluck('actor_type')->all())->toBe(['system', 'admin'])
        ->and($refunds->pluck('amount')->all())->toBe([2022, 1000])
        ->and($refunds[0]->return_request_id)->toBe($return->id)
        ->and([$refunds[0]->reference, $refunds[0]->status, $refunds[0]->method, $refunds[0]->order_number])->toBe(['RF-LEG-1', 'completed', 'manual', '4'])
        ->and([$refunds[1]->source, $refunds[1]->requested_by_type, $refunds[1]->order_number])->toBe(['cancellation', 'vendor', 'L-BBBBBBBB'])
        ->and(DB::table('refunds')->where('reference', 'RF-GONE-1')->exists())->toBeFalse();

    expect(collect($report->issues())->pluck('message')->all())
        ->toContain('Return of an order that was not migrated (skipped; store orders not required for launch)', 'Refund of a store that was not migrated (skipped)');
});

it('migrates subscription payments as paid invoices without the raw gateway payload', function (): void {
    runLegacy(legacyFixture());
    $tenant = legacyTenant();

    $logs = DB::table('payment_logs')->where('tenant_id', $tenant->id)->orderBy('id')->get();
    $invoice = DB::table('invoices')->where('id', $logs[0]->invoice_id)->first();

    expect($logs->pluck('status')->all())->toBe(['captured', 'pending'])
        ->and($logs->pluck('amount_minor')->all())->toBe([29900, 29900])
        ->and($logs[1]->invoice_id)->toBeNull()
        ->and($logs[0]->payload)->not->toContain('4242')
        ->and($logs[0]->payload)->not->toContain('LEGACYFP')
        ->and([$invoice->number, $invoice->type, $invoice->status, $invoice->total_minor, $invoice->tax_minor])->toBe(['INV-2026-L0001', 'subscription', 'paid', 29900, 0])
        ->and(DB::table('invoice_items')->where('invoice_id', $invoice->id)->value('total_minor'))->toBe(29900);
});

it('migrates blog, FAQs, pages and mappable e-mail templates', function (): void {
    $report = runLegacy(legacyFixture(), steps: [LegacyStep::Localization, LegacyStep::Content, LegacyStep::EmailTemplates]);

    $post = DB::table('blog_posts')->where('slug', 'hello-world')->first();
    $template = DB::table('email_templates')->where('event', 'order.confirmation')->first();

    expect(json_decode($post->title, true))->toEqual(['en' => 'Hello world', 'ar' => 'مرحبا'])
        ->and($post->status)->toBe('published')
        ->and($post->blog_category_id)->toBe(DB::table('blog_categories')->where('slug', 'news')->value('id'))
        ->and(json_decode(DB::table('faqs')->value('question'), true))->toBe(['en' => 'How do I pay?'])
        ->and(DB::table('faqs')->value('group'))->toBe('billing')
        ->and(DB::table('pages')->where('slug', 'privacy-policy')->value('status'))->toBe('draft')
        ->and((bool) DB::table('pages')->where('slug', 'privacy-policy')->value('is_compliance'))->toBeTrue()
        ->and(json_decode($template->subject, true))->toEqual(['en' => 'We received your order {{ order.number }}', 'ar' => 'استلمنا طلبك {{ order.number }}'])
        ->and(json_decode($template->body, true)['en'])->toContain('{{ customer.name }}', '{{ store.name }}')->not->toContain('<script', 'style=')
        ->and($template->audience)->toBe('shoppers')
        ->and((string) DB::table('email_templates')->where('event', 'order.delivered')->value('body'))->not->toContain('tracking_secret');

    expect(collect($report->issues())->pluck('message')->all())
        ->toContain('Old e-mail template has no new event (new default kept)', 'Old e-mail template uses variables without a new equivalent (new default kept)');
});

it('maps shipping zones to rules with weight bands in cents and fixed per-gram rates', function (): void {
    runLegacy(legacyFixture(), steps: [LegacyStep::Localization, LegacyStep::Shipping]);
    $saudi = DB::table('countries')->where('iso2', 'SA')->value('id');
    $egypt = DB::table('countries')->where('iso2', 'EG')->value('id');

    $rule = DB::table('shipping_rules')->where('name', 'KSA Zone')->first();
    $zone = DB::table('shipping_zones')->where('shipping_rule_id', $rule->id)->first();

    expect($rule->status)->toBe('active')
        ->and(DB::table('shipping_rule_countries')->where('shipping_rule_id', $rule->id)->pluck('country_id')->all())->toBe([$saudi])
        ->and(DB::table('shipping_zone_bands')->where('shipping_zone_id', $zone->id)->orderBy('position')->get(['min_weight_grams', 'max_weight_grams', 'price'])->map(fn ($band): array => (array) $band)->all())
        ->toBe([['min_weight_grams' => 0, 'max_weight_grams' => 1000, 'price' => 1000], ['min_weight_grams' => 1001, 'max_weight_grams' => null, 'price' => 2550]])
        ->and(DB::table('shipping_rules')->where('name', 'Global Zone')->value('status'))->toBe('draft')
        ->and(DB::table('shipping_fixed_costs')->where('country_id', $egypt)->value('price_per_gram'))->toBe('0.221000');
});

it('migrates settlements and payouts and posts them to the ledger once', function (): void {
    $fixture = legacyFixture();
    runLegacy($fixture);
    runLegacy($fixture);
    $tenant = legacyTenant();

    $settlement = DB::table('order_settlements')->where('tenant_id', $tenant->id)->sole();
    $payouts = DB::table('tenant_payouts')->where('tenant_id', $tenant->id)->orderBy('id')->get();

    expect([$settlement->amount, $settlement->product_cost, $settlement->shipping_cost, $settlement->status, $settlement->order_number])->toBe([20176, 176, 20000, 'paid', '4'])
        ->and($payouts->pluck('status')->all())->toBe(['paid', 'scheduled'])
        ->and($payouts->pluck('amount')->all())->toBe([5000, 2000])
        ->and(json_decode($payouts[0]->payee, true))->toBe(['bank' => 'Bank X', 'iban' => '•••• 1234', 'name' => 'Legacy Owner'])
        ->and($payouts[0]->method)->toBe('bank_transfer')
        ->and(DB::table('ledger_transactions')->where('tenant_id', $tenant->id)->where('source', 'settlement')->count())->toBe(1)
        ->and(DB::table('ledger_transactions')->where('tenant_id', $tenant->id)->where('source', 'payout')->count())->toBe(1);
});

it('migrates catalogue requests with threads, private attachments and payments', function (): void {
    $report = runLegacy(legacyFixture());
    $tenant = legacyTenant();

    $product = DB::table('catalog_product_requests')->where('tenant_id', $tenant->id)->sole();
    $messages = DB::table('catalog_request_messages')->where('requestable_type', 'product_request')->where('requestable_id', $product->id)->orderBy('id')->get();
    $attachments = DB::table('catalog_request_attachments')->whereIn('message_id', $messages->pluck('id'))->get();
    $manufacturing = DB::table('catalog_manufacturing_requests')->where('tenant_id', $tenant->id)->sole();

    expect([$product->title, $product->status, $product->priority])->toBe(['Need mugs', 'pending', 'high'])
        ->and($messages->pluck('sender')->all())->toBe(['vendor', 'vendor', 'admin'])
        ->and($messages[0]->body)->toBeNull()
        ->and($attachments)->toHaveCount(2)
        ->and($attachments->pluck('disk')->unique()->all())->toBe(['central'])
        ->and(Storage::disk('central')->exists($attachments[0]->path))->toBeTrue()
        ->and(DB::table('catalog_brand_requests')->where('tenant_id', $tenant->id)->value('status'))->toBe('under_review')
        ->and([$manufacturing->status, $manufacturing->quantity, $manufacturing->product_id])->toBe(['requested', 50, DB::table('products')->where('sku', 'LEG-101')->value('id')])
        ->and(DB::table('catalog_request_payments')->where('requestable_type', 'manufacturing_request')->value('amount'))->toBe(5500)
        ->and(DB::table('catalog_product_requests')->count())->toBe(1);

    expect(collect($report->issues())->pluck('message')->all())->toContain('Image file missing in old storage');
});

it('reconciles old and new finance totals to zero difference (golden values)', function (): void {
    $fixture = legacyFixture();
    $report = runLegacy($fixture);
    $tenant = legacyTenant();
    $scope = $tenant->name.' (#'.$tenant->id.')';

    $result = app(LegacyReconciliationService::class)->run($fixture->config);
    $value = fn (string $metric): array => array_intersect_key($result->line($scope, $metric) ?? [], ['old' => 0, 'new' => 0]);

    expect($result->differences())->toBe(0)
        ->and($value('orders grand total'))->toBe(['old' => 18318, 'new' => 18318])
        ->and($value('platform profit'))->toBe(['old' => 18274, 'new' => 18274])
        ->and($value('vendor profit'))->toBe(['old' => 44, 'new' => 44])
        ->and($value('refunds'))->toBe(['old' => 3022, 'new' => 3022])
        ->and($value('subscription payments'))->toBe(['old' => 29900, 'new' => 29900])
        ->and($value('settlements'))->toBe(['old' => 20176, 'new' => 20176])
        ->and($value('settlements (ledger)'))->toBe(['old' => 20176, 'new' => 20176])
        ->and($value('payouts'))->toBe(['old' => 5000, 'new' => 5000])
        ->and($value('payouts (ledger)'))->toBe(['old' => 5000, 'new' => 5000])
        ->and($result->excluded())->toMatchArray(['refunds' => ['rows' => 1, 'amount' => 500], 'payment_logs' => ['rows' => 1, 'amount' => 29900], 'vendor_settlements' => ['rows' => 1, 'amount' => 100]])
        ->and($report->toMarkdown([]))->toContain('## Finance reconciliation', '**Differences: 0**');
});

it('legacy:reconcile writes the section into the report and fails on a difference', function (): void {
    $fixture = legacyFixture();
    runLegacy($fixture);
    $path = storage_path('framework/testing/reconcile-'.(ParallelTesting::token() ?: '0').'.md');
    @mkdir(dirname($path), 0775, true);
    file_put_contents($path, "# Report\n\n## Finance reconciliation\n\nold\n\n## Tail\n");
    $config = $fixture->config;
    $source = storage_path('framework/testing/legacy-source-'.(ParallelTesting::token() ?: '0'));
    @mkdir($source, 0775, true);
    file_put_contents($source.'/.env', "DB_HOST={$config->host}\nDB_PORT={$config->port}\nDB_DATABASE={$config->database}\nDB_USERNAME={$config->username}\nDB_PASSWORD=\"{$config->password}\"\n");

    expect(Artisan::call('legacy:reconcile', ['--report' => $path, '--source' => $source]))->toBe(0);
    $written = (string) file_get_contents($path);
    expect($written)->toContain('**Differences: 0**', '## Tail')->not->toContain("\nold\n");

    DB::table('refunds')->where('reference', 'RF-LEG-1')->update(['amount' => 2000]);
    expect(Artisan::call('legacy:reconcile', ['--report' => $path, '--source' => $source]))->toBe(1);
    @unlink($path);
    @unlink($source.'/.env');
    @rmdir($source);
});

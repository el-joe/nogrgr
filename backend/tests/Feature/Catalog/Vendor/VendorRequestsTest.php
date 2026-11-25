<?php

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Catalog\DTOs\Requests\PaymentRequestData;
use App\Modules\Catalog\DTOs\Requests\RequestReplyData;
use App\Modules\Catalog\Enums\BrandRequestStatus;
use App\Modules\Catalog\Enums\ManufacturingRequestStatus;
use App\Modules\Catalog\Enums\MessageSender;
use App\Modules\Catalog\Enums\PaymentRequestStatus;
use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Requests\BrandRequest;
use App\Modules\Catalog\Models\Requests\ManufacturingRequest;
use App\Modules\Catalog\Models\Requests\ProductRequest;
use App\Modules\Catalog\Models\Requests\RequestMessage;
use App\Modules\Catalog\Notifications\RequestUpdateNotification;
use App\Modules\Catalog\Notifications\StoreRequestNotification;
use App\Modules\Catalog\Services\Requests\RequestPaymentService;
use App\Modules\Catalog\Services\Requests\RequestThreadService;
use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Models\PaymentGateway;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/VendorCatalogHelpers.php';
require_once __DIR__.'/../CatalogHelpers.php';
require_once __DIR__.'/../../Platform/Registration/SignUpHelpers.php';

/*
| VEN-050 … 050j vendor requests: hub + lists scoped to the store, new product /
| manufacturing / brand requests, detail with the live conversation (vendor channel auth,
| read receipts, replies → admin notification), attachments, cancel / delete, CSV export
| and paying a payment request through the hosted checkout (webhook replay safe).
*/

function vendorPaymentsGateway(): PaymentGateway
{
    $gateway = PaymentGateway::factory()->create(['code' => PaymentGatewayCode::Stripe, 'is_active' => true, 'sort_order' => 0, 'fee_percent_bps' => 0, 'fee_fixed_minor' => 0]);
    $gateway->configs()->create([
        'purpose' => GatewayPurpose::VendorPayments,
        'is_enabled' => true,
        'mode' => GatewayMode::Test,
        'credentials' => ['publishable_key' => 'pk_test_1', 'secret_key' => 'sk_test_1', 'webhook_secret' => 'whsec_test_secret'],
    ]);

    return $gateway;
}

function fakeRequestStripe(string $paymentStatus = 'paid'): void
{
    Http::fake([
        'https://api.stripe.com/v1/checkout/sessions*' => function (HttpRequest $request) use ($paymentStatus) {
            if ($request->method() === 'POST') {
                return Http::response(['id' => 'cs_test_request', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_request']);
            }

            return Http::response(['id' => 'cs_test_request', 'status' => $paymentStatus === 'paid' ? 'complete' : 'open', 'payment_status' => $paymentStatus, 'payment_intent' => 'pi_request', 'amount_total' => 170000, 'currency' => 'usd']);
        },
    ]);
}

function requestStripeEvent(string $type, array $session, string $id): string
{
    return (string) json_encode(['id' => $id, 'type' => $type, 'data' => ['object' => $session]]);
}

beforeEach(function (): void {
    Storage::fake('central');
    Notification::fake();
    [$this->tenant, $this->owner, $this->sa, $this->ae] = catalogStore();
    [$this->other] = catalogStore('beta');
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/requests'.$path);
    $this->as = fn () => $this->actingAsVendor($this->owner, $this->tenant);
});

it('sends guests to the store login', function (): void {
    $this->get(($this->url)('/product'))->assertRedirect($this->tenantUrl($this->tenant, 'login'));
});

it('forbids staff without the request permissions', function (): void {
    $staff = catalogStaff($this->tenant, []);

    foreach (['', '/product', '/product/data', '/manufacturing/create', '/brand'] as $path) {
        $this->actingAsVendor($staff, $this->tenant)->getJson(($this->url)($path))->assertForbidden();
    }

    $this->actingAsVendor($staff, $this->tenant)->postJson(($this->url)('/product'), ['subject' => 'Lamp'])->assertForbidden();

    $viewer = catalogStaff($this->tenant, ['requests.brands.view']);
    $this->actingAsVendor($viewer, $this->tenant)->get(($this->url)(''))->assertRedirect(($this->url)('/brand'));
    $this->actingAsVendor($viewer, $this->tenant)->postJson(($this->url)('/brand'), ['subject' => 'Megz'])->assertForbidden();
});

it('refuses a session of another store', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant)->get($this->tenantUrl($this->other, 'admin/requests/product'))->assertNotFound();
});

it('lists only the store\'s own requests with stats and unread replies', function (): void {
    $own = ProductRequest::factory()->create(['tenant_id' => $this->tenant->id, 'title' => 'Bamboo board']);
    ProductRequest::factory()->create(['tenant_id' => $this->other->id, 'title' => 'Foreign lamp']);
    app(RequestThreadService::class)->post($own, new RequestReplyData(MessageSender::Admin, 'Sara', 'Target price?'));

    ($this->as)()->get(($this->url)('/product'))
        ->assertOk()
        ->assertSee(__('catalog::vendor_requests.pages.product.title'))
        ->assertSee(__('catalog::vendor_requests.tabs.manufacturing'));

    $json = ($this->as)()->getJson(($this->url)('/product/data?draw=1&start=0&length=10'))->assertOk()->json();
    expect($json['recordsFiltered'])->toBe(1)
        ->and($json['data'][0]['request'])->toContain('Bamboo board')
        ->and($json['data'][0]['unread'])->toContain('>1<');

    $foreign = ProductRequest::withoutTenantScope()->where('tenant_id', $this->other->id)->sole();
    ($this->as)()->get(($this->url)('/product/'.$foreign->id))->assertNotFound();
});

it('validates and submits a product request with target markets and files', function (): void {
    $admin = catalogAdmin(['requests.products.view', 'requests.products.manage']);

    ($this->as)()->getJson(($this->url)('/product/create'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
    ($this->as)()->get(($this->url)('/product/create'))->assertOk()->assertSee('vendor-request-form', false);

    ($this->as)()->postJson(($this->url)('/product'), ['country_ids' => [999999]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subject', 'country_ids']);

    ($this->as)()->post(($this->url)('/product'), [
        'subject' => 'Bamboo cutting board set',
        'url' => 'https://supplier.example/board',
        'country_ids' => [$this->sa->id, $this->ae->id],
        'notes' => 'Three sizes please.',
        'files' => [UploadedFile::fake()->create('supplier-quote.pdf', 240, 'application/pdf')],
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('success', true);

    $request = ProductRequest::withoutTenantScope()->where('tenant_id', $this->tenant->id)->sole();
    expect($request->title)->toBe('Bamboo cutting board set')
        ->and($request->target_country_ids)->toBe([$this->sa->id, $this->ae->id])
        ->and($request->description)->toBe('Three sizes please.');

    $message = RequestMessage::query()->sole();
    expect($message->sender)->toBe(MessageSender::Vendor)->and($message->attachments)->toHaveCount(1);

    Notification::assertSentTo($admin, RequestUpdateNotification::class, fn (RequestUpdateNotification $notification): bool => $notification->eventName === 'vendor_submitted');
    Notification::assertNotSentTo($admin, RequestUpdateNotification::class, fn (RequestUpdateNotification $notification): bool => $notification->eventName === 'vendor_replied');
});

it('submits a manufacturing request linked to a store product', function (): void {
    $central = Product::factory()->create(['name' => ['en' => 'Moka pot', 'ar' => 'موكا']]);
    $product = storeProduct($this->tenant, $central->id);

    ($this->as)()->get(($this->url)('/manufacturing/create'))->assertOk()->assertSee(__('catalog::vendor_requests.create.manufacturing_title'));
    ($this->as)()->getJson(($this->url)('/manufacturing/products?q=Moka'))->assertOk()->assertJsonPath('results.0.id', $product->id);

    ($this->as)()->postJson(($this->url)('/manufacturing'), ['subject' => 'Tote', 'quantity' => 0, 'notes' => 'x', 'product_id' => 999999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['quantity', 'product_id']);

    ($this->as)()->postJson(($this->url)('/manufacturing'), ['subject' => 'Branded moka pot', 'quantity' => 1000, 'notes' => 'Engraved logo', 'product_id' => $product->id])->assertCreated();

    $request = ManufacturingRequest::withoutTenantScope()->sole();
    expect($request->product_id)->toBe($central->id)->and($request->quantity)->toBe(1000)->and($request->status)->toBe(ManufacturingRequestStatus::Requested);
});

it('submits, deletes a pending brand request and refuses deleting a reviewed one', function (): void {
    ($this->as)()->postJson(($this->url)('/brand'), ['subject' => 'Megz Home', 'country_ids' => [$this->sa->id], 'notes' => 'Logo draft attached.'])->assertCreated();
    $brand = BrandRequest::withoutTenantScope()->sole();
    expect($brand->target_country_ids)->toBe([$this->sa->id]);

    ($this->as)()->deleteJson(($this->url)('/brand/'.$brand->id))->assertOk();
    expect(BrandRequest::withoutTenantScope()->count())->toBe(0);

    $reviewed = BrandRequest::factory()->create(['tenant_id' => $this->tenant->id, 'status' => BrandRequestStatus::UnderReview]);
    ($this->as)()->deleteJson(($this->url)('/brand/'.$reviewed->id))->assertUnprocessable();
});

it('opens a request, marks platform replies read and notifies admins of a vendor reply', function (): void {
    $admin = catalogAdmin(['requests.manufacturing.view', 'requests.manufacturing.manage']);
    $request = ManufacturingRequest::factory()->create(['tenant_id' => $this->tenant->id, 'product_name' => 'Branded tote bags']);
    $adminMessage = app(RequestThreadService::class)->post($request, new RequestReplyData(MessageSender::Admin, 'Sara', 'Final quote attached.'));

    ($this->as)()->get(($this->url)('/manufacturing/'.$request->id))
        ->assertOk()
        ->assertSee('Branded tote bags')
        ->assertSee('Final quote attached.')
        ->assertSee(__('catalog::vendor_requests.actions.cancel'));

    expect($adminMessage->fresh()->read_at)->not->toBeNull();

    ($this->as)()->postJson(($this->url)('/manufacturing/'.$request->id.'/reply'), [])->assertUnprocessable()->assertJsonValidationErrors('body');
    ($this->as)()->postJson(($this->url)('/manufacturing/'.$request->id.'/reply'), ['body' => 'Approved, please send the payment request.'])
        ->assertCreated()
        ->assertJsonPath('success', true);

    $reply = RequestMessage::query()->latest('id')->first();
    expect($reply->sender)->toBe(MessageSender::Vendor)->and($reply->sender_name)->toBe($this->tenant->name);

    Notification::assertSentTo($admin, RequestUpdateNotification::class, fn (RequestUpdateNotification $notification): bool => $notification->eventName === 'vendor_replied' && $notification->parameters['store'] === $this->tenant->name);

    $json = ($this->as)()->getJson(($this->url)('/manufacturing/'.$request->id.'/messages?after='.$adminMessage->id))->assertOk()->json('data');
    expect($json['html'])->toContain('Approved, please send')->and($json['last_id'])->toBe($reply->id);
});

it('pushes admin replies to the store staff inbox', function (): void {
    $request = ProductRequest::factory()->create(['tenant_id' => $this->tenant->id]);

    app(RequestThreadService::class)->post($request, new RequestReplyData(MessageSender::Admin, 'Sara', 'Do you have a target price?'));

    Notification::assertSentTo($this->owner, StoreRequestNotification::class, fn (StoreRequestNotification $notification): bool => $notification->eventName === 'admin_replied'
        && $notification->vendorRoute === 'requests.product.show'
        && $notification->routeParameters === ['id' => $request->id]);
});

it('cancels a manufacturing request until production starts', function (): void {
    $request = ManufacturingRequest::factory()->create(['tenant_id' => $this->tenant->id, 'status' => ManufacturingRequestStatus::Quoted]);
    $admin = catalogAdmin(['requests.manufacturing.view', 'requests.manufacturing.manage']);

    ($this->as)()->postJson(($this->url)('/manufacturing/'.$request->id.'/cancel'))->assertOk();
    expect($request->fresh()->status)->toBe(ManufacturingRequestStatus::Cancelled);
    Notification::assertSentTo($admin, RequestUpdateNotification::class, fn (RequestUpdateNotification $notification): bool => $notification->eventName === 'vendor_cancelled');

    $producing = ManufacturingRequest::factory()->create(['tenant_id' => $this->tenant->id, 'status' => ManufacturingRequestStatus::InProduction]);
    ($this->as)()->postJson(($this->url)('/manufacturing/'.$producing->id.'/cancel'))->assertUnprocessable();
});

it('exports the store\'s manufacturing requests', function (): void {
    ManufacturingRequest::factory()->create(['tenant_id' => $this->tenant->id, 'product_name' => 'Own tote']);
    ManufacturingRequest::factory()->create(['tenant_id' => $this->other->id, 'product_name' => 'Foreign tote']);

    $csv = ($this->as)()->get(($this->url)('/manufacturing/export'))->assertOk()->streamedContent();

    expect($csv)->toContain('Own tote')->not->toContain('Foreign tote');
});

it('streams attachments of the store\'s own requests only', function (): void {
    $own = ProductRequest::factory()->create(['tenant_id' => $this->tenant->id]);
    $foreign = ProductRequest::factory()->create(['tenant_id' => $this->other->id]);
    $threads = app(RequestThreadService::class);
    $mine = $threads->post($own, new RequestReplyData(MessageSender::Admin, 'Sara', null, [UploadedFile::fake()->create('quote.pdf', 10, 'application/pdf')]))->attachments->first();
    $theirs = $threads->post($foreign, new RequestReplyData(MessageSender::Admin, 'Sara', null, [UploadedFile::fake()->create('secret.pdf', 10, 'application/pdf')]))->attachments->first();

    ($this->as)()->get(($this->url)('/attachments/'.$mine->id.'?download=1'))->assertOk();
    ($this->as)()->get(($this->url)('/attachments/'.$theirs->id))->assertForbidden();

    $manufacturingOnly = catalogStaff($this->tenant, ['requests.manufacturing.view']);
    $this->actingAsVendor($manufacturingOnly, $this->tenant)->get(($this->url)('/attachments/'.$mine->id))->assertForbidden();
});

it('authorizes the live request channel for the store\'s own staff only', function (): void {
    $own = ProductRequest::factory()->create(['tenant_id' => $this->tenant->id]);
    $foreign = ProductRequest::factory()->create(['tenant_id' => $this->other->id]);
    $callback = app(Broadcaster::class)->getChannels()['requests.{kind}.{id}'];
    $noPermission = catalogStaff($this->tenant, ['requests.brands.view']);

    $this->tenant->run(function () use ($callback, $own, $foreign, $noPermission): void {
        expect($callback($this->owner, 'product', (string) $own->id))->toBeTrue()
            ->and($callback($this->owner, 'product', (string) $foreign->id))->toBeFalse()
            ->and($callback($this->owner, 'unknown', (string) $own->id))->toBeFalse()
            ->and($callback($noPermission, 'product', (string) $own->id))->toBeFalse();
    });

    $this->postJson(($this->url)('/broadcasting/auth'), ['socket_id' => '1.1', 'channel_name' => 'private-requests.product.'.$own->id])->assertUnauthorized();
});

it('pays a payment request through the hosted checkout once, even with webhook replays', function (): void {
    vendorPaymentsGateway();
    fakeRequestStripe();
    $request = ManufacturingRequest::factory()->create(['tenant_id' => $this->tenant->id, 'status' => ManufacturingRequestStatus::AwaitingPayment]);
    $payment = app(RequestPaymentService::class)->create($request, new PaymentRequestData('Deposit 50%', 170000, now()->addDays(4)), null);

    ($this->as)()->get(($this->url)('/manufacturing/'.$request->id))->assertOk()->assertSee('Pay $1,700.00');

    ($this->as)()->post(($this->url)('/manufacturing/'.$request->id.'/payments/'.$payment->id.'/pay'), ['gateway' => 'paypal'])->assertSessionHasErrors('gateway');
    ($this->as)()->post(($this->url)('/manufacturing/'.$request->id.'/payments/'.$payment->id.'/pay'), ['gateway' => 'stripe'])
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_request');

    $log = PaymentLog::query()->withoutGlobalScopes()->sole();
    expect($log->purpose)->toBe(PaymentPurpose::Invoice)
        ->and($log->tenant_id)->toBe($this->tenant->id)
        ->and($log->invoice_id)->toBe($payment->invoice_id)
        ->and($log->amount_minor)->toBe(170000);

    $payload = requestStripeEvent('checkout.session.completed', ['id' => 'cs_test_request', 'payment_status' => 'paid'], 'evt_request_1');
    $send = fn () => $this->call('POST', route('central.webhooks.payments', 'stripe'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => stripeSignature($payload), 'CONTENT_TYPE' => 'application/json'], $payload);
    $send()->assertOk()->assertJson(['result' => 'processed']);
    $send()->assertOk()->assertJson(['result' => 'duplicate']);

    expect($log->fresh()->status)->toBe(PaymentLogStatus::Captured)
        ->and($payment->fresh()->status)->toBe(PaymentRequestStatus::Paid)
        ->and($payment->fresh()->invoice->status)->toBe(InvoiceStatus::Paid);

    ($this->as)()->get(($this->url)('/payments/'.$log->id.'/result'))
        ->assertOk()
        ->assertSee(__('catalog::vendor_requests.result.paid_title'))
        ->assertSee($payment->fresh()->invoice->number);

    // Another store's session cannot open this result page.
    $beta = $this->createVendorOwner($this->other);
    $this->actingAsVendor($beta, $this->other)->get($this->tenantUrl($this->other, 'admin/requests/payments/'.$log->id.'/result'))->assertNotFound();
});

it('shows the cancelled state without settling the payment', function (): void {
    vendorPaymentsGateway();
    fakeRequestStripe('unpaid');
    $request = BrandRequest::factory()->create(['tenant_id' => $this->tenant->id]);
    $payment = app(RequestPaymentService::class)->create($request, new PaymentRequestData('Brand fee', 85000), null);

    ($this->as)()->post(($this->url)('/brand/'.$request->id.'/payments/'.$payment->id.'/pay'), ['gateway' => 'stripe'])->assertRedirect();
    $log = PaymentLog::query()->withoutGlobalScopes()->sole();

    ($this->as)()->get(($this->url)('/payments/'.$log->id.'/cancel'))
        ->assertOk()
        ->assertSee(__('catalog::vendor_requests.result.cancelled_message'))
        ->assertSee(__('catalog::vendor_requests.result.try_again'));

    expect($payment->fresh()->status)->toBe(PaymentRequestStatus::Pending)
        ->and($log->fresh()->status)->toBe(PaymentLogStatus::Failed);
});

it('does not offer payments on product requests', function (): void {
    expect(RequestKind::Product->hasPayments())->toBeFalse();
    $request = ProductRequest::factory()->create(['tenant_id' => $this->tenant->id]);

    ($this->as)()->post(($this->url)('/product/'.$request->id.'/payments/1/pay'), ['gateway' => 'stripe'])->assertNotFound();
});

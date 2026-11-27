<?php

use App\Modules\Commerce\Notifications\OrderCustomerNotification;
use App\Modules\Integrations\Enums\IntegrationSection;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Models\CustomerDevice;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/MobileApiHelpers.php';

/*
| RQA-APP-014: customer inbox rows store the order mail-template variables (`order.number`)
| while the texts use `:order_number`. The mobile inbox and the push render the real values
| for new rows and for rows stored before the fix (dotted params).
*/

beforeEach(function (): void {
    Mail::fake();
    $this->tenant = $this->createTenant('shop');
    $this->customer = $this->createCustomer($this->tenant, ['locale' => 'ar']);
    Sanctum::actingAs($this->customer, ['api'], 'sanctum');
});

function deliveredNotification(): OrderCustomerNotification
{
    return new OrderCustomerNotification(EmailTemplateEvent::OrderDelivered, 'delivered', [
        'customer.name' => 'Sara',
        'store.name' => 'QA Store One',
        'order.number' => 'ORD-10904',
        'order.url' => 'https://shop.test/orders/1',
    ], Str::uuid()->toString());
}

it('renders a new order notification without placeholders in en and ar', function (): void {
    $this->tenant->run(fn () => $this->customer->notify(deliveredNotification()));

    $this->tenant->run(fn () => expect($this->customer->notifications()->sole()->data['params'])->toMatchArray(['order_number' => 'ORD-10904', 'store_name' => 'QA Store One']));

    $this->getJson(mobileApi('notifications'), mobileHeaders($this->tenant, null, ['X-Locale' => 'en']))
        ->assertOk()
        ->assertJsonPath('data.items.0.title', 'Order ORD-10904 delivered')
        ->assertJsonPath('data.items.0.body', __('commerce::notifications.customer.delivered.body', ['order_number' => 'ORD-10904'], 'en'));

    $arabic = $this->getJson(mobileApi('notifications'), mobileHeaders($this->tenant, null, ['X-Locale' => 'ar']))->assertOk()->json('data.items.0');

    expect($arabic['title'])->toBe('تم توصيل الطلب ORD-10904')
        ->and($arabic['body'])->toContain('ORD-10904')->not->toContain(':order_number');
});

it('renders legacy rows stored with dotted template variables', function (): void {
    $this->tenant->run(fn () => $this->customer->notifications()->create([
        'id' => Str::uuid()->toString(),
        'type' => OrderCustomerNotification::class,
        'data' => [
            'event' => 'delivered',
            'order_uuid' => Str::uuid()->toString(),
            'title_key' => 'commerce::notifications.customer.delivered.subject',
            'body_key' => 'commerce::notifications.customer.delivered.body',
            'params' => ['order.number' => 'ORD-10904', 'store.name' => 'QA Store One'],
        ],
    ]));

    $this->getJson(mobileApi('notifications'), mobileHeaders($this->tenant, null, ['X-Locale' => 'en']))
        ->assertOk()
        ->assertJsonPath('data.items.0.title', 'Order ORD-10904 delivered')
        ->assertJsonPath('data.items.0.body', __('commerce::notifications.customer.delivered.body', ['order_number' => 'ORD-10904'], 'en'));
});

it('pushes the rendered order text, not the placeholders', function (): void {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);
    $this->useSettings(IntegrationSection::Firebase, ['enabled' => true, 'project_id' => 'shop-app', 'service_account' => (string) json_encode([
        'type' => 'service_account', 'project_id' => 'shop-app', 'private_key_id' => 'kid-'.Str::random(6), 'private_key' => $pem, 'client_email' => 'push@shop-app.iam.gserviceaccount.com',
    ])]);
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.token', 'expires_in' => 3600]),
        'fcm.googleapis.com/*' => Http::response(['name' => 'projects/shop-app/messages/1']),
    ]);

    $this->tenant->run(function (): void {
        CustomerDevice::factory()->create(['customer_id' => $this->customer->id, 'push_token' => 'token-live']);
        $this->customer->notify(deliveredNotification());
    });

    Http::assertSent(fn (HttpRequest $request): bool => str_starts_with($request->url(), 'https://fcm.googleapis.com/')
        && $request['message']['notification']['title'] === 'تم توصيل الطلب ORD-10904'
        && str_contains($request['message']['notification']['body'], 'ORD-10904')
        && ! str_contains($request['message']['notification']['body'], ':order_number'));
});

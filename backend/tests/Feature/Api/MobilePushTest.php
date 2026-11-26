<?php

use App\Modules\Commerce\Notifications\OrderCustomerNotification;
use App\Modules\Integrations\Enums\IntegrationSection;
use App\Modules\Notifications\Models\CustomerDevice;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/*
| Customer push (P11): every customer inbox row goes to the customer's app installs through
| FCM HTTP v1 with the service account from Admin › Integrations › Firebase (DB, never
| .env), in the customer's language, only while the push switch of the type is on;
| rejected tokens are cleared.
*/

function pushServiceAccount(): string
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);

    return (string) json_encode(['type' => 'service_account', 'project_id' => 'shop-app', 'private_key_id' => 'kid-'.Str::random(6), 'private_key' => $pem, 'client_email' => 'push@shop-app.iam.gserviceaccount.com']);
}

beforeEach(function (): void {
    Mail::fake();
    $this->useSettings(IntegrationSection::Firebase, ['enabled' => true, 'project_id' => 'shop-app', 'service_account' => pushServiceAccount()]);
    $this->tenant = $this->createTenant('shop');
    $this->customer = $this->createCustomer($this->tenant, ['locale' => 'ar']);
});

it('pushes a customer notification to every install with a token, in the customer language', function (): void {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.token', 'expires_in' => 3600]),
        'fcm.googleapis.com/*' => Http::sequence()->push(['name' => 'projects/shop-app/messages/1'])->push(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404),
    ]);

    $this->tenant->run(function (): void {
        CustomerDevice::factory()->create(['customer_id' => $this->customer->id, 'push_token' => 'token-live']);
        CustomerDevice::factory()->create(['customer_id' => $this->customer->id, 'push_token' => 'token-dead']);
        CustomerDevice::factory()->create(['customer_id' => $this->customer->id, 'push_token' => null]);
        $this->customer->notify(new OrderCustomerNotification(null, 'confirmation', ['order_number' => '1001'], 'f1d0a7a6-0000-4000-8000-000000000001'));
    });

    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://fcm.googleapis.com/v1/projects/shop-app/messages:send'
        && $request->hasHeader('Authorization', 'Bearer ya29.token')
        && $request['message']['notification']['title'] === __('commerce::notifications.customer.confirmation.subject', ['order_number' => '1001'], 'ar')
        && $request['message']['data']['order_uuid'] === 'f1d0a7a6-0000-4000-8000-000000000001');
    Http::assertSentCount(3);

    $this->tenant->run(fn () => expect(CustomerDevice::query()->whereNotNull('push_token')->pluck('push_token')->all())->toBe(['token-live']));
});

it('respects the customer push switch of the notification type', function (): void {
    Http::fake();

    $this->tenant->run(function (): void {
        $this->customer->forceFill(['notification_preferences' => ['order_updates' => ['email' => true, 'push' => false]]])->save();
        CustomerDevice::factory()->create(['customer_id' => $this->customer->id]);
        $this->customer->notify(new OrderCustomerNotification(null, 'confirmation', ['order_number' => '1001'], 'f1d0a7a6-0000-4000-8000-000000000002'));
    });

    Http::assertNothingSent();
});

it('sends nothing while Firebase is not configured', function (): void {
    $this->useSettings(IntegrationSection::Firebase, ['enabled' => false]);
    config(['services.fcm.enabled' => false]);
    Http::fake();

    $this->tenant->run(function (): void {
        CustomerDevice::factory()->create(['customer_id' => $this->customer->id]);
        $this->customer->notify(new OrderCustomerNotification(null, 'confirmation', ['order_number' => '1001'], 'f1d0a7a6-0000-4000-8000-000000000003'));
    });

    Http::assertNothingSent();
});

<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Identity\Enums\CustomerNotificationChannel;
use App\Modules\Identity\Enums\CustomerNotificationType;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Services\CustomerNotificationPreferences;
use App\Modules\Notifications\Enums\PushDeliveryResult;
use App\Modules\Notifications\Repositories\Contracts\CustomerDeviceRepositoryContract;
use App\Modules\Notifications\Support\Push\FcmClient;
use App\Shared\Support\Locale;

/**
 * Mobile push of a customer's database notification (P11): every install of the customer
 * with a token and push on, in the customer's language, when the customer's push switch
 * of the notification type is on. Texts are the inbox row's title / body keys; the data
 * payload carries the inbox id + event + order uuid so the app can deep-link.
 */
class CustomerPushService
{
    public function __construct(
        private readonly CustomerDeviceRepositoryContract $devices,
        private readonly CustomerNotificationPreferences $preferences,
        private readonly FcmClient $fcm,
    ) {}

    /**
     * Notification type of an inbox payload `event` (order / shipping / return / support ticket events).
     */
    public static function typeOf(string $event): CustomerNotificationType
    {
        return match (true) {
            str_starts_with($event, 'return') => CustomerNotificationType::Returns,
            str_starts_with($event, 'ticket_') => CustomerNotificationType::Support,
            $event === 'back_in_stock' => CustomerNotificationType::BackInStock,
            str_starts_with($event, 'shipping_'), in_array($event, ['shipped', 'delivered', 'order_shipped', 'order_delivered'], true) => CustomerNotificationType::ShippingUpdates,
            default => CustomerNotificationType::OrderUpdates,
        };
    }

    /**
     * @param  array<string, mixed>  $payload  database notification data
     * @return int pushes accepted by FCM
     */
    public function sendNotification(Customer $customer, string $notificationId, array $payload): int
    {
        if (! $this->fcm->isConfigured()) {
            return 0;
        }

        $event = (string) ($payload['event'] ?? '');

        if (! $this->preferences->allows($customer, self::typeOf($event), CustomerNotificationChannel::Push)) {
            return 0;
        }

        $locale = Locale::isSupported($customer->locale) ? (string) $customer->locale : app()->getLocale();
        $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];
        $title = self::text($payload, 'title', $params, $locale);
        $body = self::text($payload, 'body', $params, $locale);

        if ($title === '') {
            return 0;
        }

        $data = array_filter([
            'notification_id' => $notificationId,
            'event' => $event,
            'order_uuid' => is_string($payload['order_uuid'] ?? null) ? $payload['order_uuid'] : null,
            'ticket_id' => is_int($payload['ticket_id'] ?? null) ? (string) $payload['ticket_id'] : null,
            'store_product_id' => is_int($payload['store_product_id'] ?? null) ? (string) $payload['store_product_id'] : null,
        ], static fn (?string $value): bool => $value !== null && $value !== '');

        return $this->sendToCustomer($customer, $title, $body, $data);
    }

    /**
     * @param  array<string, string>  $data
     */
    public function sendToCustomer(Customer $customer, string $title, string $body, array $data = []): int
    {
        $sent = 0;

        foreach ($this->devices->pushTargets($customer->id) as $device) {
            $result = $this->fcm->send((string) $device->push_token, $title, $body, $data);

            if ($result === PushDeliveryResult::InvalidToken) {
                $this->devices->clearToken((string) $device->push_token);
            }

            $sent += $result === PushDeliveryResult::Sent ? 1 : 0;
        }

        return $sent;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $params
     */
    private static function text(array $payload, string $field, array $params, string $locale): string
    {
        $key = $payload[$field.'_key'] ?? null;
        $params = is_array($payload[$field.'_params'] ?? null) ? $payload[$field.'_params'] : $params;

        if (! is_string($key) || $key === '') {
            return trim((string) ($payload[$field] ?? ''));
        }

        $text = __($key, $params, $locale);

        return is_string($text) && $text !== $key ? $text : '';
    }
}

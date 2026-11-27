<?php

namespace App\Modules\Commerce\Listeners;

use App\Modules\Commerce\Enums\FulfilmentStatus;
use App\Modules\Commerce\Events\OrderCancelled;
use App\Modules\Commerce\Events\OrderDelivered;
use App\Modules\Commerce\Events\OrderFulfilmentUpdated;
use App\Modules\Commerce\Events\OrderPaid;
use App\Modules\Commerce\Events\OrderShipped;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Notifications\OrderAlertNotification;
use App\Modules\Commerce\Notifications\OrderCustomerNotification;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Identity\Repositories\Contracts\CustomerRepositoryContract;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Services\NotificationDispatcher;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\StoreSettingsService;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Notification;

/**
 * Order events → notifications (shared Notifications module):
 *   paid      → customer confirmation (template order.confirmation), store owners
 *               "new order", admins with orders.view "order paid";
 *   shipped   → customer (order.shipped, tracking number);
 *   delivered → customer (order.delivered);
 *   cancelled → customer (order.cancelled), store owners, admins;
 *   out for delivery / delayed → customer (shipping updates preference).
 * The public notify* helpers are reused by AfterSales (returns, refunds) in the store's
 * tenant context.
 */
class SendOrderNotifications
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly AdminRepositoryContract $admins,
        private readonly CustomerRepositoryContract $customers,
        private readonly VendorStaffRepositoryContract $staff,
    ) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(OrderPaid::class, [self::class, 'paid']);
        $events->listen(OrderShipped::class, [self::class, 'shipped']);
        $events->listen(OrderDelivered::class, [self::class, 'delivered']);
        $events->listen(OrderCancelled::class, [self::class, 'cancelled']);
        $events->listen(OrderFulfilmentUpdated::class, [self::class, 'fulfilment']);
    }

    public function fulfilment(OrderFulfilmentUpdated $event): void
    {
        if ($event->notifyCustomer && in_array($event->status, [FulfilmentStatus::OutForDelivery, FulfilmentStatus::Delayed], true)) {
            $this->notifyCustomer($event->order, null, 'shipping_'.$event->status->value, [
                'shipment.company' => (string) $event->order->shipping_company_name,
                'shipment.tracking_no' => (string) $event->order->tracking_number,
            ]);
        }
    }

    public function paid(OrderPaid $event): void
    {
        $this->notifyCustomer($event->order, EmailTemplateEvent::OrderConfirmation, 'confirmation');
        $this->notifyStore($event->order, 'store_new_order');
        $this->notifyAdmins($event->order, 'admin_order_paid');
    }

    public function shipped(OrderShipped $event): void
    {
        $this->notifyCustomer($event->order, EmailTemplateEvent::OrderShipped, 'shipped', [
            'shipment.company' => (string) $event->order->shipping_company_name,
            'shipment.tracking_no' => (string) $event->order->tracking_number,
            'shipment.tracking_url' => '',
        ]);
    }

    public function delivered(OrderDelivered $event): void
    {
        // MAIL-015: the e-mail's button opens the review modal of the storefront order page.
        $this->notifyCustomer($event->order, EmailTemplateEvent::OrderDelivered, 'delivered', [
            'order.review_url' => $this->storeBaseUrl().route('storefront.orders.show', ['order' => $event->order->uuid, 'review' => 1], false),
        ]);
    }

    public function cancelled(OrderCancelled $event): void
    {
        $this->notifyCustomer($event->order, EmailTemplateEvent::OrderCancelled, 'cancelled');
        $this->notifyStore($event->order, 'store_order_cancelled');
        $this->notifyAdmins($event->order, 'admin_order_cancelled');
    }

    /**
     * @param  array<string, string>  $extra
     */
    public function notifyCustomer(Order $order, ?EmailTemplateEvent $template, string $name, array $extra = []): void
    {
        $customer = $order->customer_id !== null ? $this->customers->find($order->customer_id) : null;

        if (! $customer instanceof Customer) {
            return;
        }

        $tenant = tenant();
        $store = $tenant instanceof Tenant ? $tenant->name : '';
        $base = $this->storeBaseUrl();

        $customer->notify(new OrderCustomerNotification($template, $name, [
            'customer.first_name' => (string) strtok((string) $customer->name, ' '),
            'customer.name' => (string) $customer->name,
            'store.name' => (string) $store,
            'store.url' => $base,
            'order.number' => $order->number,
            'order.total' => $order->money('grand_total')->format(),
            'order.url' => $base.route('storefront.orders.show', ['order' => $order->uuid], false),
        ] + $extra, $order->uuid));
    }

    /**
     * Storefront base URL of the current store: links in customer e-mails must point to the
     * store even when the event fires from the admin panel (central request).
     */
    private function storeBaseUrl(): string
    {
        $tenant = tenant();

        return $tenant instanceof Tenant ? StoreSettingsService::storeUrl($tenant) : rtrim(url('/'), '/');
    }

    /**
     * @param  array<string, string|int>  $extra
     */
    public function notifyStore(Order $order, string $name, array $extra = []): void
    {
        // Owners plus every active member allowed to see the orders / returns (#124).
        $permission = str_starts_with($name, 'store_return') || str_starts_with($name, 'store_refund') ? 'returns.view' : 'orders.view';
        $recipients = $this->staff->activeOwners()->merge($this->staff->activeWithPermission($permission))->unique('id')->values();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new OrderAlertNotification($name, $this->params($order) + $extra, forStore: true));
        }
    }

    /**
     * Admins and their permissions live in the central DB: resolved and notified in the
     * central context.
     */
    /**
     * @param  array<string, string|int>  $extra
     */
    public function notifyAdmins(Order $order, string $name, string $permission = 'orders.view', array $extra = []): void
    {
        $notification = new OrderAlertNotification($name, $this->params($order) + $extra);
        $send = function () use ($notification, $permission): void {
            $admins = $this->admins->activeWithPermission($permission);

            if ($admins->isNotEmpty()) {
                $this->dispatcher->send($admins, $notification);
            }
        };

        tenancy()->initialized ? tenancy()->central($send) : $send();
    }

    /**
     * @return array<string, string>
     */
    private function params(Order $order): array
    {
        $tenant = tenant();

        return [
            'number' => $order->number,
            'total' => $order->money('grand_total')->format(),
            'store' => $tenant instanceof Tenant ? $tenant->name : '',
            'customer' => (string) $order->customer_name,
        ];
    }
}

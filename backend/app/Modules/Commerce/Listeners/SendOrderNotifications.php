<?php

namespace App\Modules\Commerce\Listeners;

use App\Modules\Commerce\Events\OrderCancelled;
use App\Modules\Commerce\Events\OrderDelivered;
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
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Notification;

/**
 * Order events → notifications (shared Notifications module):
 *   paid      → customer confirmation (template order.confirmation), store owners
 *               "new order", admins with orders.view "order paid";
 *   shipped   → customer (order.shipped, tracking number);
 *   delivered → customer (order.delivered);
 *   cancelled → customer (order.cancelled), store owners, admins.
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
    }

    public function paid(OrderPaid $event): void
    {
        $this->customer($event->order, EmailTemplateEvent::OrderConfirmation, 'confirmation');
        $this->store($event->order, 'store_new_order');
        $this->admins($event->order, 'admin_order_paid');
    }

    public function shipped(OrderShipped $event): void
    {
        $this->customer($event->order, EmailTemplateEvent::OrderShipped, 'shipped', [
            'shipment.company' => (string) $event->order->shipping_company_name,
            'shipment.tracking_no' => (string) $event->order->tracking_number,
            'shipment.tracking_url' => '',
        ]);
    }

    public function delivered(OrderDelivered $event): void
    {
        $this->customer($event->order, EmailTemplateEvent::OrderDelivered, 'delivered');
    }

    public function cancelled(OrderCancelled $event): void
    {
        $this->customer($event->order, EmailTemplateEvent::OrderCancelled, 'cancelled');
        $this->store($event->order, 'store_order_cancelled');
        $this->admins($event->order, 'admin_order_cancelled');
    }

    /**
     * @param  array<string, string>  $extra
     */
    private function customer(Order $order, EmailTemplateEvent $template, string $name, array $extra = []): void
    {
        $customer = $order->customer_id !== null ? $this->customers->find($order->customer_id) : null;

        if (! $customer instanceof Customer) {
            return;
        }

        $tenant = tenant();
        $store = $tenant instanceof Tenant ? $tenant->name : '';

        $customer->notify(new OrderCustomerNotification($template, $name, [
            'customer.first_name' => (string) strtok((string) $customer->name, ' '),
            'customer.name' => (string) $customer->name,
            'store.name' => (string) $store,
            'store.url' => url('/'),
            'order.number' => $order->number,
            'order.total' => $order->money('grand_total')->format(),
            'order.url' => url('/orders/'.$order->uuid),
        ] + $extra, $order->uuid));
    }

    private function store(Order $order, string $name): void
    {
        $owners = $this->staff->activeOwners();

        if ($owners->isNotEmpty()) {
            Notification::send($owners, new OrderAlertNotification($name, $this->params($order), forStore: true));
        }
    }

    /**
     * Admins and their permissions live in the central DB: resolved and notified in the
     * central context.
     */
    private function admins(Order $order, string $name): void
    {
        $notification = new OrderAlertNotification($name, $this->params($order));
        $send = function () use ($notification): void {
            $admins = $this->admins->activeWithPermission('orders.view');

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

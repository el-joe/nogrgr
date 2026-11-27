<?php

namespace App\Modules\Catalog\Listeners;

use App\Modules\Catalog\Events\ProductOfferedToStores;
use App\Modules\Catalog\Jobs\Requests\NotifyStoreStaff;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Notifications\RequestUpdateNotification;
use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Repositories\Contracts\OwnerRepositoryContract;
use App\Modules\Notifications\Services\NotificationDispatcher;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Tells every newly assigned store about a central product (old controller's
 * "New Product Added for You"): the owner (database + mail + Reverb, or e-mail to
 * owner_email when there is no owner account) and the store staff allowed to view
 * products (vendor bell, via NotifyStoreStaff), linking to the product under
 * Products › New products.
 */
class NotifyStoresOfOfferedProduct implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly OwnerRepositoryContract $owners,
    ) {}

    public function handle(ProductOfferedToStores $event): void
    {
        $product = Product::query()->find($event->productId);

        if (! $product instanceof Product) {
            return;
        }

        $tenants = Tenant::query()
            ->where('status', TenantStatus::Active)
            ->when(! $event->allStores, static fn ($query) => $query->whereKey($event->tenantIds))
            ->when($event->allStores && $event->exceptTenantIds !== [], static fn ($query) => $query->whereKeyNot($event->exceptTenantIds))
            ->get();

        foreach ($tenants as $tenant) {
            /** @var Tenant $tenant */
            $params = ['product' => (string) $product->name, 'sku' => (string) $product->sku, 'store' => (string) $tenant->name];
            $this->notifyOwner($tenant, $params);
            NotifyStoreStaff::dispatch((int) $tenant->id, 'catalog.products.view', 'product_assigned', $params, 'catalog.products.new.show', ['central' => $product->id]);
        }
    }

    /**
     * @param  array<string, string>  $params
     */
    private function notifyOwner(Tenant $tenant, array $params): void
    {
        $notification = new RequestUpdateNotification('product_assigned', $params, route('central.tenant.login'));
        $owner = $tenant->owner_id !== null ? $this->owners->find($tenant->owner_id) : null;

        if ($owner instanceof Owner) {
            $this->dispatcher->send($owner, $notification);

            return;
        }

        if (filled($tenant->owner_email)) {
            Notification::route('mail', (string) $tenant->owner_email)->notify($notification);
        }
    }
}

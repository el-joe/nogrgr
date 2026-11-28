<?php

namespace App\Modules\AfterSales\Services;

use App\Modules\Catalog\Repositories\Contracts\StoreProductRepositoryContract;
use App\Modules\Catalog\Services\Vendor\StoreCatalogReader;
use App\Modules\Commerce\Models\CartItem;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Services\Cart\CartLineResolver;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Replacement options of an exchange (old project parity, RETURN_EXCHANGE_REFUND_PLAN
 * B.6): the other options of the same product that sell at the same price as the ordered
 * one today and are in stock in the order's country. Priced and validated by the cart
 * line resolver, never here.
 */
class ExchangeOptionService extends BaseService
{
    public function __construct(
        private readonly StoreProductRepositoryContract $products,
        private readonly StoreCatalogReader $reader,
        private readonly CartLineResolver $lines,
    ) {}

    /**
     * @return array<int, string> variant id => label
     */
    public function forItem(Order $order, OrderItem $item): array
    {
        if ((int) $item->variant_key <= 0) {
            return [];
        }

        $product = $this->products->find((int) $item->store_product_id);

        if ($product === null) {
            return [];
        }

        $view = $this->reader->views(new Collection([$product]))[0] ?? null;

        if ($view === null) {
            return [];
        }

        $variantIds = $view->isOwn()
            ? $view->product->variants->where('is_active', true)->pluck('id')->map(static fn ($id): int => (int) $id)->all()
            : array_map(static fn ($variant): int => $variant->id(), array_values(collect($view->variants)->all()));

        if ($variantIds === []) {
            return [];
        }

        $candidates = array_map(static function (int $id) use ($product): CartItem {
            $line = new CartItem(['store_product_id' => $product->getKey(), 'variant_key' => $id, 'quantity' => 1]);
            $line->setRelation('storeProduct', $product);

            return $line;
        }, array_values(array_unique(array_merge($variantIds, [(int) $item->variant_key]))));

        $resolved = $this->lines->resolve($candidates, $order->country_id);
        $price = null;

        foreach ($resolved as $line) {
            if ((int) $line->item->variant_key === (int) $item->variant_key) {
                $price = $line->unitDisplayPrice?->minor;
            }
        }

        $price ??= (int) $item->price;
        $options = [];

        foreach ($resolved as $line) {
            $id = (int) $line->item->variant_key;

            if ($id !== (int) $item->variant_key && $line->issue === null && $line->unitDisplayPrice?->minor === $price) {
                $options[$id] = (string) ($line->variantLabel ?: '#'.$id);
            }
        }

        return $options;
    }
}

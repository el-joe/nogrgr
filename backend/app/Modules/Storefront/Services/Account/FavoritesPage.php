<?php

namespace App\Modules\Storefront\Services\Account;

use App\Modules\Engagement\Models\CustomerFavorite;
use App\Modules\Engagement\Services\FavoritesService;
use App\Modules\Identity\Models\Customer;
use App\Modules\Storefront\DTOs\Catalog\ProductCardData;
use App\Modules\Storefront\Services\Catalog\ProductCards;
use App\Modules\Storefront\Services\Catalog\ProductIndex;
use Illuminate\Support\Str;

/**
 * Account › Wishlist (SF-ECO-033): the customer's favorites (Engagement) as storefront
 * product cards, newest first. Products the store no longer sells in the visitor's
 * country are left out; `q` narrows by product name.
 */
class FavoritesPage
{
    public const LIMIT = 200;

    public function __construct(
        private readonly FavoritesService $favorites,
        private readonly ProductIndex $index,
        private readonly ProductCards $cards,
    ) {}

    /**
     * @return array{cards: list<ProductCardData>, total: int}
     */
    public function cards(Customer $customer, ?string $search = null): array
    {
        $term = Str::lower(trim((string) $search));
        $cards = [];
        $total = 0;

        foreach ($this->favorites->forCustomer($customer, self::LIMIT)->items() as $favorite) {
            /** @var CustomerFavorite $favorite */
            $entry = $this->index->entry($favorite->store_product_id);

            if ($entry === null) {
                continue;
            }

            $total++;

            if ($term === '' || Str::contains(Str::lower($entry->name), $term)) {
                $cards[] = $this->cards->card($entry);
            }
        }

        return ['cards' => $cards, 'total' => $total];
    }
}

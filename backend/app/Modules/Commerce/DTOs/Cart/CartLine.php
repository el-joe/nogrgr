<?php

namespace App\Modules\Commerce\DTOs\Cart;

use App\Modules\Catalog\DTOs\Vendor\StoreProductView;
use App\Modules\Commerce\DTOs\Promotions\FlashDiscount;
use App\Modules\Commerce\DTOs\Promotions\ProductRef;
use App\Modules\Commerce\Enums\CartLineIssue;
use App\Modules\Commerce\Models\CartItem;
use App\Modules\Commerce\Pricing\OrderLineInput;
use App\Shared\Money\Money;

/**
 * One resolved cart line: the item, the product as the store sells it (central ⊕
 * override, decision #65), its engine input (null when the line has an issue), stock and
 * flash sale. `available` = sellable units (null = stock not managed).
 */
final readonly class CartLine
{
    public function __construct(
        public CartItem $item,
        public ProductRef $ref,
        public ?StoreProductView $view,
        public string $name,
        public ?string $variantLabel,
        public ?string $sku,
        public ?string $imageUrl,
        public ?int $available,
        public ?FlashDiscount $flash,
        public ?OrderLineInput $input,
        public ?Money $unitDisplayPrice,
        public ?CartLineIssue $issue,
    ) {}

    public function key(): string
    {
        return $this->item->lineKey();
    }

    public function isOwn(): bool
    {
        return $this->view?->isOwn() ?? false;
    }

    public function isPurchasable(): bool
    {
        return $this->issue === null && $this->input !== null;
    }
}

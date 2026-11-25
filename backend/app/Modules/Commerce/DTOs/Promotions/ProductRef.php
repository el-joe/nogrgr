<?php

namespace App\Modules\Commerce\DTOs\Promotions;

use App\Modules\Catalog\Enums\StoreProductSource;

/**
 * Typed product identity inside a store: a central product (central id) or an own
 * product (store_products id).
 */
final readonly class ProductRef
{
    public function __construct(public StoreProductSource $source, public int $id) {}

    public static function central(int $id): self
    {
        return new self(StoreProductSource::Central, $id);
    }

    public static function own(int $id): self
    {
        return new self(StoreProductSource::Own, $id);
    }

    public function key(): string
    {
        return $this->source->value.':'.$this->id;
    }
}

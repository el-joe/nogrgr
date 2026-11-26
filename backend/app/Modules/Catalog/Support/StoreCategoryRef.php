<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Enums\StoreCategorySource;

/**
 * Reference to a category as the store sees it: "central:<central categories.id>" or
 * "store:<store_categories.id>" (sort payloads, position rows); routes use "central-5" / "store-3".
 */
final readonly class StoreCategoryRef
{
    public function __construct(public StoreCategorySource $source, public int $id) {}

    public static function central(int $id): self
    {
        return new self(StoreCategorySource::Central, $id);
    }

    public static function store(int $id): self
    {
        return new self(StoreCategorySource::Store, $id);
    }

    public static function tryParse(mixed $key): ?self
    {
        if (! is_string($key) || preg_match('/^(central|store)[:-]([1-9]\d{0,18})$/', $key, $match) !== 1) {
            return null;
        }

        return new self(StoreCategorySource::from($match[1]), (int) $match[2]);
    }

    public function key(): string
    {
        return $this->source->value.':'.$this->id;
    }

    /**
     * URL-safe form ("central-5" / "store-3") for route parameters.
     */
    public function routeKey(): string
    {
        return $this->source->value.'-'.$this->id;
    }

    public function isCentral(): bool
    {
        return $this->source === StoreCategorySource::Central;
    }
}

<?php

namespace App\Modules\Catalog\Enums;

use App\Modules\Catalog\Models\Requests\BrandRequest;
use App\Modules\Catalog\Models\Requests\ManufacturingRequest;
use App\Modules\Catalog\Models\Requests\ProductRequest;
use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Request types with a conversation thread (shared RequestThreadService). The value is
 * also the morph alias of the request model, the route segment and the broadcast channel
 * part ("requests.<kind>.<id>").
 */
enum RequestKind: string
{
    use HasEnumHelpers;

    case Product = 'product';
    case Manufacturing = 'manufacturing';
    case Brand = 'brand';

    /**
     * Permission resource ("requests.products" → .view / .manage).
     */
    public function permission(string $ability = 'view'): string
    {
        return match ($this) {
            self::Product => 'requests.products',
            self::Manufacturing => 'requests.manufacturing',
            self::Brand => 'requests.brands',
        }.'.'.$ability;
    }

    /**
     * Admin route name prefix ("admin.requests.products").
     */
    public function routePrefix(): string
    {
        return match ($this) {
            self::Product => 'admin.requests.products',
            self::Manufacturing => 'admin.requests.manufacturing',
            self::Brand => 'admin.requests.brands',
        };
    }

    /**
     * Display reference prefix ("PR-2041").
     */
    public function referencePrefix(): string
    {
        return match ($this) {
            self::Product => 'PR',
            self::Manufacturing => 'MR',
            self::Brand => 'BR',
        };
    }

    /**
     * Stable morph alias of the request model (catalog_request_messages.requestable_type).
     */
    public function morphAlias(): string
    {
        return $this->value.'_request';
    }

    /**
     * @return class-string<ProductRequest|ManufacturingRequest|BrandRequest>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Product => ProductRequest::class,
            self::Manufacturing => ManufacturingRequest::class,
            self::Brand => BrandRequest::class,
        };
    }

    /**
     * Kind of an admin route name ("admin.requests.manufacturing.status" → Manufacturing).
     */
    public static function fromRouteName(?string $name): ?self
    {
        foreach (self::cases() as $kind) {
            if ($name !== null && str_starts_with($name, $kind->routePrefix().'.')) {
                return $kind;
            }
        }

        return null;
    }

    public static function fromMorphAlias(string $alias): ?self
    {
        foreach (self::cases() as $kind) {
            if ($kind->morphAlias() === $alias) {
                return $kind;
            }
        }

        return null;
    }

    public function hasPayments(): bool
    {
        return $this !== self::Product;
    }
}

<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * What a tenant change request edits on the store (central tenants row).
 */
enum TenantChangeType: string
{
    use HasEnumHelpers;

    case Countries = 'countries';
    case Categories = 'categories';

    /**
     * Tenant attribute the approved id list is written to.
     */
    public function tenantAttribute(): string
    {
        return match ($this) {
            self::Countries => 'selling_country_ids',
            self::Categories => 'selling_category_ids',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Countries => 'info',
            self::Categories => 'brand',
        };
    }
}

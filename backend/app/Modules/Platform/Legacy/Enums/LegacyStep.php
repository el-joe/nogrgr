<?php

namespace App\Modules\Platform\Legacy\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * The steps of `php artisan legacy:migrate`, in run order. Central steps read the old
 * central DB; store steps run once per old store against its own DB (decision #110);
 * after-store central steps (after-sales) need the migrated orders (decision #113).
 */
enum LegacyStep: string
{
    use HasEnumHelpers;

    case Localization = 'localization';
    case Plans = 'plans';
    case Settings = 'settings';
    case Gateways = 'gateways';
    case Catalog = 'catalog';
    case Admins = 'admins';
    case Tenants = 'tenants';
    case Promotions = 'promotions';
    case Support = 'support';
    case Affiliates = 'affiliates';
    case Content = 'content';
    case EmailTemplates = 'email_templates';
    case Shipping = 'shipping';
    case Billing = 'billing';
    case Settlements = 'settlements';
    case Requests = 'requests';
    case StoreCatalog = 'store_catalog';
    case Customers = 'customers';
    case Orders = 'orders';
    case Engagement = 'engagement';
    case StorePromotions = 'store_promotions';
    case AfterSales = 'after_sales';
    case Finalize = 'finalize';

    public function isStore(): bool
    {
        return in_array($this, [self::StoreCatalog, self::Customers, self::Orders, self::Engagement, self::StorePromotions], true);
    }

    /**
     * Central steps that need the migrated store orders, so they run after the store steps.
     */
    public function afterStores(): bool
    {
        return $this === self::AfterSales;
    }

    /**
     * Central steps run before the store steps.
     *
     * @return list<self>
     */
    public static function central(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $step): bool => ! $step->isStore() && ! $step->afterStores() && $step !== self::Finalize));
    }

    /**
     * @return list<self>
     */
    public static function afterStore(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $step): bool => $step->afterStores()));
    }

    /**
     * @return list<self>
     */
    public static function store(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $step): bool => $step->isStore()));
    }
}

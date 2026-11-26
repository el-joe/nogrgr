<?php

namespace App\Modules\Support\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Vendor help center topics (VEN-092, Figma 88:112298) with their articles (slugs of
 * support::help.articles.*), in display order.
 */
enum HelpTopic: string
{
    use HasEnumHelpers;

    case GettingStarted = 'getting_started';
    case Products = 'products';
    case Orders = 'orders';
    case StoreDesign = 'store_design';
    case Marketing = 'marketing';
    case Finance = 'finance';
    case Settings = 'settings';
    case Support = 'support';

    public function icon(): string
    {
        return match ($this) {
            self::GettingStarted => 'zap',
            self::Products => 'box',
            self::Orders => 'cart',
            self::StoreDesign => 'palette',
            self::Marketing => 'megaphone',
            self::Finance => 'credit-card',
            self::Settings => 'settings',
            self::Support => 'lifebuoy',
        };
    }

    /**
     * @return list<string>
     */
    public function articles(): array
    {
        return match ($this) {
            self::GettingStarted => ['setup-checklist', 'how-your-store-works'],
            self::Products => ['import-central-products', 'markup-per-country', 'own-products', 'edit-requests'],
            self::Orders => ['order-lifecycle', 'returns-and-refunds'],
            self::StoreDesign => ['themes-and-home-variants', 'pages-banners-appearance'],
            self::Marketing => ['coupons', 'flash-sales'],
            self::Finance => ['subscription-and-invoices', 'settlements-and-payouts'],
            self::Settings => ['custom-domain', 'staff-and-roles', 'payment-gateways', 'languages-and-currencies'],
            self::Support => ['support-tickets'],
        };
    }

    public static function forArticle(string $slug): ?self
    {
        foreach (self::cases() as $topic) {
            if (in_array($slug, $topic->articles(), true)) {
                return $topic;
            }
        }

        return null;
    }
}

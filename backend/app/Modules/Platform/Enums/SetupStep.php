<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * The 8 steps of the vendor setup checklist (VEN-007b), in display order.
 */
enum SetupStep: string
{
    use HasEnumHelpers;

    case StoreDetails = 'store_details';
    case CountriesLanguages = 'countries_languages';
    case Theme = 'theme';
    case ImportProducts = 'import_products';
    case Logo = 'logo';
    case PaymentReadiness = 'payment_readiness';
    case Domain = 'domain';
    case Tour = 'tour';

    public function isOptional(): bool
    {
        return $this === self::Domain;
    }

    public function isSkippable(): bool
    {
        return $this === self::PaymentReadiness;
    }

    public function icon(): string
    {
        return match ($this) {
            self::StoreDetails => 'store',
            self::CountriesLanguages => 'globe',
            self::Theme => 'palette',
            self::ImportProducts => 'box',
            self::Logo => 'upload',
            self::PaymentReadiness => 'credit-card',
            self::Domain => 'link',
            self::Tour => 'sparkles',
        };
    }

    /**
     * Vendor panel route (without prefix) of the page that completes this step. Steps
     * whose page ships in a later phase only link once the route exists.
     */
    public function route(): ?string
    {
        return match ($this) {
            self::StoreDetails => 'settings.store.edit',
            self::CountriesLanguages => 'onboarding.setup',
            self::Theme => 'store.themes.index',
            self::ImportProducts => 'catalog.products.new',
            self::PaymentReadiness => 'settings.payment-gateways.index',
            self::Domain => 'settings.domains.index',
            self::Logo, self::Tour => null,
        };
    }
}

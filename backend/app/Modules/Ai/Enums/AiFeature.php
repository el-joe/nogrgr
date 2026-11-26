<?php

namespace App\Modules\Ai\Enums;

use App\Modules\Billing\Enums\PlanLimit;
use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * What an AI call was made for (AI usage log, Admin › Reports › AI usage).
 */
enum AiFeature: string
{
    use HasEnumHelpers;

    case StoreTranslation = 'store_translation';
    case DeltaTranslation = 'delta_translation';
    case ProductTranslation = 'product_translation';
    case CatalogTranslation = 'catalog_translation';
    case StaticKeyTranslation = 'static_key_translation';
    case StoreTextSuggestion = 'store_text_suggestion';
    case ImageIndex = 'image_index';
    case ImageSearch = 'image_search';
    case PriceFinder = 'price_finder';
    case SocialPost = 'social_post';
    case LogoGeneration = 'logo_generation';

    /**
     * Monthly plan quota a call of this feature counts against (null = not metered:
     * translations are bought per language, indexing is a platform cost).
     */
    public function planLimit(): ?PlanLimit
    {
        return match ($this) {
            self::ImageSearch => PlanLimit::ImageSearches,
            self::PriceFinder, self::SocialPost => PlanLimit::AiCalls,
            self::LogoGeneration => PlanLimit::AiLogos,
            default => null,
        };
    }

    /**
     * Features metered by a plan quota.
     *
     * @return list<self>
     */
    public static function meteredBy(PlanLimit $limit): array
    {
        return array_values(array_filter(self::cases(), static fn (self $feature): bool => $feature->planLimit() === $limit));
    }
}

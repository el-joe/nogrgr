<?php

namespace App\Modules\Ai\Enums;

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
}

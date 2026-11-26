<?php

namespace App\Modules\Storefront\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Locale switcher choices (SF-ECO-010): language, display currency, shopping country.
 */
final class StorefrontPreferencesData extends BaseDTO
{
    public function __construct(
        public readonly ?string $locale = null,
        public readonly ?string $currency = null,
        public readonly ?int $countryId = null,
    ) {}
}

<?php

namespace App\Modules\Localization\DTOs;

use App\Modules\Localization\Enums\Continent;
use App\Shared\DTOs\BaseDTO;

/**
 * Admin edit of an ISO country (ISO codes and flag are reference data and stay as seeded).
 */
final class CountryData extends BaseDTO
{
    /**
     * @param  array<string, string>  $name  locale => name
     */
    public function __construct(
        public readonly array $name,
        public readonly ?string $phoneCode,
        public readonly ?string $currencyCode,
        public readonly ?Continent $continent,
        public readonly bool $isActive,
    ) {}
}

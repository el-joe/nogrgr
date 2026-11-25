<?php

namespace App\Modules\Localization\DTOs;

use App\Modules\Localization\Enums\TextDirection;
use App\Shared\DTOs\BaseDTO;

/**
 * Admin create / edit of a central language. `price` is the one USD purchase price as a
 * decimal string ("49.00"); the service turns it into minor units (ignored for ar / en).
 * `countryIds` null = offered in all countries.
 */
final class LanguageData extends BaseDTO
{
    /**
     * @param  array<string, string>  $name  locale => name
     * @param  list<int|string>|null  $countryIds
     */
    public function __construct(
        public readonly string $code,
        public readonly array $name,
        public readonly string $nativeName,
        public readonly TextDirection $direction,
        public readonly ?string $flag,
        public readonly bool $isActive,
        public readonly ?string $price,
        public readonly ?array $countryIds = null,
        public readonly int $sortOrder = 0,
    ) {}
}

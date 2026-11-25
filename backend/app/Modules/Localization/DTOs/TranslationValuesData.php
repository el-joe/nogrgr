<?php

namespace App\Modules\Localization\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Values of one translation key per locale (ADM-092 edit / add key). An empty value
 * clears the admin's override for that locale.
 */
final class TranslationValuesData extends BaseDTO
{
    /**
     * @param  array<string, string|null>  $values  locale => value
     */
    public function __construct(
        public readonly string $file,
        public readonly string $key,
        public readonly array $values,
    ) {}
}

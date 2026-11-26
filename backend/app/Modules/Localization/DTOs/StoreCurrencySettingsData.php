<?php

namespace App\Modules\Localization\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Vendor › Settings › Currencies (VEN-072): the store's switched-on currency codes and the
 * codes shown by default to shoppers of their countries. Display only — every charge is USD.
 */
final class StoreCurrencySettingsData extends BaseDTO
{
    /**
     * @param  list<string>  $active
     * @param  list<string>  $default
     */
    public function __construct(
        public readonly array $active,
        public readonly array $default,
    ) {}
}

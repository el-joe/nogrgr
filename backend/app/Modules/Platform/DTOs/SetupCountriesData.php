<?php

namespace App\Modules\Platform\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Wizard step 1: the countries the store sells to and its default country (one of them).
 */
final class SetupCountriesData extends BaseDTO
{
    /**
     * @param  list<int>  $countryIds
     */
    public function __construct(
        public readonly array $countryIds,
        public readonly int $defaultCountryId,
        public readonly ?string $submittedBy = null,
    ) {}
}

<?php

namespace App\Modules\Storefront\DTOs;

/**
 * Activate a catalogue theme from the vendor themes page (VEN-030 / 030a): as the store
 * default, for some selling countries, or both.
 */
final readonly class ThemePageActivationData
{
    /**
     * @param  list<int>  $countryIds
     */
    public function __construct(
        public string $slug,
        public bool $asDefault,
        public array $countryIds = [],
    ) {}
}

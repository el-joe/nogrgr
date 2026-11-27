<?php

namespace App\Modules\Storefront\DTOs;

/**
 * Activate a catalogue theme (by id) from the vendor themes page (VEN-030 / 030a): as the
 * store default, for some selling countries, or both.
 */
final readonly class ThemePageActivationData
{
    /**
     * @param  list<int>  $countryIds
     */
    public function __construct(
        public int $themeId,
        public bool $asDefault,
        public array $countryIds = [],
    ) {}
}

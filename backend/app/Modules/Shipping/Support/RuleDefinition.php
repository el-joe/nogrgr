<?php

namespace App\Modules\Shipping\Support;

/**
 * Pure shipping rule of one country: its zones in admin order.
 */
final readonly class RuleDefinition
{
    /**
     * @param  list<ZoneDefinition>  $zones
     */
    public function __construct(
        public string $name,
        public array $zones,
        public ?int $id = null,
    ) {}

    /**
     * Zone resolution: an explicit zone id must belong to the rule; else the zone that lists
     * the city; else the first whole-country zone; with no city at all, the first zone.
     * Null = no zone serves the destination (shipping unavailable).
     */
    public function zoneFor(?int $cityId = null, ?int $zoneId = null): ?ZoneDefinition
    {
        if ($zoneId !== null) {
            foreach ($this->zones as $zone) {
                if ($zone->id === $zoneId) {
                    return $zone;
                }
            }

            return null;
        }

        if ($cityId !== null) {
            foreach ($this->zones as $zone) {
                if (in_array($cityId, $zone->cityIds, true)) {
                    return $zone;
                }
            }
        }

        foreach ($this->zones as $zone) {
            if ($zone->coversWholeCountry()) {
                return $zone;
            }
        }

        return $cityId === null ? ($this->zones[0] ?? null) : null;
    }
}

<?php

namespace App\Modules\Catalog\Enums;

use App\Modules\Integrations\Enums\IntegrationSection;
use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * External catalogs the importer can read (ADM-013). The value is also stored as
 * `external_source` on imported products, attributes and options.
 */
enum ImportDriver: string
{
    use HasEnumHelpers;

    case Neozena = 'neozena';

    public function label(): string
    {
        return __('catalog::imports.drivers.'.$this->value);
    }

    /**
     * Integrations card that holds the driver's credentials.
     */
    public function integrationSection(): IntegrationSection
    {
        return match ($this) {
            self::Neozena => IntegrationSection::Neozena,
        };
    }
}

<?php

namespace App\Modules\Catalog\Exceptions;

use DomainException;

/**
 * A vendor catalog rule refused the action (product not offered to the store, nothing to
 * import, no selling countries…). Answered as 422 by AnswersDomainErrors.
 */
class StoreCatalogException extends DomainException
{
    public static function notOffered(): self
    {
        return new self(__('catalog::vendor.errors.not_offered'));
    }

    public static function nothingToImport(): self
    {
        return new self(__('catalog::vendor.errors.nothing_to_import'));
    }

    public static function marketNotSold(): self
    {
        return new self(__('catalog::vendor.errors.market_not_sold'));
    }

    public static function noMarkets(): self
    {
        return new self(__('catalog::vendor.errors.no_markets'));
    }

    public static function staleSort(): self
    {
        return new self(__('catalog::vendor.errors.stale_sort'));
    }
}

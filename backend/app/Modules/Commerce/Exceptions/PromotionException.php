<?php

namespace App\Modules\Commerce\Exceptions;

use RuntimeException;

/**
 * Domain errors of the promotions layer (translated message).
 */
final class PromotionException extends RuntimeException
{
    public static function storeContextRequired(): self
    {
        return new self(__('commerce::promotions.errors.store_context'));
    }
}

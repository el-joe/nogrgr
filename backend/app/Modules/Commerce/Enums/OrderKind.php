<?php

namespace App\Modules\Commerce\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Which part of a split cart an order is (OrderComposer): the catalog order (central
 * products, pays the shipping) or the own-products companion order (merchant-fulfilled).
 * An own-only cart is a single `own` order.
 */
enum OrderKind: string
{
    use HasEnumHelpers;

    case Catalog = 'catalog';
    case Own = 'own';
}

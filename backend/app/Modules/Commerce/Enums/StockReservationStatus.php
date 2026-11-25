<?php

namespace App\Modules\Commerce\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Stock hold of one order line (central stock_reservations):
 *   reserved  → held while the order waits for payment (expires);
 *   committed → paid central stock, held until a branch fulfils it;
 *   deducted  → paid own-product stock, already subtracted from the store's stock;
 *   released  → failed / cancelled / expired, no longer held.
 */
enum StockReservationStatus: string
{
    use HasEnumHelpers;

    case Reserved = 'reserved';
    case Committed = 'committed';
    case Deducted = 'deducted';
    case Released = 'released';
}

<?php

namespace App\Modules\AfterSales\Events;

use App\Modules\AfterSales\Enums\ReturnStatus;
use App\Modules\AfterSales\Models\ReturnRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A return was opened (from = null) or moved in its state machine.
 */
final class ReturnStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly ReturnRequest $return,
        public readonly ?ReturnStatus $from,
        public readonly ReturnStatus $to,
        public readonly ?string $message = null,
    ) {}
}

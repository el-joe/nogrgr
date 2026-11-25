<?php

namespace App\Modules\AfterSales\DTOs;

use App\Modules\AfterSales\Enums\InspectionResult;
use App\Modules\AfterSales\Enums\ReturnAction;
use App\Shared\DTOs\BaseDTO;
use App\Shared\Money\Money;
use Illuminate\Http\UploadedFile;

/**
 * One return transition (ADM-023a / VEN-022a): the note / message and the fields of the
 * inspect, refund and ship-exchange modals.
 */
final class ReturnActionData extends BaseDTO
{
    /**
     * @param  list<UploadedFile>  $photos
     */
    public function __construct(
        public readonly ReturnAction $action,
        public readonly ?string $note = null,
        public readonly ?InspectionResult $result = null,
        public readonly ?string $condition = null,
        public readonly bool $restock = false,
        public readonly ?int $branchId = null,
        public readonly ?Money $amount = null,
        public readonly bool $thenRefund = false,
        public readonly ?string $trackingNumber = null,
        public readonly array $photos = [],
    ) {}
}

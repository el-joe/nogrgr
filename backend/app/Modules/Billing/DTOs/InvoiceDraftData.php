<?php

namespace App\Modules\Billing\DTOs;

use App\Modules\Billing\Enums\InvoiceType;
use App\Shared\DTOs\BaseDTO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Everything needed to issue an invoice. Amounts are USD minor units.
 */
final class InvoiceDraftData extends BaseDTO
{
    /**
     * @param  list<InvoiceLineData>  $lines
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly InvoiceType $type,
        public readonly array $lines,
        public readonly ?Model $billable = null,
        public readonly ?int $taxRateBp = null,
        public readonly ?Carbon $dueAt = null,
        public readonly ?string $billToName = null,
        public readonly ?string $billToEmail = null,
    ) {}
}

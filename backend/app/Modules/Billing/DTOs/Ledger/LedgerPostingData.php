<?php

namespace App\Modules\Billing\DTOs\Ledger;

use App\Modules\Billing\Enums\LedgerDirection;
use App\Modules\Billing\Enums\LedgerSource;
use App\Shared\DTOs\BaseDTO;
use Carbon\CarbonInterface;

/**
 * A balanced posting for one source event (decision #72). `idempotencyKey` makes a
 * replayed event a no-op; `direction` / `amount` default to the sign / size of the
 * store net change; `amount` is set for net-zero events (a paid invoice).
 */
final class LedgerPostingData extends BaseDTO
{
    /**
     * @param  list<LedgerLine>  $lines
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly LedgerSource $source,
        public readonly string $idempotencyKey,
        public readonly string $description,
        public readonly array $lines,
        public readonly ?string $sourceReference = null,
        public readonly ?string $orderUuid = null,
        public readonly ?LedgerDirection $direction = null,
        public readonly ?int $amount = null,
        public readonly array $meta = [],
        public readonly ?CarbonInterface $occurredAt = null,
        public readonly ?int $createdById = null,
    ) {}
}

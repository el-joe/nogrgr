<?php

namespace App\Modules\Billing\DTOs\Ledger;

/**
 * Outcome of `finance:backfill-ledger` (decision #74 d). "posted" = transactions written
 * (or, in a dry run, that would be written); "existing" = the idempotency key is already in
 * the ledger; "unchanged" = the ledger already matches the formulas for that order.
 */
final class LedgerBackfillReport
{
    public int $stores = 0;

    public int $ordersPosted = 0;

    public int $ordersExisting = 0;

    public int $ordersUnchanged = 0;

    /** Orders already in the ledger that got a correcting posting. */
    public int $ordersCorrected = 0;

    public int $settlementsPosted = 0;

    public int $settlementsExisting = 0;

    public int $invoicesPosted = 0;

    public int $invoicesExisting = 0;

    /** @var array<int, string> store id => error message */
    public array $failures = [];

    public function __construct(public readonly bool $dryRun) {}

    public function posted(): int
    {
        return $this->ordersPosted + $this->ordersCorrected + $this->settlementsPosted + $this->invoicesPosted;
    }
}

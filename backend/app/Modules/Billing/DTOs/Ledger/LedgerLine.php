<?php

namespace App\Modules\Billing\DTOs\Ledger;

use App\Modules\Billing\Enums\LedgerAccount;
use App\Shared\DTOs\BaseDTO;

/**
 * One debit or credit line of a posting (USD minor, never negative).
 */
final class LedgerLine extends BaseDTO
{
    public function __construct(
        public readonly LedgerAccount $account,
        public readonly int $debit = 0,
        public readonly int $credit = 0,
        public readonly ?string $orderUuid = null,
        public readonly ?string $orderNumber = null,
    ) {}

    public static function debit(LedgerAccount $account, int $amount, ?string $orderUuid = null, ?string $orderNumber = null): self
    {
        return new self($account, debit: $amount, orderUuid: $orderUuid, orderNumber: $orderNumber);
    }

    public static function credit(LedgerAccount $account, int $amount, ?string $orderUuid = null, ?string $orderNumber = null): self
    {
        return new self($account, credit: $amount, orderUuid: $orderUuid, orderNumber: $orderNumber);
    }

    /**
     * A signed change of an account: positive increases its natural side (payable /
     * revenue: credit; receivable / cash: debit), negative the other side.
     */
    public static function change(LedgerAccount $account, int $delta, ?string $orderUuid = null, ?string $orderNumber = null): self
    {
        $creditNatural = in_array($account, [LedgerAccount::VendorPayable, LedgerAccount::PlatformRevenue], true);

        return ($delta >= 0) === $creditNatural
            ? self::credit($account, abs($delta), $orderUuid, $orderNumber)
            : self::debit($account, abs($delta), $orderUuid, $orderNumber);
    }
}

<?php

namespace App\Modules\Billing\Services\Ledger;

use App\Modules\Billing\DTOs\Ledger\LedgerLine;
use App\Modules\Billing\DTOs\Ledger\LedgerPostingData;
use App\Modules\Billing\Enums\LedgerAccount;
use App\Modules\Billing\Enums\LedgerDirection;
use App\Modules\Billing\Models\LedgerTransaction;
use App\Modules\Billing\Repositories\Contracts\LedgerRepositoryContract;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;

/**
 * The only writer of the central ledger (decision #72). A posting is a balanced set of
 * debit / credit lines for one source event, idempotent by its key (a replayed event
 * returns the first transaction). Postings of a store are serialised (store row lock) so
 * `balance_after` is the exact running net.
 *
 * Store net = vendor_payable (credit − debit, the platform owes the store)
 *           − vendor_receivable (debit − credit, the store owes the platform).
 */
class LedgerService extends BaseService
{
    public function __construct(private readonly LedgerRepositoryContract $ledger) {}

    public function post(LedgerPostingData $data): ?LedgerTransaction
    {
        $existing = $this->ledger->findByKey($data->idempotencyKey);

        if ($existing !== null) {
            return $existing;
        }

        $lines = array_values(array_filter($data->lines, static fn (LedgerLine $line): bool => $line->debit !== 0 || $line->credit !== 0));
        $debits = array_sum(array_map(static fn (LedgerLine $line): int => $line->debit, $lines));
        $credits = array_sum(array_map(static fn (LedgerLine $line): int => $line->credit, $lines));

        foreach ($lines as $line) {
            if ($line->debit < 0 || $line->credit < 0) {
                throw new InvalidArgumentException('Ledger lines are never negative.');
            }
        }

        if ($debits !== $credits) {
            throw new InvalidArgumentException("Unbalanced ledger posting {$data->idempotencyKey}: debit {$debits} ≠ credit {$credits}.");
        }

        if ($lines === []) {
            return null;
        }

        $net = self::netChange($lines);

        try {
            return $this->transaction(function () use ($data, $lines, $net): LedgerTransaction {
                $previous = $this->ledger->lockedBalance($data->tenantId);
                $again = $this->ledger->findByKey($data->idempotencyKey);

                if ($again !== null) {
                    return $again;
                }

                return $this->ledger->record([
                    'tenant_id' => $data->tenantId,
                    'source' => $data->source,
                    'source_reference' => $data->sourceReference,
                    'order_uuid' => $data->orderUuid,
                    'direction' => $data->direction ?? ($net >= 0 ? LedgerDirection::Credit : LedgerDirection::Debit),
                    'amount' => $data->amount ?? abs($net),
                    'net_amount' => $net,
                    'balance_after' => $previous + $net,
                    'currency' => Money::BASE_CURRENCY,
                    'description' => mb_substr($data->description, 0, 255),
                    'meta' => $data->meta === [] ? null : $data->meta,
                    'idempotency_key' => $data->idempotencyKey,
                    'created_by_id' => $data->createdById,
                    'occurred_at' => $data->occurredAt ?? now(),
                ], array_map(static fn (LedgerLine $line): array => [
                    'tenant_id' => $data->tenantId,
                    'order_uuid' => $line->orderUuid,
                    'order_number' => $line->orderNumber,
                    'account' => $line->account,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                ], $lines));
            }, (new LedgerTransaction)->getConnectionName());
        } catch (UniqueConstraintViolationException) {
            return $this->ledger->findByKey($data->idempotencyKey);
        }
    }

    /**
     * @return array{payable: int, receivable: int, net: int}
     */
    public function balanceOf(int $tenantId): array
    {
        $balance = $this->ledger->balances([$tenantId])[$tenantId] ?? ['payable' => 0, 'receivable' => 0];

        return $balance + ['net' => $balance['payable'] - $balance['receivable']];
    }

    /**
     * Signed effect of lines on the store net.
     *
     * @param  list<LedgerLine>  $lines
     */
    public static function netChange(array $lines): int
    {
        $net = 0;

        foreach ($lines as $line) {
            $net += match ($line->account) {
                LedgerAccount::VendorPayable => $line->credit - $line->debit,
                LedgerAccount::VendorReceivable => $line->credit - $line->debit,
                default => 0,
            };
        }

        return $net;
    }
}

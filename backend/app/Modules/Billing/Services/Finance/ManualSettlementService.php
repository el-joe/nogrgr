<?php

namespace App\Modules\Billing\Services\Finance;

use App\Modules\Billing\DTOs\Finance\FinanceActor;
use App\Modules\Billing\DTOs\Finance\ManualSettlementData;
use App\Modules\Billing\Enums\SettlementMethod;
use App\Modules\Billing\Exceptions\FinanceException;
use App\Modules\Billing\Repositories\Contracts\LedgerRepositoryContract;
use App\Modules\Commerce\Enums\SettlementStatus;
use App\Modules\Commerce\Events\SettlementPaid;
use App\Modules\Commerce\Models\OrderSettlement;
use App\Modules\Commerce\Repositories\Contracts\OrderSettlementRepositoryContract;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;
use Illuminate\Support\Collection;

/**
 * "Mark settlement paid" (ADM-050a, decision #72): the store paid the platform outside
 * the gateway. The amount (≤ what the store owes) is applied to its open orders oldest
 * first; each order gets a paid `order_settlements` row sharing one ST- reference (so
 * OrderSettlement's Σ paid settlements, the vendor's Pay platform page and the ledger
 * all agree). A payout offset may not exceed what the platform owes the store.
 */
class ManualSettlementService extends BaseService
{
    public const DISK = 'central';

    public function __construct(
        private readonly LedgerRepositoryContract $ledger,
        private readonly OrderSettlementRepositoryContract $settlements,
        private readonly PayoutService $payouts,
        private readonly StoreFinanceNotifier $notifier,
    ) {}

    public function owed(int $tenantId): int
    {
        return (int) $this->ledger->openReceivables($tenantId)->sum('owed');
    }

    /**
     * @return Collection<int, OrderSettlement>
     */
    public function markPaid(int $tenantId, ManualSettlementData $data, FinanceActor $actor): Collection
    {
        $open = $this->ledger->openReceivables($tenantId);
        $owed = (int) $open->sum('owed');

        if ($owed <= 0) {
            throw FinanceException::rule('nothing_to_settle');
        }

        if ($data->amount <= 0 || $data->amount > $owed) {
            throw FinanceException::rule('settlement_exceeds', 422, ['owed' => Money::ofMinor($owed)->format()]);
        }

        if ($data->method === SettlementMethod::PayoutOffset && $data->amount > $this->payouts->available($tenantId)) {
            throw FinanceException::rule('offset_exceeds', 422, ['available' => Money::ofMinor($this->payouts->available($tenantId))->format()]);
        }

        if ($data->method->needsReference() && trim((string) $data->reference) === '') {
            throw FinanceException::rule('reference_required');
        }

        $attachment = null;

        if ($data->attachment !== null) {
            $stored = $data->attachment->store('settlements/'.$tenantId, self::DISK);
            $attachment = $stored === false ? null : $stored;
        }

        $reference = $this->settlements->nextReference();

        /** @var Collection<int, OrderSettlement> $rows */
        $rows = $this->transaction(function () use ($tenantId, $data, $actor, $open, $reference, $attachment): Collection {
            $left = $data->amount;
            $rows = collect();

            foreach ($open as $order) {
                if ($left <= 0) {
                    break;
                }

                $part = min($left, $order['owed']);
                $left -= $part;

                $rows->push($this->settlements->create([
                    'reference' => $reference,
                    'tenant_id' => $tenantId,
                    'order_uuid' => $order['uuid'],
                    'order_number' => (string) $order['number'],
                    'amount' => $part,
                    'shipping_cost' => 0,
                    'product_cost' => $part,
                    'currency' => Money::BASE_CURRENCY,
                    'gateway' => 'manual',
                    'method' => $data->method->value,
                    'transaction_reference' => $data->method->needsReference() ? trim((string) $data->reference) : $reference,
                    'note' => $data->note,
                    'attachment_path' => $attachment,
                    'attachment_name' => $data->attachment !== null ? mb_substr($data->attachment->getClientOriginalName(), 0, 255) : null,
                    'status' => SettlementStatus::Paid,
                    'paid_by_id' => $actor->id,
                    'recorded_by_name' => $actor->name,
                    'paid_at' => now(),
                ]));
            }

            return $rows;
        }, (new OrderSettlement)->getConnectionName());

        foreach ($rows as $row) {
            SettlementPaid::dispatch($row);
        }

        $this->notifier->notify($tenantId, 'settlement_confirmed', [
            'reference' => $reference,
            'amount' => Money::ofMinor($data->amount)->format(),
        ], 'finance.settlements.index');

        return $rows;
    }
}

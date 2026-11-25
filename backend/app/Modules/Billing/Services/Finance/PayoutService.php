<?php

namespace App\Modules\Billing\Services\Finance;

use App\Modules\Billing\DTOs\Finance\FinanceActor;
use App\Modules\Billing\DTOs\Finance\PayoutData;
use App\Modules\Billing\DTOs\Finance\PayoutUpdateData;
use App\Modules\Billing\Enums\PayoutStatus;
use App\Modules\Billing\Exceptions\FinanceException;
use App\Modules\Billing\Models\TenantPayout;
use App\Modules\Billing\Repositories\Contracts\TenantPayoutRepositoryContract;
use App\Modules\Billing\Services\Ledger\FinancePostings;
use App\Modules\Billing\Services\Ledger\LedgerService;
use App\Modules\Platform\Models\ComplianceProfile;
use App\Modules\Platform\Services\ComplianceService;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;

/**
 * Platform → store payouts (ADM-052 / 052a / 052b, decision #72). A payout may not exceed
 * what the platform owes the store minus payouts already open; paying it posts the
 * ledger debit, cancelling a paid payout reverses it. Owners are notified when the admin
 * ticks "email the vendor".
 */
class PayoutService extends BaseService
{
    public function __construct(
        private readonly TenantPayoutRepositoryContract $payouts,
        private readonly LedgerService $ledger,
        private readonly FinancePostings $postings,
        private readonly ComplianceService $compliance,
        private readonly StoreFinanceNotifier $notifier,
    ) {}

    /**
     * What may still be paid out: vendor_payable − open payouts (never negative).
     */
    public function available(int $tenantId): int
    {
        return max(0, $this->ledger->balanceOf($tenantId)['payable'] - $this->payouts->openTotal($tenantId));
    }

    /**
     * Bank details on file (KYC profile), masked.
     *
     * @return array{bank: string|null, iban: string|null, name: string|null, verified: bool}|null
     */
    public function payee(int $tenantId): ?array
    {
        $profile = $this->compliance->forTenant($tenantId);

        if (! $profile instanceof ComplianceProfile) {
            return null;
        }

        return [
            'bank' => $profile->bank_name,
            'iban' => $profile->maskedIban(),
            'name' => $profile->account_name ?? $profile->legal_name,
            'verified' => $profile->status->value === 'verified',
        ];
    }

    public function create(PayoutData $data, FinanceActor $actor): TenantPayout
    {
        if ($data->amount <= 0) {
            throw FinanceException::rule('amount_positive');
        }

        $available = $this->available($data->tenantId);

        if ($data->amount > $available) {
            throw FinanceException::rule('payout_exceeds', 422, ['available' => Money::ofMinor($available)->format()]);
        }

        /** @var TenantPayout $payout */
        $payout = $this->transaction(function () use ($data, $actor): TenantPayout {
            /** @var TenantPayout $payout */
            $payout = $this->payouts->create([
                'tenant_id' => $data->tenantId,
                'amount' => $data->amount,
                'currency' => Money::BASE_CURRENCY,
                'method' => $data->method,
                'status' => PayoutStatus::Scheduled,
                'scheduled_for' => $data->scheduledFor,
                'transaction_reference' => $data->transactionReference,
                'note' => $data->note,
                'notify_vendor' => $data->notifyVendor,
                'payee' => $this->payee($data->tenantId),
                'created_by_id' => $actor->id,
                'created_by_name' => $actor->name,
            ]);

            return $this->payouts->update($payout, ['reference' => 'PO-'.(880 + $payout->id)]);
        }, (new TenantPayout)->getConnectionName());

        if ($payout->notify_vendor) {
            $this->notify($payout, 'payout_scheduled');
        }

        return $payout;
    }

    public function update(TenantPayout $payout, PayoutUpdateData $data): TenantPayout
    {
        [$payout, $from] = $this->transaction(function () use ($payout, $data): array {
            $payout = $this->payouts->lock($payout);
            $from = $payout->status;

            if ($from === PayoutStatus::Cancelled) {
                throw FinanceException::rule('payout_cancelled');
            }

            if ($data->status !== $from && ! $from->canTransitionTo($data->status)) {
                throw FinanceException::rule('payout_transition', 422, ['from' => $from->label(), 'to' => $data->status->label()]);
            }

            $attributes = ['transaction_reference' => $data->transactionReference, 'status' => $data->status];

            if ($data->status === PayoutStatus::Paid && $from !== PayoutStatus::Paid) {
                if ($payout->amount > $this->ledger->balanceOf($payout->tenant_id)['payable']) {
                    throw FinanceException::rule('payout_exceeds', 422, ['available' => Money::ofMinor(max(0, $this->ledger->balanceOf($payout->tenant_id)['payable']))->format()]);
                }

                $attributes['paid_at'] = now();
            }

            if ($data->status === PayoutStatus::Cancelled) {
                $attributes['cancelled_at'] = now();
            }

            return [$this->payouts->update($payout, $attributes), $from];
        }, (new TenantPayout)->getConnectionName());

        if ($payout->status !== $from) {
            $this->afterTransition($payout, $from);
        }

        return $payout;
    }

    public function cancel(TenantPayout $payout): TenantPayout
    {
        return $this->update($payout, new PayoutUpdateData(PayoutStatus::Cancelled, $payout->transaction_reference));
    }

    private function afterTransition(TenantPayout $payout, PayoutStatus $from): void
    {
        if ($payout->status === PayoutStatus::Paid) {
            $this->postings->payoutPaid($payout);
            $this->notify($payout, 'payout_paid');
        }

        if ($payout->status === PayoutStatus::Cancelled) {
            if ($from === PayoutStatus::Paid) {
                $this->postings->payoutCancelled($payout);
            }

            $this->notify($payout, 'payout_cancelled');
        }
    }

    private function notify(TenantPayout $payout, string $event): void
    {
        if (! $payout->notify_vendor) {
            return;
        }

        $this->notifier->notify($payout->tenant_id, $event, [
            'reference' => (string) $payout->reference,
            'amount' => $payout->money()->format(),
            'date' => (string) $payout->scheduled_for?->toDateString(),
        ], 'finance.payouts.index');
    }
}

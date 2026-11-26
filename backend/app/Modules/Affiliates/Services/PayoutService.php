<?php

namespace App\Modules\Affiliates\Services;

use App\Modules\Affiliates\DTOs\AdminActor;
use App\Modules\Affiliates\DTOs\PayoutData;
use App\Modules\Affiliates\Enums\ConversionStatus;
use App\Modules\Affiliates\Enums\PayoutRequestStatus;
use App\Modules\Affiliates\Events\AffiliatePayoutPaid;
use App\Modules\Affiliates\Events\AffiliatePayoutRequestRejected;
use App\Modules\Affiliates\Exceptions\AffiliateRuleException;
use App\Modules\Affiliates\Models\AffiliatePayout;
use App\Modules\Affiliates\Models\AffiliatePayoutRequest;
use App\Modules\Affiliates\Repositories\Contracts\AffiliateRepositoryContract;
use App\Modules\Affiliates\Repositories\Contracts\ConversionRepositoryContract;
use App\Modules\Affiliates\Repositories\Contracts\PayoutRepositoryContract;
use App\Modules\Affiliates\Repositories\Contracts\PayoutRequestRepositoryContract;
use App\Modules\Identity\Models\Affiliate;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Affiliate payouts (ADM-060b / 062):
 *   pay()     0 < amount ≤ balance, and ≥ the program minimum unless it empties the
 *             balance. The amount is allocated to approved conversions oldest first
 *             (partially when needed; a fully covered conversion becomes paid), the
 *             balance drops and total_paid grows. Paying a request closes it.
 *   reject()  pending request → rejected with the admin note.
 * Proof files live on the private `central` disk.
 */
class PayoutService extends BaseService
{
    public const DISK = 'central';

    public function __construct(
        private readonly AffiliateRepositoryContract $affiliates,
        private readonly ConversionRepositoryContract $conversions,
        private readonly PayoutRepositoryContract $payouts,
        private readonly PayoutRequestRepositoryContract $requests,
        private readonly ProgramSettingsService $settings,
    ) {}

    public function pay(Affiliate $affiliate, PayoutData $data, AdminActor $actor, ?AffiliatePayoutRequest $request = null): AffiliatePayout
    {
        $stored = $data->attachment?->store('affiliate-payouts/'.$affiliate->id, self::DISK);

        try {
            return $this->transaction(function () use ($affiliate, $data, $actor, $request, $stored): AffiliatePayout {
                $locked = $this->affiliates->lock($affiliate->id);
                $this->assertAmount($locked, $data->amount);

                if ($request !== null && $this->requests->lock($request->id)->status !== PayoutRequestStatus::Pending) {
                    throw AffiliateRuleException::requestClosed();
                }

                /** @var AffiliatePayout $payout */
                $payout = $this->payouts->create([
                    'affiliate_id' => $locked->id,
                    'amount' => $data->amount,
                    'method' => $data->method,
                    'reference' => $data->reference,
                    'notes' => $data->notes,
                    'attachment' => $stored ?: null,
                    'attachment_name' => $data->attachment?->getClientOriginalName(),
                    'paid_by_id' => $actor->id,
                    'paid_by_name' => $actor->name,
                    'paid_at' => now(),
                ]);

                $this->allocate($locked->id, $data->amount);

                $locked->forceFill([
                    'balance' => $locked->balance - $data->amount,
                    'total_paid' => $locked->total_paid + $data->amount,
                ])->save();

                if ($request !== null) {
                    $this->requests->update($request, ['status' => PayoutRequestStatus::Paid, 'payout_id' => $payout->id, 'processed_at' => now()]);
                }

                $this->dispatchAfterCommit(new AffiliatePayoutPaid($payout));

                return $payout;
            }, $this->connection());
        } catch (Throwable $exception) {
            if (is_string($stored) && $stored !== '') {
                Storage::disk(self::DISK)->delete($stored);
            }

            throw $exception;
        }
    }

    public function reject(AffiliatePayoutRequest $request, ?string $adminNote): AffiliatePayoutRequest
    {
        return $this->transaction(function () use ($request, $adminNote): AffiliatePayoutRequest {
            $locked = $this->requests->lock($request->id);

            if ($locked->status !== PayoutRequestStatus::Pending) {
                throw AffiliateRuleException::requestClosed();
            }

            $this->requests->update($locked, ['status' => PayoutRequestStatus::Rejected, 'admin_note' => $adminNote, 'processed_at' => now()]);
            $this->dispatchAfterCommit(new AffiliatePayoutRequestRejected($locked));

            return $locked;
        }, $this->connection());
    }

    private function assertAmount(Affiliate $affiliate, int $amount): void
    {
        if ($amount <= 0) {
            throw AffiliateRuleException::notPositive();
        }

        if ($amount > $affiliate->balance) {
            throw AffiliateRuleException::amountAboveBalance(Money::ofMinor(max(0, $affiliate->balance)));
        }

        $minimum = $this->settings->minPayout();

        if ($amount < $minimum->minor && $amount < $affiliate->balance) {
            throw AffiliateRuleException::belowMinimum($minimum);
        }
    }

    private function allocate(int $affiliateId, int $amount): void
    {
        $remaining = $amount;

        foreach ($this->conversions->lockUnpaidApproved($affiliateId) as $conversion) {
            if ($remaining <= 0) {
                break;
            }

            $due = $conversion->commission_amount - $conversion->paid_amount;
            $part = min($due, $remaining);
            $remaining -= $part;
            $paid = $conversion->paid_amount + $part;

            $this->conversions->update($conversion, $paid >= $conversion->commission_amount
                ? ['paid_amount' => $paid, 'status' => ConversionStatus::Paid, 'paid_at' => now()]
                : ['paid_amount' => $paid]);
        }
    }

    private function connection(): ?string
    {
        return (new AffiliatePayout)->getConnectionName();
    }
}

<?php

namespace App\Modules\Affiliates\Services;

use App\Modules\Affiliates\Enums\ConversionSource;
use App\Modules\Affiliates\Enums\ConversionStatus;
use App\Modules\Affiliates\Enums\RateType;
use App\Modules\Affiliates\Events\AffiliateConversionApproved;
use App\Modules\Affiliates\Events\AffiliateConversionReversed;
use App\Modules\Affiliates\Exceptions\AffiliateRuleException;
use App\Modules\Affiliates\Models\AffiliateConversion;
use App\Modules\Affiliates\Models\AffiliatePromoCode;
use App\Modules\Affiliates\Repositories\Contracts\AffiliateRepositoryContract;
use App\Modules\Affiliates\Repositories\Contracts\ConversionRepositoryContract;
use App\Modules\Affiliates\Repositories\Contracts\PromoCodeRepositoryContract;
use App\Modules\Affiliates\Support\CommissionCalculator;
use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Identity\Models\Affiliate;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Models\VendorRegistration;
use App\Shared\Services\BaseService;
use Illuminate\Support\Str;

/**
 * Conversion lifecycle (commission only on the referred store's FIRST paid
 * subscription payment, reversed on refund):
 *
 *   recordSignup()       RegistrationCompleted → pending conversion from the promo code
 *                        (registration meta `affiliate_promo_code`, wins) or the referral
 *                        click (meta `affiliate_click_id`, else the visitor's cookie);
 *                        self-referrals are stored as rejected. The rate is snapshotted.
 *   approveForPayment()  PaymentCaptured (registration / renewal, amount > 0, store
 *                        known) → the store's pending conversion is approved on that
 *                        payment and the affiliate balance credited. Later payments find
 *                        no pending conversion, so renewals never earn. A suspended /
 *                        pending affiliate keeps the conversion pending.
 *   reverse()            refund (AffiliateConversionReversalRequested) or admin: approved
 *                        / paid → reversed, the commission leaves earned + balance (the
 *                        balance may go negative when it was already paid out).
 *   reject()             admin: pending → rejected.
 * Invariant: balance = total_earned − total_paid.
 */
class ConversionService extends BaseService
{
    public function __construct(
        private readonly ConversionRepositoryContract $conversions,
        private readonly AffiliateRepositoryContract $affiliates,
        private readonly PromoCodeRepositoryContract $promoCodes,
        private readonly AffiliateAccountService $accounts,
        private readonly ReferralTracker $tracker,
    ) {}

    public function recordSignup(VendorRegistration $registration, Tenant $tenant, ?int $fallbackClickId = null): ?AffiliateConversion
    {
        if ($this->conversions->existsForTenant($tenant->id)) {
            return null;
        }

        $meta = $registration->meta ?? [];
        $promo = is_string($meta['affiliate_promo_code'] ?? null) ? $this->promoCodes->findByCode($meta['affiliate_promo_code']) : null;
        $promo = $promo !== null && $promo->isUsable() && $promo->affiliate?->isActive() ? $promo : null;
        $clickId = filter_var($meta['affiliate_click_id'] ?? $fallbackClickId, FILTER_VALIDATE_INT);
        $click = $promo === null && $clickId !== false ? $this->tracker->openClick($clickId) : null;
        $affiliate = $promo?->affiliate ?? $click?->affiliate;

        if (! $affiliate instanceof Affiliate || ! $affiliate->isActive()) {
            return null;
        }

        $conversion = $this->transaction(function () use ($affiliate, $tenant, $registration, $promo, $click): AffiliateConversion {
            [$type, $value] = $this->rateFor($affiliate, $promo);
            $selfReferral = $this->isSelfReferral($affiliate, $tenant, $registration);

            /** @var AffiliateConversion $conversion */
            $conversion = $this->conversions->create([
                'affiliate_id' => $affiliate->id,
                'click_id' => $click?->id,
                'promo_code_id' => $promo?->id,
                'source' => $promo !== null ? ConversionSource::PromoCode : ConversionSource::Link,
                'tenant_id' => $tenant->id,
                'commission_type' => $type,
                'commission_value' => $value,
                'status' => $selfReferral ? ConversionStatus::Rejected : ConversionStatus::Pending,
                'note' => $selfReferral ? (string) __('affiliates::admin.conversions.self_referral') : null,
            ]);

            $click?->forceFill(['converted_at' => now(), 'tenant_id' => $tenant->id])->save();

            if ($promo !== null) {
                $promo->increment('uses');
            }

            return $conversion;
        }, $this->connection());

        $log = $registration->paymentLogId() !== null ? PaymentLog::withoutTenantScope()->find($registration->paymentLogId()) : null;

        if ($log instanceof PaymentLog && $conversion->status === ConversionStatus::Pending) {
            $this->approveForPayment($log, $tenant->id);
        }

        return $conversion->refresh();
    }

    public function approveForPayment(PaymentLog $log, ?int $tenantId = null): ?AffiliateConversion
    {
        $tenantId ??= $log->tenant_id;

        if ($tenantId === null || $log->status !== PaymentLogStatus::Captured || $log->amount_minor <= 0
            || ! in_array($log->purpose, [PaymentPurpose::Registration, PaymentPurpose::Renewal], true)) {
            return null;
        }

        return $this->transaction(function () use ($log, $tenantId): ?AffiliateConversion {
            if ($this->conversions->paymentLogUsed($log->id)) {
                return null;
            }

            $conversion = $this->conversions->lockPendingForTenant($tenantId);

            if ($conversion === null) {
                return null;
            }

            $affiliate = $this->affiliates->lock($conversion->affiliate_id);

            if (! $affiliate->isActive()) {
                return null;
            }

            $commission = CommissionCalculator::amount($conversion->commission_type, $conversion->commission_value, $log->amount_minor);

            $this->conversions->update($conversion, [
                'payment_log_id' => $log->id,
                'sale_amount' => $log->amount_minor,
                'commission_amount' => $commission,
                'status' => ConversionStatus::Approved,
                'approved_at' => now(),
            ]);

            $affiliate->forceFill([
                'balance' => $affiliate->balance + $commission,
                'total_earned' => $affiliate->total_earned + $commission,
            ])->save();

            $this->dispatchAfterCommit(new AffiliateConversionApproved($conversion));

            return $conversion;
        }, $this->connection());
    }

    /**
     * Billing refund of a subscription payment. Store-order refunds (reason
     * "store_order:…") carry a refund id, not a payment log id, and never earned.
     */
    public function reverseForPaymentLog(int $paymentLogId, ?string $reason = null): ?AffiliateConversion
    {
        if ($reason !== null && Str::startsWith($reason, 'store_order:')) {
            return null;
        }

        return $this->transaction(function () use ($paymentLogId, $reason): ?AffiliateConversion {
            $conversion = $this->conversions->lockByPaymentLog($paymentLogId);

            if ($conversion === null || ! $conversion->status->canReverse()) {
                return null;
            }

            return $this->applyReversal($conversion, filled($reason) ? $reason : (string) __('affiliates::admin.conversions.refunded'));
        }, $this->connection());
    }

    public function reverse(AffiliateConversion $conversion, string $reason): AffiliateConversion
    {
        return $this->transaction(function () use ($conversion, $reason): AffiliateConversion {
            $locked = $this->conversions->lock($conversion->id);

            if (! $locked->status->canReverse()) {
                throw AffiliateRuleException::cannotReverse();
            }

            return $this->applyReversal($locked, $reason);
        }, $this->connection());
    }

    public function reject(AffiliateConversion $conversion, ?string $reason): AffiliateConversion
    {
        return $this->transaction(function () use ($conversion, $reason): AffiliateConversion {
            $locked = $this->conversions->lock($conversion->id);

            if (! $locked->status->canReject()) {
                throw AffiliateRuleException::cannotReject();
            }

            $this->conversions->update($locked, ['status' => ConversionStatus::Rejected, 'note' => $reason]);

            return $locked;
        }, $this->connection());
    }

    private function applyReversal(AffiliateConversion $conversion, string $reason): AffiliateConversion
    {
        $affiliate = $this->affiliates->lock($conversion->affiliate_id);
        $commission = $conversion->commission_amount;

        $this->conversions->update($conversion, [
            'status' => ConversionStatus::Reversed,
            'reversed_at' => now(),
            'note' => $reason,
        ]);

        $affiliate->forceFill([
            'balance' => $affiliate->balance - $commission,
            'total_earned' => $affiliate->total_earned - $commission,
        ])->save();

        $this->dispatchAfterCommit(new AffiliateConversionReversed($conversion));

        return $conversion;
    }

    /**
     * @return array{0: RateType, 1: int}
     */
    private function rateFor(Affiliate $affiliate, ?AffiliatePromoCode $promo): array
    {
        if ($promo !== null && $promo->commission_type !== null && $promo->commission_value !== null) {
            return [$promo->commission_type, $promo->commission_value];
        }

        return $this->accounts->commissionFor($affiliate);
    }

    /**
     * The affiliate's own store, its own e-mail, or a store of the same owner.
     */
    private function isSelfReferral(Affiliate $affiliate, Tenant $tenant, VendorRegistration $registration): bool
    {
        if ($affiliate->tenant_id === $tenant->id) {
            return true;
        }

        $emails = array_filter([Str::lower((string) $tenant->owner_email), Str::lower($registration->email)]);
        $affiliateEmails = array_filter([
            Str::lower((string) $affiliate->email),
            $affiliate->tenant_id !== null ? Str::lower((string) Tenant::query()->whereKey($affiliate->tenant_id)->value('owner_email')) : null,
        ]);

        if (array_intersect($emails, $affiliateEmails) !== []) {
            return true;
        }

        $ownerId = Tenant::query()->whereKey($tenant->id)->value('owner_id');

        return $affiliate->tenant_id !== null && $ownerId !== null
            && Tenant::query()->whereKey($affiliate->tenant_id)->value('owner_id') === $ownerId;
    }

    private function connection(): ?string
    {
        return (new AffiliateConversion)->getConnectionName();
    }
}

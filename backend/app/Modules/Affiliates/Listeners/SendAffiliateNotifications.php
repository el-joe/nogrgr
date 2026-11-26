<?php

namespace App\Modules\Affiliates\Listeners;

use App\Modules\Affiliates\Enums\ConversionStatus;
use App\Modules\Affiliates\Events\AffiliateActivated;
use App\Modules\Affiliates\Events\AffiliateConversionApproved;
use App\Modules\Affiliates\Events\AffiliateConversionReversed;
use App\Modules\Affiliates\Events\AffiliatePayoutPaid;
use App\Modules\Affiliates\Events\AffiliatePayoutRequestRejected;
use App\Modules\Affiliates\Models\AffiliateConversion;
use App\Modules\Affiliates\Notifications\AffiliateNotification;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Models\Affiliate;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Shared\Money\Money;
use Illuminate\Events\Dispatcher;

/**
 * Affiliate program events → AffiliateNotification to the affiliate (database + mail +
 * Reverb). Affiliates without an e-mail (vendor accounts whose owner e-mail is used by
 * another affiliate) get the inbox only.
 */
class SendAffiliateNotifications
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(AffiliateActivated::class, [self::class, 'activated']);
        $events->listen(AffiliateConversionApproved::class, [self::class, 'conversionApproved']);
        $events->listen(AffiliateConversionReversed::class, [self::class, 'conversionReversed']);
        $events->listen(AffiliatePayoutPaid::class, [self::class, 'payoutPaid']);
        $events->listen(AffiliatePayoutRequestRejected::class, [self::class, 'payoutRejected']);
    }

    public function activated(AffiliateActivated $event): void
    {
        $affiliate = $event->affiliate;
        $referral = route('central.home', ['ref' => $affiliate->code]);

        $affiliate->notify(new AffiliateNotification('activated', ['code' => (string) $affiliate->code], EmailTemplateEvent::AffiliateActivated, [
            'affiliate.name' => $affiliate->name,
            'affiliate.code' => (string) $affiliate->code,
            'referral.url' => $referral,
            'login.url' => route('affiliate.login'),
        ], [
            'tone' => 'success',
            'icon' => 'check',
            'label' => (string) __('affiliates::notifications.mail.label_account'),
            'heading' => (string) __('affiliates::notifications.activated.title', ['code' => (string) $affiliate->code]),
            'rows' => [
                ['label' => (string) __('affiliates::notifications.mail.code'), 'value' => (string) $affiliate->code],
                ['label' => (string) __('affiliates::notifications.mail.referral_link'), 'value' => $referral],
            ],
            'note' => null,
            'cta' => (string) __('affiliates::notifications.mail.cta_dashboard'),
        ], route('affiliate.dashboard')));
    }

    public function conversionApproved(AffiliateConversionApproved $event): void
    {
        $this->conversion($event->conversion, 'conversion_approved');
    }

    public function conversionReversed(AffiliateConversionReversed $event): void
    {
        $this->conversion($event->conversion, 'conversion_reversed');
    }

    public function payoutPaid(AffiliatePayoutPaid $event): void
    {
        $payout = $event->payout;
        $affiliate = $this->affiliate($payout->affiliate_id);

        if ($affiliate === null) {
            return;
        }

        $amount = $payout->money()->format();
        $reference = $payout->reference ?? $payout->displayReference();

        $affiliate->notify(new AffiliateNotification('payout_paid', ['amount' => $amount, 'reference' => $reference], EmailTemplateEvent::AffiliatePayout, [
            'affiliate.name' => $affiliate->name,
            'payout.status' => (string) __('affiliates::notifications.mail.paid'),
            'payout.amount' => $amount,
            'payout.reference' => $reference,
            'payout.method' => $payout->method->label(),
            'payout.note' => (string) ($payout->notes ?? ''),
            'dashboard.url' => route('affiliate.dashboard'),
        ], [
            'tone' => 'success',
            'icon' => 'check',
            'label' => (string) __('affiliates::notifications.mail.label_payout'),
            'heading' => (string) __('affiliates::notifications.payout_paid.title', ['amount' => $amount, 'reference' => $reference]),
            'rows' => [
                ['label' => (string) __('affiliates::notifications.mail.amount'), 'value' => $amount],
                ['label' => (string) __('affiliates::notifications.mail.method'), 'value' => $payout->method->label()],
                ['label' => (string) __('affiliates::notifications.mail.reference'), 'value' => $reference],
                ['label' => (string) __('affiliates::notifications.mail.balance'), 'value' => $affiliate->balanceMoney()->format()],
            ],
            'note' => $payout->notes,
            'cta' => (string) __('affiliates::notifications.mail.cta_dashboard'),
        ], route('affiliate.dashboard')));
    }

    public function payoutRejected(AffiliatePayoutRequestRejected $event): void
    {
        $request = $event->payoutRequest;
        $affiliate = $this->affiliate($request->affiliate_id);

        if ($affiliate === null) {
            return;
        }

        $amount = $request->money()->format();

        $affiliate->notify(new AffiliateNotification('payout_rejected', ['amount' => $amount, 'reference' => $request->reference()], EmailTemplateEvent::AffiliatePayout, [
            'affiliate.name' => $affiliate->name,
            'payout.status' => (string) __('affiliates::notifications.mail.rejected'),
            'payout.amount' => $amount,
            'payout.reference' => $request->reference(),
            'payout.method' => '',
            'payout.note' => (string) ($request->admin_note ?? ''),
            'dashboard.url' => route('affiliate.dashboard'),
        ], [
            'tone' => 'danger',
            'icon' => 'x',
            'label' => (string) __('affiliates::notifications.mail.label_payout'),
            'heading' => (string) __('affiliates::notifications.payout_rejected.title', ['amount' => $amount, 'reference' => $request->reference()]),
            'rows' => [
                ['label' => (string) __('affiliates::notifications.mail.amount'), 'value' => $amount],
                ['label' => (string) __('affiliates::notifications.mail.reference'), 'value' => $request->reference()],
                ['label' => (string) __('affiliates::notifications.mail.balance'), 'value' => $affiliate->balanceMoney()->format()],
            ],
            'note' => $request->admin_note,
            'cta' => (string) __('affiliates::notifications.mail.cta_dashboard'),
        ], route('affiliate.dashboard')));
    }

    private function conversion(AffiliateConversion $conversion, string $eventName): void
    {
        $affiliate = $this->affiliate($conversion->affiliate_id);

        if ($affiliate === null) {
            return;
        }

        $conversion->loadMissing('tenant');
        $amount = Money::ofMinor($conversion->commission_amount)->format();
        $store = (string) ($conversion->tenant->name ?? '—');
        $plan = $conversion->tenant_id !== null
            ? (string) (Subscription::withoutTenantScope()->with('plan')->where('tenant_id', $conversion->tenant_id)->latest('id')->first()?->plan?->name ?? '—')
            : '—';
        $reversed = $conversion->status === ConversionStatus::Reversed;

        $affiliate->notify(new AffiliateNotification($eventName, ['amount' => $amount, 'store' => $store, 'reference' => $conversion->reference()], EmailTemplateEvent::AffiliateConversion, [
            'affiliate.name' => $affiliate->name,
            'conversion.status' => $conversion->status->label(),
            'store.name' => $store,
            'plan.name' => $plan,
            'commission.amount' => $amount,
            'conversion.note' => (string) ($conversion->note ?? ''),
            'dashboard.url' => route('affiliate.dashboard'),
        ], [
            'tone' => $reversed ? 'danger' : 'success',
            'icon' => $reversed ? 'x' : 'check',
            'label' => (string) __('affiliates::notifications.mail.label_conversion'),
            'heading' => (string) __('affiliates::notifications.'.$eventName.'.title', ['amount' => $amount, 'store' => $store, 'reference' => $conversion->reference()]),
            'rows' => [
                ['label' => (string) __('affiliates::notifications.mail.store'), 'value' => $store],
                ['label' => (string) __('affiliates::notifications.mail.plan'), 'value' => $plan],
                ['label' => (string) __('affiliates::notifications.mail.commission'), 'value' => $amount],
                ['label' => (string) __('affiliates::notifications.mail.balance'), 'value' => $affiliate->balanceMoney()->format()],
            ],
            'note' => $reversed ? $conversion->note : null,
            'cta' => (string) __('affiliates::notifications.mail.cta_dashboard'),
        ], route('affiliate.dashboard')));
    }

    private function affiliate(int $id): ?Affiliate
    {
        return Affiliate::withoutTenantScope()->find($id);
    }
}

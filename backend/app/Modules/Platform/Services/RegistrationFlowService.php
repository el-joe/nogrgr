<?php

namespace App\Modules\Platform\Services;

use App\Modules\Affiliates\Services\ReferralTracker;
use App\Modules\Billing\DTOs\InvoiceLineData;
use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Enums\PlanStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Repositories\Contracts\PlanRepositoryContract;
use App\Modules\Billing\Support\InvoiceCalculator;
use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Services\OwnerService;
use App\Modules\Platform\DTOs\ChoosePlanData;
use App\Modules\Platform\DTOs\PendingRegistrationData;
use App\Modules\Platform\DTOs\RegistrationQuoteData;
use App\Modules\Platform\DTOs\SignUpDraftData;
use App\Modules\Platform\Enums\PlanChoice;
use App\Modules\Platform\Enums\RegistrationStatus;
use App\Modules\Platform\Exceptions\PlatformRuleException;
use App\Modules\Platform\Models\VendorRegistration;
use App\Modules\Platform\Repositories\Contracts\VendorRegistrationRepositoryContract;
use App\Modules\Platform\Support\RegistrationSession;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * The central sign-up wizard before the completion link (Platform › Registration):
 *
 *   start()       WEB-016 sign-up → session draft (e-mail, phone, compliance accept)
 *   forOwner()    OWN-007 "Add another store" → draft from the signed-in owner + store
 *   choosePlan()  WEB-016b → vendor_registrations row: trial → e-mail link (or straight
 *                 on when the e-mail is proven); paid → payment_pending (WEB-016c)
 *   resend()      WEB-017 resend with cooldown + limit; changeEmail()
 *   continueUrl() fresh completion link for a proven e-mail ("Set up my store")
 */
class RegistrationFlowService extends BaseService
{
    /** Seconds between two verification e-mails. */
    public const RESEND_COOLDOWN_SECONDS = 60;

    /** Verification e-mails per registration (first send included). */
    public const MAX_LINK_SENDS = 5;

    public function __construct(
        private readonly VendorRegistrationRepositoryContract $registrations,
        private readonly VendorRegistrationService $registrationService,
        private readonly PlanRepositoryContract $plans,
        private readonly OwnerService $owners,
        private readonly ReferralTracker $referrals,
    ) {}

    public function start(RegistrationSession $session, SignUpDraftData $draft): void
    {
        $session->putDraft($draft);
    }

    /**
     * Plans offered on the plan step, in admin order.
     *
     * @return Collection<int, Plan>
     */
    public function plans(): Collection
    {
        return $this->plans->ordered()->filter(static fn (Plan $plan): bool => $plan->status === PlanStatus::Active)->values();
    }

    public function quote(Plan $plan, BillingTerm $term): RegistrationQuoteData
    {
        $taxRate = (int) config('billing.tax_rate_bp', 0);
        $totals = InvoiceCalculator::calculate([new InvoiceLineData(description: $plan->name, unitAmountMinor: $plan->priceFor($term)->minor)], $taxRate, Money::BASE_CURRENCY);

        return new RegistrationQuoteData(
            plan: $plan,
            term: $term,
            subtotal: $totals['subtotal'],
            taxRateBp: $taxRate,
            tax: $totals['tax'],
            total: $totals['total'],
        );
    }

    public function quoteFor(VendorRegistration $registration): ?RegistrationQuoteData
    {
        $plan = $registration->plan;

        return $plan instanceof Plan ? $this->quote($plan, $registration->term ?? BillingTerm::Monthly) : null;
    }

    /**
     * Creates (or, for the visitor's own open registration, re-plans) the registration.
     */
    public function choosePlan(RegistrationSession $session, ChoosePlanData $data): VendorRegistration
    {
        $plan = $this->plans()->first(static fn (Plan $plan): bool => $plan->id === $data->planId);

        if (! $plan instanceof Plan) {
            throw PlatformRuleException::planUnavailable();
        }

        $quote = $this->quote($plan, $data->term);
        $trial = $data->choice === PlanChoice::Trial && $plan->trial_days > 0;

        if ($data->choice === PlanChoice::Trial && ! $trial && $quote->requiresPayment()) {
            throw PlatformRuleException::trialUnavailable();
        }

        $needsPayment = ! $trial && $quote->requiresPayment();
        $current = $this->current($session);

        if ($current !== null && in_array($current->status, [RegistrationStatus::PaymentPending, RegistrationStatus::PaymentFailed], true)) {
            return $this->replan($current, $plan, $data->term, $needsPayment);
        }

        $draft = $session->draft();

        if ($draft === null) {
            throw PlatformRuleException::signUpMissing();
        }

        $registration = $this->registrationService->createPending(new PendingRegistrationData(
            email: $draft->email,
            phone: $draft->phone,
            planId: $plan->id,
            term: $data->term,
            locale: $draft->locale,
            status: $needsPayment ? RegistrationStatus::PaymentPending : ($draft->emailVerified ? RegistrationStatus::Incomplete : RegistrationStatus::EmailUnverified),
            meta: array_filter([
                'compliance_accepted_at' => $draft->complianceAcceptedAt,
                'social_provider' => $draft->socialProvider,
                'default_country_id' => $draft->defaultCountryId,
                'plan_choice' => $needsPayment ? PlanChoice::Paid->value : PlanChoice::Trial->value,
                // Theme picked on the central theme preview (WEB-004); the new store starts with it.
                'theme_id' => $session->preselectedTheme(),
                // Affiliate attribution survives the e-mail link being opened in another browser.
                'affiliate_click_id' => $this->referrals->currentClickId(request()),
            ], static fn (mixed $value): bool => $value !== null),
            ownerId: $draft->ownerId,
            emailVerified: $draft->emailVerified,
            ownerName: $draft->ownerName,
            ownerPasswordHash: $draft->ownerId !== null ? $this->owners->find($draft->ownerId)?->getRawOriginal('password') : null,
            storeName: $draft->storeName,
            slug: $draft->slug,
        ));

        $session->forgetDraft();
        $session->setCurrent($registration->id);

        if (! $needsPayment && ! $draft->emailVerified) {
            $this->registrationService->sendLink($registration);
        }

        return $registration;
    }

    /**
     * The visitor's registration (session), when it is still open.
     */
    public function current(RegistrationSession $session): ?VendorRegistration
    {
        $id = $session->currentId();

        /** @var VendorRegistration|null $registration */
        $registration = $id === null ? null : $this->registrations->find($id);

        if ($registration === null || $registration->status === RegistrationStatus::Completed || $registration->isExpired()) {
            return null;
        }

        return $registration;
    }

    /**
     * Seconds until the verification e-mail may be sent again (0 = now).
     */
    public function resendAvailableIn(VendorRegistration $registration): int
    {
        if ($registration->link_sent_at === null) {
            return 0;
        }

        $elapsed = (int) $registration->link_sent_at->diffInSeconds(now(), true);

        return max(0, self::RESEND_COOLDOWN_SECONDS - $elapsed);
    }

    public function resendsLeft(VendorRegistration $registration): int
    {
        return max(0, self::MAX_LINK_SENDS - $registration->link_sent_count);
    }

    public function resend(VendorRegistration $registration): void
    {
        if ($registration->status !== RegistrationStatus::EmailUnverified) {
            throw PlatformRuleException::linkNotAllowed();
        }

        if ($this->resendsLeft($registration) === 0) {
            throw PlatformRuleException::resendLimit();
        }

        if (($wait = $this->resendAvailableIn($registration)) > 0) {
            throw PlatformRuleException::resendCooldown($wait);
        }

        $this->registrationService->sendLink($registration);
    }

    public function changeEmail(VendorRegistration $registration, string $email): void
    {
        if ($registration->status !== RegistrationStatus::EmailUnverified || $registration->owner_id !== null) {
            throw PlatformRuleException::linkNotAllowed();
        }

        if ($this->resendsLeft($registration) === 0) {
            throw PlatformRuleException::resendLimit();
        }

        $this->registrationService->changeEmail($registration, $email);
    }

    /**
     * Completion link for a registration whose e-mail is proven; null while the e-mail
     * still has to be verified (the visitor is shown the "check your inbox" page).
     */
    public function continueUrl(VendorRegistration $registration): ?string
    {
        if (! $registration->isEmailVerified() || ! $registration->status->canSendLink()) {
            return null;
        }

        return $this->registrationService->issueLink($registration, false);
    }

    /**
     * OWN-007: sign-up draft from the signed-in owner; the store name / address are
     * reserved once the plan is chosen.
     */
    public function draftForOwner(Owner $owner, string $storeName, string $slug, ?int $countryId, string $locale): SignUpDraftData
    {
        if (! $this->registrationService->slugAvailable($slug)) {
            throw PlatformRuleException::slugTaken(Str::lower($slug));
        }

        return new SignUpDraftData(
            email: $owner->email,
            phone: $owner->phone,
            locale: $locale,
            complianceAcceptedAt: now()->toIso8601String(),
            emailVerified: true,
            ownerId: $owner->id,
            ownerName: $owner->name,
            storeName: $storeName,
            slug: Str::lower($slug),
            defaultCountryId: $countryId,
        );
    }

    private function replan(VendorRegistration $registration, Plan $plan, BillingTerm $term, bool $needsPayment): VendorRegistration
    {
        $status = $needsPayment ? RegistrationStatus::PaymentPending : $this->registrationService->readyStatus($registration);

        $this->registrations->update($registration, [
            'plan_id' => $plan->id,
            'term' => $term,
            'status' => $status,
            'meta' => [...($registration->meta ?? []), 'plan_choice' => $needsPayment ? PlanChoice::Paid->value : PlanChoice::Trial->value],
        ]);

        $registration->setRelation('plan', $plan);

        if ($status === RegistrationStatus::EmailUnverified) {
            $this->registrationService->sendLink($registration);
        }

        return $registration;
    }
}

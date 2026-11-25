<?php

namespace App\Modules\Platform\Listeners;

use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Events\PaymentCaptured;
use App\Modules\Billing\Events\PaymentFailed;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Platform\Enums\RegistrationStatus;
use App\Modules\Platform\Models\VendorRegistration;
use App\Modules\Platform\Notifications\RegistrationPaymentReceiptNotification;
use App\Modules\Platform\Repositories\Contracts\VendorRegistrationRepositoryContract;
use App\Modules\Platform\Services\VendorRegistrationService;
use Illuminate\Support\Facades\Notification;

/**
 * Registration payments (Billing payment log, purpose registration): captured → the
 * sign-up moves past the payment step, the receipt is e-mailed and, for an unproven
 * e-mail, the completion link is sent; failed → payment_failed (retry page). Runs
 * synchronously so the return page shows the new state; idempotent for webhook replays.
 */
final class AdvanceRegistrationOnPayment
{
    public function __construct(
        private readonly VendorRegistrationRepositoryContract $registrations,
        private readonly VendorRegistrationService $registrationService,
    ) {}

    public function captured(PaymentCaptured $event): void
    {
        $registration = $this->registrationFor($event->paymentLog);

        if ($registration === null || ! $this->registrationService->markPaid($registration, $event->paymentLog->id)) {
            return;
        }

        Notification::route('mail', $registration->email)->notify(
            (new RegistrationPaymentReceiptNotification(
                planName: (string) $registration->plan?->name,
                term: $registration->term?->label() ?? '',
                amount: $event->paymentLog->amount()->format($registration->locale),
                reference: $event->paymentLog->reference(),
            ))->locale($registration->locale ?? app()->getLocale()),
        );

        if ($registration->status === RegistrationStatus::EmailUnverified) {
            $this->registrationService->sendLink($registration);
        }
    }

    public function failed(PaymentFailed $event): void
    {
        $registration = $this->registrationFor($event->paymentLog);

        if ($registration !== null) {
            $this->registrationService->markPaymentFailed($registration);
        }
    }

    private function registrationFor(PaymentLog $log): ?VendorRegistration
    {
        $id = ($log->payload ?? [])['registration_id'] ?? null;

        if ($log->purpose !== PaymentPurpose::Registration || ! is_numeric($id)) {
            return null;
        }

        /** @var VendorRegistration|null $registration */
        $registration = $this->registrations->find((int) $id);

        return $registration;
    }
}

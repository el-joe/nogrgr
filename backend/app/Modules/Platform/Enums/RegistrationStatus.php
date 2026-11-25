<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * A central-website sign-up (vendor_registrations):
 *   payment_pending | payment_failed → email_unverified (link sent) → incomplete (account /
 *   store steps) → completed (store provisioned). Free / trial plans skip payment.
 */
enum RegistrationStatus: string
{
    use HasEnumHelpers;

    case PaymentPending = 'payment_pending';
    case PaymentFailed = 'payment_failed';
    case EmailUnverified = 'email_unverified';
    case Incomplete = 'incomplete';
    case Completed = 'completed';

    public function step(): RegistrationStep
    {
        return match ($this) {
            self::PaymentPending, self::PaymentFailed => RegistrationStep::Payment,
            self::EmailUnverified => RegistrationStep::EmailVerification,
            self::Incomplete => RegistrationStep::StoreDetails,
            self::Completed => RegistrationStep::Done,
        };
    }

    /**
     * The completion link (central.register.complete) may be (re)sent.
     */
    public function canSendLink(): bool
    {
        return in_array($this, [self::EmailUnverified, self::Incomplete], true);
    }

    public function isPending(): bool
    {
        return $this !== self::Completed;
    }

    /**
     * @return array<string, string>
     */
    public static function pendingOptions(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            if ($case->isPending()) {
                $options[$case->value] = $case->label();
            }
        }

        return $options;
    }

    public function tone(): string
    {
        return match ($this) {
            self::PaymentPending, self::Incomplete => 'warning',
            self::PaymentFailed => 'danger',
            self::EmailUnverified => 'neutral',
            self::Completed => 'success',
        };
    }
}

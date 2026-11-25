<?php

namespace App\Modules\Platform\Exceptions;

use App\Shared\Http\JsonResponder;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A vendors / registration / compliance / domains business rule refused the action.
 * Renders {success: false, message} with 422 (JSON) or redirects back with the message.
 */
class PlatformRuleException extends DomainException
{
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return JsonResponder::error($this->getMessage(), 422);
        }

        return back()->with('error', $this->getMessage());
    }

    public static function slugTaken(string $slug): self
    {
        return new self(__('platform::messages.slug_taken', ['slug' => $slug]));
    }

    public static function domainTaken(string $host): self
    {
        return new self(__('platform::messages.domain_taken', ['host' => $host]));
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self(__('platform::messages.invalid_transition', ['from' => $from, 'to' => $to]));
    }

    public static function cannotRetry(): self
    {
        return new self(__('platform::messages.cannot_retry'));
    }

    public static function noOwner(): self
    {
        return new self(__('platform::messages.no_owner'));
    }

    public static function linkNotAllowed(): self
    {
        return new self(__('platform::messages.link_not_allowed'));
    }

    public static function registrationClosed(): self
    {
        return new self(__('platform::messages.registration_closed'));
    }

    public static function accountStepMissing(): self
    {
        return new self(__('platform::messages.account_step_missing'));
    }

    public static function noteRequired(): self
    {
        return new self(__('platform::messages.note_required'));
    }

    public static function documentMissing(): self
    {
        return new self(__('platform::messages.document_missing'));
    }

    public static function planUnavailable(): self
    {
        return new self(__('platform::signup.errors.plan_unavailable'));
    }

    public static function trialUnavailable(): self
    {
        return new self(__('platform::signup.errors.trial_unavailable'));
    }

    public static function signUpMissing(): self
    {
        return new self(__('platform::signup.errors.sign_up_missing'));
    }

    public static function resendLimit(): self
    {
        return new self(__('platform::signup.verify.limit_reached'));
    }

    public static function resendCooldown(int $seconds): self
    {
        return new self(__('platform::signup.verify.cooldown', ['seconds' => $seconds]));
    }

    public static function paymentUnavailable(): self
    {
        return new self(__('platform::signup.errors.payment_unavailable'));
    }

    public static function languageNotPurchasable(): self
    {
        return new self(__('platform::setup.errors.language_not_purchasable'));
    }

    public static function launchNeedsCountries(): self
    {
        return new self(__('platform::setup.errors.launch_needs_countries'));
    }
}

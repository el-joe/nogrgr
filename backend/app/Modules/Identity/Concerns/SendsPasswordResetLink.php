<?php

namespace App\Modules\Identity\Concerns;

use App\Modules\Identity\Notifications\ResetPasswordNotification;

/**
 * Sends the password reset mail with a link to the model's own reset route and
 * uses the account language for notifications (HasLocalePreference).
 *
 * @phpstan-require-extends \Illuminate\Foundation\Auth\User
 */
trait SendsPasswordResetLink
{
    /**
     * Named route of the reset form for this account type.
     */
    abstract protected function passwordResetRoute(): string;

    /**
     * Password broker of this account type (config/auth.php `passwords`).
     */
    abstract protected function passwordBroker(): string;

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $url = route($this->passwordResetRoute(), ['token' => $token, 'email' => $this->getEmailForPasswordReset()]);
        $expires = (int) config('auth.passwords.'.$this->passwordBroker().'.expire', 60);

        $this->notify(new ResetPasswordNotification($url, $expires));
    }

    public function preferredLocale(): ?string
    {
        $locale = $this->getAttributes()['locale'] ?? null;

        return is_string($locale) && $locale !== '' ? $locale : null;
    }
}

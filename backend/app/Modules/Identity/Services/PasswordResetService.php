<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\DTOs\ResetPasswordData;
use App\Modules\Identity\Enums\Guard;
use App\Modules\Platform\Enums\AuditAction;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Services\BaseService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Forgot / reset password through the guard's Laravel password broker
 * (config/auth.php `passwords`). The token table lives on the connection the
 * broker resolves: central for admins/affiliates, the tenant DB for customers.
 *
 * Sending a link never reveals whether the email exists: the caller always shows
 * the same "if an account exists…" status.
 */
class PasswordResetService extends BaseService
{
    public function __construct(private readonly Auditor $auditor) {}

    public function sendResetLink(Guard $guard, string $email): void
    {
        $status = Password::broker($guard->passwordBroker())->sendResetLink(['email' => $email]);

        if ($status === Password::RESET_LINK_SENT) {
            $this->auditor->record(AuditAction::PasswordResetRequested, null, ['guard' => $guard->value, 'email' => $email]);
        }
    }

    /**
     * Whether a reset link (token + e-mail) is still usable, so the form can show the
     * "link expired" state up front instead of after the user typed a new password.
     */
    public function tokenIsValid(Guard $guard, ?string $email, string $token): bool
    {
        if ($email === null || $email === '') {
            return false;
        }

        $broker = Password::broker($guard->passwordBroker());

        if (! $broker instanceof PasswordBroker) {
            return false;
        }

        $user = $broker->getUser(['email' => mb_strtolower(trim($email))]);

        return $user !== null && $broker->tokenExists($user, $token);
    }

    /**
     * @throws ValidationException when the token is invalid or expired
     */
    public function reset(Guard $guard, ResetPasswordData $data): void
    {
        $status = Password::broker($guard->passwordBroker())->reset(
            ['email' => $data->email, 'password' => $data->password, 'token' => $data->token],
            function (CanResetPassword $user, string $password): void {
                if (! $user instanceof Model || ! $user instanceof Authenticatable) {
                    return;
                }

                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                $this->auditor->record(AuditAction::PasswordReset, $user);

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            /* Always the generic invalid-token message, so an unknown email is indistinguishable from a bad token. */
            throw ValidationException::withMessages(['email' => __(Password::INVALID_TOKEN)]);
        }
    }
}

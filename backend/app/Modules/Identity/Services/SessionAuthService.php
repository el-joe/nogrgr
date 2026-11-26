<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\DTOs\LoginCredentialsData;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Enums\Guard;
use App\Modules\Platform\Enums\AuditAction;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Services\BaseService;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Email + password session sign-in for the admin, affiliate and customer guards
 * (vendor staff use VendorAuthService because of the two host modes).
 *
 *  - 5 attempts per minute per guard + email + IP, then 429
 *  - unknown email and wrong password give the same message (timing equalised)
 *  - an inactive (pending / suspended) account is refused only after its password matched
 *  - login, logout and failed attempts are written to the audit log
 */
class SessionAuthService extends BaseService
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    public function __construct(private readonly Auditor $auditor) {}

    public function attempt(Guard $guard, LoginCredentialsData $credentials, Request $request): Authenticatable
    {
        $this->ensureIsNotRateLimited($guard, $credentials, $request);

        $session = $this->guard($guard);
        $provider = $session->getProvider();
        $user = $provider->retrieveByCredentials(['email' => $credentials->email]);

        if ($user === null) {
            Hash::check($credentials->password, $this->dummyHash());
            $this->failed($guard, $credentials, $request, 'invalid_credentials');
        }

        if (! $provider->validateCredentials($user, ['password' => $credentials->password])) {
            $this->failed($guard, $credentials, $request, 'invalid_credentials');
        }

        if (! $this->isActive($user)) {
            $this->auditFailure($guard, $credentials, 'inactive');

            throw ValidationException::withMessages(['email' => __($this->isPending($user) ? 'identity::auth.pending' : 'identity::auth.inactive')]);
        }

        RateLimiter::clear($this->throttleKey($guard, $credentials, $request));

        $request->session()->regenerate();
        $session->login($user, $credentials->remember);

        if ($user instanceof Model) {
            $user->forceFill(['last_login_at' => now()])->saveQuietly();
            $this->auditor->record(AuditAction::Login, $user);
        }

        return $user;
    }

    /**
     * Log a user in right after registration (no password check, no throttle).
     */
    public function login(Guard $guard, Authenticatable $user, Request $request, bool $remember = false): void
    {
        $request->session()->regenerate();
        $this->guard($guard)->login($user, $remember);

        if ($user instanceof Model) {
            $user->forceFill(['last_login_at' => now()])->saveQuietly();
            $this->auditor->record(AuditAction::Login, $user);
        }
    }

    /**
     * Sign out of one guard. Other guards sharing the host (admin + affiliate + central
     * vendor mode) stay signed in, so the session is regenerated rather than invalidated.
     */
    public function logout(Guard $guard, Request $request): void
    {
        $session = $this->guard($guard);
        $user = $session->user();

        if ($user instanceof Model) {
            $this->auditor->record(AuditAction::Logout, $user);
        }

        $session->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();
    }

    public function throttleKey(Guard $guard, LoginCredentialsData $credentials, Request $request): string
    {
        return $guard->value.'-login:'.sha1(mb_strtolower(trim($credentials->email)).'|'.$request->ip());
    }

    private function isActive(Authenticatable $user): bool
    {
        if (! $user instanceof Model) {
            return true;
        }

        $status = $user->getAttribute('status');

        return $status === null || $status === AccountStatus::Active;
    }

    /**
     * A pending account (e.g. a partner sign-up awaiting approval) gets its own message.
     */
    private function isPending(Authenticatable $user): bool
    {
        return $user instanceof Model && $user->getAttribute('status') === AccountStatus::Pending;
    }

    private function ensureIsNotRateLimited(Guard $guard, LoginCredentialsData $credentials, Request $request): void
    {
        $key = $this->throttleKey($guard, $credentials, $request);

        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);
        $this->auditFailure($guard, $credentials, 'throttled');

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => (int) ceil($seconds / 60)]),
        ])->status(429);
    }

    private function failed(Guard $guard, LoginCredentialsData $credentials, Request $request, string $reason): never
    {
        RateLimiter::hit($this->throttleKey($guard, $credentials, $request), self::DECAY_SECONDS);
        $this->auditFailure($guard, $credentials, $reason);

        throw ValidationException::withMessages(['email' => __('auth.failed')]);
    }

    private function auditFailure(Guard $guard, LoginCredentialsData $credentials, string $reason): void
    {
        $this->auditor->record(AuditAction::LoginFailed, null, [
            'guard' => $guard->value,
            'email' => $credentials->email,
            'reason' => $reason,
        ]);
    }

    private function guard(Guard $guard): SessionGuard
    {
        $session = Auth::guard($guard->value);

        if (! $session instanceof SessionGuard) {
            throw new LogicException("The {$guard->value} guard must be a session guard.");
        }

        return $session;
    }

    private function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make('nogrgr-timing-equaliser');
    }
}

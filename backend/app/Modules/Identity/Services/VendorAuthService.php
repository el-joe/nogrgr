<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\DTOs\VendorCredentialsData;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Platform\Enums\AuditAction;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Routing\VendorPanel;
use App\Shared\Services\BaseService;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Vendor panel authentication for both host modes.
 *
 *  - store host (`{store}/login`): tenancy is already initialised; authenticate against
 *    this tenant's vendor_staff.
 *  - central host (`{central}/tenant/login`): find candidate stores in the central
 *    vendor_staff_directory, verify the password inside each tenant, then log in
 *    directly (one store) or hand back the matching stores for the picker.
 *
 * Every failure returns the same message (no account enumeration) and counts towards
 * a per email+IP rate limit.
 */
class VendorAuthService extends BaseService
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    public const PENDING_SESSION_KEY = 'vendor_panel.pending';

    public const PENDING_TTL_SECONDS = 300;

    public function __construct(
        private readonly VendorStaffDirectory $directory,
        private readonly TenantRepositoryContract $tenants,
        private readonly Auditor $auditor,
        private readonly OwnerService $owners,
    ) {}

    /**
     * Store-host login (tenant context).
     */
    public function loginToCurrentTenant(VendorCredentialsData $credentials, Request $request): void
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            throw new LogicException('Store-host vendor login requires tenant context.');
        }

        $this->ensureIsNotRateLimited($credentials, $request);

        $staff = $this->verify($credentials);

        if ($staff === null) {
            $this->failed($credentials, $request);
        }

        RateLimiter::clear($this->throttleKey($credentials, $request));

        $this->completeLogin($tenant, $staff, $credentials->remember, $request);
    }

    /**
     * Central-host login. Returns the stores whose credentials matched: exactly one means
     * the user is now logged in; several means the caller must show the store picker.
     *
     * @return Collection<int, Tenant>
     */
    public function loginFromCentral(VendorCredentialsData $credentials, Request $request): Collection
    {
        $this->ensureIsNotRateLimited($credentials, $request);

        $entries = $this->directory->lookup($credentials->email);

        if ($entries->isEmpty()) {
            Hash::check($credentials->password, $this->dummyHash());
            $this->failed($credentials, $request);
        }

        $tenants = $this->tenants->findMany($entries->pluck('tenant_id')->map(fn (int $id): int => $id)->values()->all())->keyBy('id');

        /** @var array<int, int> $matches tenant id => vendor_staff id */
        $matches = [];

        foreach ($entries as $entry) {
            $tenant = $tenants->get($entry->tenant_id);

            if (! $tenant instanceof Tenant) {
                continue;
            }

            $staffId = $tenant->run(fn (): ?int => $this->verify($credentials, $entry->vendor_staff_id)?->id);

            if ($staffId !== null) {
                $matches[$tenant->id] = $staffId;
            }
        }

        if ($matches === []) {
            $this->failed($credentials, $request);
        }

        RateLimiter::clear($this->throttleKey($credentials, $request));

        /** @var Collection<int, Tenant> $matched */
        $matched = $tenants->only(array_keys($matches))->values();
        $this->signInOwner($credentials->email, $matched, $request);

        $preferred = $credentials->store !== null
            ? $matched->first(fn (Tenant $tenant): bool => $tenant->slug === $credentials->store)
            : null;

        if ($matched->count() === 1 || $preferred !== null) {
            $tenant = $preferred ?? $matched->firstOrFail();
            $this->loginById($tenant, $matches[$tenant->id], $credentials->remember, $request);

            return collect([$tenant]);
        }

        $request->session()->put(self::PENDING_SESSION_KEY, [
            'candidates' => $matches,
            'remember' => $credentials->remember,
            'expires_at' => now()->addSeconds(self::PENDING_TTL_SECONDS)->getTimestamp(),
        ]);

        return $matched;
    }

    /**
     * Stores offered by the picker (empty when nothing is pending or it expired).
     *
     * @return Collection<int, Tenant>
     */
    public function pendingStores(Request $request): Collection
    {
        $pending = $this->pending($request);

        if ($pending !== null) {
            return $this->tenants->findMany(array_keys($pending['candidates']))->values();
        }

        $owner = $this->owners->current();

        return $owner === null
            ? collect()
            : $this->owners->stores($owner)->filter(static fn (Tenant $tenant): bool => $tenant->status === TenantStatus::Active)->values()->toBase();
    }

    /**
     * Completes a multi-store login with the store chosen in the picker.
     */
    public function selectStore(int $tenantId, Request $request): Tenant
    {
        $pending = $this->pending($request);

        $tenant = $pending !== null && isset($pending['candidates'][$tenantId])
            ? $this->tenants->find($tenantId)
            : null;

        if ($pending === null) {
            return $this->selectOwnedStore($tenantId, $request);
        }

        if (! $tenant instanceof Tenant) {
            throw ValidationException::withMessages(['tenant_id' => __('identity::auth.store_unavailable')]);
        }

        $request->session()->forget(self::PENDING_SESSION_KEY);
        $this->loginById($tenant, $pending['candidates'][$tenantId], $pending['remember'], $request);

        return $tenant;
    }

    /**
     * Log the vendor out in either host mode. Other guards on the same host (e.g. an admin
     * session on the central host) stay signed in.
     */
    public function logout(Request $request): void
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            $tenantId = $request->session()->get(VendorPanel::SESSION_TENANT);
            $tenant = is_numeric($tenantId) ? $this->tenants->find((int) $tenantId) : null;
        }

        $logout = function () use ($request): void {
            $guard = Auth::guard('vendor');

            if ($guard instanceof SessionGuard && $guard->check()) {
                $this->auditor->record(AuditAction::Logout, $guard->user() instanceof VendorStaff ? $guard->user() : null);
                $guard->logout();
            }

            $request->session()->forget([VendorPanel::SESSION_TENANT, VendorPanel::SESSION_IMPERSONATOR, self::PENDING_SESSION_KEY]);
        };

        if ($tenant instanceof Tenant && ! tenancy()->initialized) {
            $tenant->run($logout);
        } else {
            $logout();
        }

        $this->owners->logout();
        $request->session()->regenerateToken();
    }

    /**
     * Log in as a given staff member of a tenant (verified login or admin impersonation).
     */
    public function loginById(Tenant $tenant, int $staffId, bool $remember, Request $request, ?int $impersonatorId = null): VendorStaff
    {
        $login = function () use ($tenant, $staffId, $remember, $request, $impersonatorId): VendorStaff {
            $staff = $this->provider()->retrieveById($staffId);

            if (! $staff instanceof VendorStaff) {
                throw ValidationException::withMessages(['email' => __('auth.failed')]);
            }

            $this->completeLogin($tenant, $staff, $remember, $request, $impersonatorId);

            return $staff;
        };

        return tenancy()->initialized ? $login() : $tenant->run($login);
    }

    /**
     * Signed-in owner (Google / Apple or an earlier central login) opening one of their
     * stores from the picker: signs in as the store's owner staff account.
     */
    private function selectOwnedStore(int $tenantId, Request $request): Tenant
    {
        $owner = $this->owners->current();
        $tenant = $owner === null ? null : $this->tenants->find($tenantId);

        if (! $tenant instanceof Tenant || $owner === null || ! $this->owners->owns($owner, $tenant) || $tenant->status !== TenantStatus::Active) {
            throw ValidationException::withMessages(['tenant_id' => __('identity::auth.store_unavailable')]);
        }

        $staffId = $tenant->run(function () use ($owner): ?int {
            $staff = $this->provider()->retrieveByCredentials(['email' => $owner->email]);

            return $staff instanceof VendorStaff && $staff->status === AccountStatus::Active ? $staff->id : null;
        });

        if ($staffId === null) {
            throw ValidationException::withMessages(['tenant_id' => __('identity::auth.store_unavailable')]);
        }

        $this->loginById($tenant, $staffId, false, $request);

        return $tenant;
    }

    /**
     * A central login whose e-mail belongs to the owner of a matched store also opens the
     * owner session (owner portal, "Add another store", store picker).
     *
     * @param  Collection<int, Tenant>  $matched
     */
    private function signInOwner(string $email, Collection $matched, Request $request): void
    {
        $owner = $this->owners->findByEmail($email);

        if ($owner !== null && $owner->isActive() && $matched->contains(fn (Tenant $tenant): bool => $this->owners->owns($owner, $tenant))) {
            $this->owners->login($owner, $request, false);
        }
    }

    private function completeLogin(Tenant $tenant, VendorStaff $staff, bool $remember, Request $request, ?int $impersonatorId = null): void
    {
        $request->session()->regenerate();

        Auth::guard('vendor')->login($staff, $remember);

        $request->session()->put(VendorPanel::SESSION_TENANT, $tenant->id);

        if ($impersonatorId !== null) {
            $request->session()->put(VendorPanel::SESSION_IMPERSONATOR, $impersonatorId);
            $this->auditor->record(AuditAction::Impersonated, $staff, ['impersonator_admin_id' => $impersonatorId]);
        } else {
            $request->session()->forget(VendorPanel::SESSION_IMPERSONATOR);
            $staff->forceFill(['last_login_at' => now()])->saveQuietly();
            $this->auditor->record(AuditAction::Login, $staff);
        }
    }

    /**
     * Verify credentials inside the current tenant. Returns the active staff member or null.
     */
    private function verify(VendorCredentialsData $credentials, ?int $expectedId = null): ?VendorStaff
    {
        $staff = $this->provider()->retrieveByCredentials(['email' => $credentials->email]);

        if (! $staff instanceof VendorStaff
            || ($expectedId !== null && $staff->id !== $expectedId)
            || $staff->status !== AccountStatus::Active) {
            Hash::check($credentials->password, $this->dummyHash());

            return null;
        }

        return $this->provider()->validateCredentials($staff, ['password' => $credentials->password]) ? $staff : null;
    }

    private function provider(): UserProvider
    {
        $guard = Auth::guard('vendor');

        if (! $guard instanceof SessionGuard) {
            throw new LogicException('The vendor guard must be a session guard.');
        }

        return $guard->getProvider();
    }

    /**
     * @return array{candidates: array<int, int>, remember: bool, expires_at: int}|null
     */
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::PENDING_SESSION_KEY);

        if (! is_array($pending) || ! isset($pending['candidates'], $pending['expires_at']) || ! is_array($pending['candidates'])) {
            return null;
        }

        if ((int) $pending['expires_at'] < now()->getTimestamp()) {
            $request->session()->forget(self::PENDING_SESSION_KEY);

            return null;
        }

        $candidates = [];

        foreach ($pending['candidates'] as $tenantId => $staffId) {
            $candidates[(int) $tenantId] = (int) $staffId;
        }

        return ['candidates' => $candidates, 'remember' => (bool) ($pending['remember'] ?? false), 'expires_at' => (int) $pending['expires_at']];
    }

    private function ensureIsNotRateLimited(VendorCredentialsData $credentials, Request $request): void
    {
        $key = $this->throttleKey($credentials, $request);

        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => (int) ceil($seconds / 60)]),
        ])->status(429);
    }

    private function failed(VendorCredentialsData $credentials, Request $request): never
    {
        RateLimiter::hit($this->throttleKey($credentials, $request), self::DECAY_SECONDS);

        throw ValidationException::withMessages(['email' => __('auth.failed')]);
    }

    private function throttleKey(VendorCredentialsData $credentials, Request $request): string
    {
        return 'vendor-login:'.sha1(mb_strtolower(trim($credentials->email)).'|'.$request->ip());
    }

    private function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make('nogrgr-timing-equaliser');
    }
}

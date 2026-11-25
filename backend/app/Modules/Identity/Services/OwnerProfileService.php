<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\DTOs\OwnerProfileData;
use App\Modules\Identity\Enums\SocialProvider;
use App\Modules\Identity\Jobs\SyncOwnerStaffPassword;
use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Repositories\Contracts\OwnerRepositoryContract;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Services\BaseService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Owner portal › Profile (OWN-006): personal information, password (kept in sync with
 * the owner's staff account in every store, queued) and linked Google / Apple
 * accounts. An owner always keeps at least one way to sign in: a provider can only be
 * unlinked while a password or the other provider remains.
 */
class OwnerProfileService extends BaseService
{
    public function __construct(private readonly OwnerRepositoryContract $owners) {}

    public function update(Owner $owner, OwnerProfileData $data): Owner
    {
        $this->owners->update($owner, [
            'name' => $data->name,
            'phone' => $data->phone,
            'locale' => $data->locale,
        ]);

        return $owner;
    }

    public function changePassword(Owner $owner, string $password): void
    {
        $hash = Hash::make($password);
        $this->owners->update($owner, ['password' => $hash]);

        $this->owners->stores($owner)
            ->reject(static fn (Tenant $tenant): bool => in_array($tenant->status, [TenantStatus::Pending, TenantStatus::Provisioning, TenantStatus::Failed], true))
            ->each(fn (Tenant $tenant) => SyncOwnerStaffPassword::dispatch($tenant->id, $owner->email, $hash)->onQueue('default'));
    }

    /**
     * Ways the owner can sign in: "password", "google", "apple".
     *
     * @return list<string>
     */
    public function signInMethods(Owner $owner): array
    {
        $methods = $owner->password !== null ? ['password'] : [];

        foreach (SocialProvider::cases() as $provider) {
            if ($this->linked($owner, $provider)) {
                $methods[] = $provider->value;
            }
        }

        return $methods;
    }

    public function linked(Owner $owner, SocialProvider $provider): bool
    {
        return filled($owner->getAttribute($provider->idColumn()));
    }

    public function canUnlink(Owner $owner, SocialProvider $provider): bool
    {
        return $this->linked($owner, $provider) && count($this->signInMethods($owner)) > 1;
    }

    /**
     * @throws ValidationException last sign-in method, or not linked
     */
    public function unlink(Owner $owner, SocialProvider $provider): Owner
    {
        if (! $this->linked($owner, $provider)) {
            throw ValidationException::withMessages(['provider' => __('identity::owner_profile.not_linked', ['provider' => $provider->brand()])]);
        }

        if (! $this->canUnlink($owner, $provider)) {
            throw ValidationException::withMessages(['provider' => __('identity::owner_profile.last_method', ['provider' => $provider->brand()])]);
        }

        $this->owners->update($owner, [$provider->idColumn() => null]);

        return $owner;
    }
}

<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\DTOs\SocialProfileData;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Repositories\Contracts\OwnerRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Services\BaseService;
use Illuminate\Auth\SessionGuard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Store owners (Identity › Owners): one person, many stores.
 *
 *   forRegistration()  owner of a store being provisioned (found by e-mail or created)
 *   fromSocial()       Google / Apple sign-in: by provider id, else link by verified
 *                      e-mail, else a new password-less owner
 *   login() / logout() the "owner" session guard
 */
class OwnerService extends BaseService
{
    public const GUARD = 'owner';

    public function __construct(private readonly OwnerRepositoryContract $owners) {}

    public function findByEmail(string $email): ?Owner
    {
        return $this->owners->findByEmail($email);
    }

    public function find(int $id): ?Owner
    {
        /** @var Owner|null $owner */
        $owner = $this->owners->find($id);

        return $owner;
    }

    /**
     * The completion link proved the e-mail, so a new owner is verified. An existing
     * owner without a password (social only) takes the password chosen for the store.
     */
    public function forRegistration(string $email, string $name, ?string $phone, ?string $passwordHash, ?string $locale): Owner
    {
        $owner = $this->owners->findByEmail($email);

        if ($owner === null) {
            /** @var Owner $owner */
            $owner = $this->owners->create([
                'name' => $name,
                'email' => Str::lower(trim($email)),
                'phone' => $phone,
                'password' => $passwordHash,
                'status' => AccountStatus::Active,
                'locale' => $locale,
                'email_verified_at' => now(),
            ]);

            return $owner;
        }

        $changes = array_filter([
            'password' => $owner->password === null ? $passwordHash : null,
            'phone' => $owner->phone === null ? $phone : null,
            'email_verified_at' => $owner->email_verified_at === null ? now() : null,
        ], static fn (mixed $value): bool => $value !== null);

        if ($changes !== []) {
            $this->owners->update($owner, $changes);
        }

        return $owner;
    }

    /**
     * @throws ValidationException suspended owner, or an unverified e-mail that belongs
     *                             to an existing owner (never linked on an unproven address)
     */
    public function fromSocial(SocialProfileData $profile): Owner
    {
        $owner = $this->owners->findBySocialId($profile->provider, $profile->id);

        if ($owner === null) {
            $owner = $this->owners->findByEmail($profile->email);

            if ($owner !== null) {
                if (! $profile->emailVerified) {
                    throw ValidationException::withMessages(['email' => __('identity::social.unverified_email')]);
                }

                $this->owners->update($owner, array_filter([
                    $profile->provider->idColumn() => $profile->id,
                    'email_verified_at' => $owner->email_verified_at === null ? now() : null,
                ], static fn (mixed $value): bool => $value !== null));
            } else {
                /** @var Owner $owner */
                $owner = $this->owners->create([
                    'name' => $profile->name !== null && trim($profile->name) !== '' ? trim($profile->name) : Str::before($profile->email, '@'),
                    'email' => Str::lower($profile->email),
                    $profile->provider->idColumn() => $profile->id,
                    'status' => AccountStatus::Active,
                    'locale' => app()->getLocale(),
                    'email_verified_at' => $profile->emailVerified ? now() : null,
                ]);
            }
        }

        if (! $owner->isActive()) {
            throw ValidationException::withMessages(['email' => __('identity::social.suspended')]);
        }

        return $owner;
    }

    /**
     * Attach a Google / Apple identity to the signed-in owner (profile "Link").
     *
     * @throws ValidationException the identity already belongs to another owner
     */
    public function linkSocial(Owner $owner, SocialProfileData $profile): Owner
    {
        $existing = $this->owners->findBySocialId($profile->provider, $profile->id);

        if ($existing !== null && $existing->id !== $owner->id) {
            throw ValidationException::withMessages(['provider' => __('identity::owner_profile.linked_elsewhere', ['provider' => $profile->provider->brand()])]);
        }

        $this->owners->update($owner, [$profile->provider->idColumn() => $profile->id]);

        return $owner;
    }

    public function login(Owner $owner, Request $request, bool $regenerate = true): void
    {
        if ($regenerate) {
            $request->session()->regenerate();
        }

        Auth::guard(self::GUARD)->login($owner);
        $owner->forceFill(['last_login_at' => now()])->saveQuietly();
    }

    public function logout(): void
    {
        $guard = Auth::guard(self::GUARD);

        if ($guard instanceof SessionGuard && $guard->check()) {
            $guard->logout();
        }
    }

    public function current(): ?Owner
    {
        $owner = Auth::guard(self::GUARD)->user();

        return $owner instanceof Owner && $owner->isActive() ? $owner : null;
    }

    /**
     * @return Collection<int, Tenant>
     */
    public function stores(Owner $owner): Collection
    {
        return $this->owners->stores($owner);
    }

    public function owns(Owner $owner, Tenant $tenant): bool
    {
        return (int) $tenant->getAttribute('owner_id') === $owner->id;
    }
}

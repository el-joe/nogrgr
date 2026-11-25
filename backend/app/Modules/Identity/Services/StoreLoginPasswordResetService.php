<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Notifications\StoreLoginPasswordResetNotification;
use App\Modules\Identity\Repositories\Contracts\OwnerRepositoryContract;
use App\Modules\Identity\Repositories\Contracts\StoreLoginPasswordResetRepositoryContract;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Password reset of the central store login (WEB-023 / 024). The central login checks
 * the password of the vendor-staff accounts that share the e-mail (vendor_staff_directory),
 * so a reset sets the new password on each of them and on the owner account.
 * Unknown e-mails get the same "sent" answer (no account enumeration); one mail per
 * minute per address; links expire after an hour.
 */
class StoreLoginPasswordResetService extends BaseService
{
    public const THROTTLE_SECONDS = 60;

    public function __construct(
        private readonly StoreLoginPasswordResetRepositoryContract $tokens,
        private readonly VendorStaffDirectory $directory,
        private readonly TenantRepositoryContract $tenants,
        private readonly OwnerRepositoryContract $owners,
    ) {}

    public function sendLink(string $email, string $locale): void
    {
        $email = mb_strtolower(trim($email));

        if (! $this->hasAccount($email)) {
            return;
        }

        $existing = $this->tokens->findByEmail($email);

        if ($existing?->created_at !== null && $existing->created_at->gt(now()->subSeconds(self::THROTTLE_SECONDS))) {
            return;
        }

        $token = Str::random(64);
        $this->tokens->put($email, Hash::make($token));

        Notification::route('mail', $email)->notify(new StoreLoginPasswordResetNotification($token, $email, $locale));
    }

    public function tokenIsValid(string $email, string $token): bool
    {
        $record = $this->tokens->findByEmail(mb_strtolower(trim($email)));

        return $record !== null
            && $record->created_at !== null
            && $record->created_at->gt(now()->subMinutes(StoreLoginPasswordResetNotification::EXPIRE_MINUTES))
            && Hash::check($token, $record->token);
    }

    /**
     * @return bool false when the token is invalid / expired
     */
    public function reset(string $email, string $token, string $password): bool
    {
        $email = mb_strtolower(trim($email));

        if (! $this->tokenIsValid($email, $token)) {
            return false;
        }

        $hash = Hash::make($password);
        $entries = $this->directory->lookup($email);
        $tenants = $this->tenants->findMany($entries->pluck('tenant_id')->map(fn ($id): int => (int) $id)->values()->all())->keyBy('id');

        foreach ($entries as $entry) {
            $tenant = $tenants->get($entry->tenant_id);

            if ($tenant instanceof Tenant) {
                $tenant->run(function () use ($entry, $email, $hash): void {
                    $staff = app(VendorStaffRepositoryContract::class);
                    $account = $staff->find($entry->vendor_staff_id);

                    if ($account instanceof VendorStaff && mb_strtolower($account->email) === $email) {
                        $staff->update($account, ['password' => $hash]);
                    }
                });
            }
        }

        $owner = $this->owners->findByEmail($email);

        if ($owner !== null) {
            $this->owners->update($owner, ['password' => $hash]);
        }

        $this->tokens->forget($email);

        return true;
    }

    private function hasAccount(string $email): bool
    {
        return $this->directory->lookup($email)->isNotEmpty() || $this->owners->findByEmail($email) !== null;
    }
}

<?php

namespace App\Modules\Identity\Services;

use App\Modules\Billing\Enums\PlanLimit;
use App\Modules\Billing\Services\EffectivePlanLimits;
use App\Modules\Identity\DTOs\VendorStaffData;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Enums\Guard;
use App\Modules\Identity\Enums\SystemRole;
use App\Modules\Identity\Exceptions\VendorStaffRuleException;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Notifications\VendorStaffInvitationNotification;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\Auditor;
use App\Modules\Platform\Services\StoreSettingsService;
use App\Shared\Services\BaseService;
use App\Shared\Support\Ui;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use LogicException;

/**
 * Store team (VEN-076 / 076a, Figma 90:115176) in the current tenant DB:
 *  - invite: a pending member with an unusable random password + a set-password invitation mail
 *    (the vendor_staff reset broker); setting the password activates them
 *    (ActivateInvitedVendorStaff → VendorStaffJoined → owners notified);
 *  - edit name / e-mail / phone / role, activate / deactivate, delete, resend invite.
 * Rules: owners (is_owner / role "owner") are untouchable, nobody acts on their own
 * account here, the "owner" role is never granted, and the plan's staff quota
 * (PlanLimit::Staff, owners included — same count as the subscription usage card).
 */
class VendorStaffService extends BaseService
{
    public function __construct(
        private readonly VendorStaffRepositoryContract $staff,
        private readonly EffectivePlanLimits $planLimits,
        private readonly Auditor $auditor,
    ) {}

    public function invite(VendorStaffData $data, VendorStaff $actor): VendorStaff
    {
        $this->guardRole($data->role);
        $limit = $this->limit();

        if ($limit !== null && $this->staff->query()->count() >= $limit) {
            throw VendorStaffRuleException::limitReached($limit);
        }

        /** @var VendorStaff $member */
        $member = $this->transaction(function () use ($data): VendorStaff {
            /** @var VendorStaff $member */
            $member = $this->staff->create([
                'name' => $data->name,
                'email' => mb_strtolower($data->email),
                'phone' => $data->phone,
                /* Unusable until the invitation sets a real one (pending members cannot sign in). */
                'password' => Str::random(64),
                'status' => AccountStatus::Pending,
                'locale' => $data->locale ?? app()->getLocale(),
                'is_owner' => false,
            ]);
            $member->assignRole($data->role);

            return $member;
        }, $this->connection());

        $this->auditor->record('staff.invited', $member, ['after' => ['email' => $member->email, 'role' => $data->role]]);
        $this->sendInvitation($member, $actor);

        return $member;
    }

    public function update(VendorStaff $member, VendorStaffData $data, VendorStaff $actor): VendorStaff
    {
        $this->guardTarget($member, $actor);
        $this->guardRole($data->role);

        $before = ['name' => $member->name, 'email' => $member->email, 'phone' => $member->phone, 'role' => $this->roleOf($member)];
        $emailChanged = mb_strtolower($data->email) !== $member->email;

        $this->transaction(function () use ($member, $data, $emailChanged): void {
            $attributes = ['name' => $data->name, 'email' => mb_strtolower($data->email), 'phone' => $data->phone];

            if ($emailChanged && $member->status !== AccountStatus::Pending) {
                $attributes['email_verified_at'] = null;
            }

            $member->forceFill($attributes)->save();
            $member->syncRoles([$data->role]);
        }, $this->connection());

        $after = ['name' => $member->name, 'email' => $member->email, 'phone' => $member->phone, 'role' => $data->role];

        if ($before !== $after) {
            $this->auditor->record('staff.updated', $member, ['before' => $before, 'after' => $after]);
        }

        if ($emailChanged && $member->status === AccountStatus::Pending) {
            $this->sendInvitation($member, $actor);
        }

        return $member;
    }

    public function setActive(VendorStaff $member, bool $active, VendorStaff $actor): VendorStaff
    {
        $this->guardTarget($member, $actor);

        if ($member->status === AccountStatus::Pending) {
            throw VendorStaffRuleException::pending();
        }

        $status = $active ? AccountStatus::Active : AccountStatus::Suspended;

        if ($member->status !== $status) {
            $before = $member->status->value;
            $member->forceFill(['status' => $status])->save();
            $this->auditor->record('staff.status_changed', $member, ['before' => ['status' => $before], 'after' => ['status' => $status->value]]);
        }

        return $member;
    }

    public function delete(VendorStaff $member, VendorStaff $actor): void
    {
        $this->guardTarget($member, $actor);

        $before = ['name' => $member->name, 'email' => $member->email, 'role' => $this->roleOf($member)];

        $this->transaction(function () use ($member): void {
            $member->syncRoles([]);
            $member->syncPermissions([]);
            $this->staff->delete($member);
        }, $this->connection());

        $this->auditor->record('staff.deleted', $member, ['before' => $before]);
    }

    public function resendInvitation(VendorStaff $member, VendorStaff $actor): void
    {
        $this->guardTarget($member, $actor);

        if ($member->status !== AccountStatus::Pending) {
            throw VendorStaffRuleException::notPending();
        }

        $this->sendInvitation($member, $actor);
    }

    public function roleOf(VendorStaff $member): ?string
    {
        $role = $member->getRoleNames()->first();

        return is_string($role) ? $role : null;
    }

    public function isLocked(VendorStaff $member): bool
    {
        return $member->is_owner || $member->hasRole(SystemRole::VENDOR_OWNER, Guard::Vendor->value);
    }

    /**
     * Staff quota of the store: plan or admin override (EffectivePlanLimits; null =
     * unlimited / no plan).
     */
    public function limit(): ?int
    {
        $limit = $this->planLimits->for(PlanLimit::Staff, (int) $this->tenant()->id);

        return is_int($limit) ? $limit : null;
    }

    /**
     * @return array{all: int, active: int, pending: int, suspended: int}
     */
    public function counts(): array
    {
        return $this->staff->counts();
    }

    public function find(int $id): VendorStaff
    {
        $member = $this->staff->find($id);
        abort_unless($member instanceof VendorStaff, 404);

        return $member;
    }

    private function sendInvitation(VendorStaff $member, VendorStaff $actor): void
    {
        $broker = Password::broker('vendor_staff');

        if (! $broker instanceof PasswordBroker) {
            throw new LogicException('The vendor_staff password broker is not configured.');
        }

        $tenant = $this->tenant();
        $token = $broker->createToken($member);
        $url = StoreSettingsService::storeUrl($tenant).'/invitation/'.$token.'?'.http_build_query(['email' => $member->email]);

        $member->notify(new VendorStaffInvitationNotification(
            (string) $tenant->name,
            $actor->name,
            (string) Ui::roleLabel($this->roleOf($member)),
            $url,
            (int) config('auth.passwords.vendor_staff.expire', 60),
        ));
    }

    private function guardTarget(VendorStaff $member, VendorStaff $actor): void
    {
        if ($this->isLocked($member)) {
            throw VendorStaffRuleException::ownerLocked();
        }

        if ($member->is($actor)) {
            throw VendorStaffRuleException::self();
        }
    }

    private function guardRole(string $role): void
    {
        if (SystemRole::lockedFor($role, Guard::Vendor->value)) {
            throw VendorStaffRuleException::ownerRole();
        }
    }

    private function tenant(): Tenant
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            throw new LogicException('The store team is managed inside a tenant context.');
        }

        return $tenant;
    }

    private function connection(): ?string
    {
        return $this->staff->query()->getModel()->getConnectionName();
    }
}

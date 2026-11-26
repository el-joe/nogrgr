<?php

namespace App\Modules\Affiliates\Services;

use App\Modules\Affiliates\DTOs\AffiliateUpdateData;
use App\Modules\Affiliates\Enums\AffiliateType;
use App\Modules\Affiliates\Enums\RateType;
use App\Modules\Affiliates\Events\AffiliateActivated;
use App\Modules\Affiliates\Repositories\Contracts\AffiliateRepositoryContract;
use App\Modules\Affiliates\Support\CommissionCalculator;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Affiliate;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Services\BaseService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Affiliate accounts (ADM-060 / 060a):
 *   createForTenant()  every provisioned store gets an active "vendor" affiliate (once);
 *                      it signs in through the vendor panel SSO, so its password is
 *                      random and the e-mail is the owner's when no affiliate uses it yet
 *   update()           own commission rate (null = program default) + status; the first
 *                      activation stamps approved_at and fires AffiliateActivated
 *   commissionFor()    the rate a new conversion snapshots
 */
class AffiliateAccountService extends BaseService
{
    public function __construct(
        private readonly AffiliateRepositoryContract $affiliates,
        private readonly ProgramSettingsService $settings,
    ) {}

    public function createForTenant(Tenant $tenant): Affiliate
    {
        $existing = $this->affiliates->findForTenant($tenant->id);

        if ($existing !== null) {
            return $existing;
        }

        $email = filled($tenant->owner_email) ? Str::lower((string) $tenant->owner_email) : null;

        return $this->transaction(function () use ($tenant, $email): Affiliate {
            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::withoutTenantScope()->create([
                'tenant_id' => $tenant->id,
                'type' => AffiliateType::Vendor,
                'code' => $this->generateCode(),
                'name' => (string) $tenant->name,
                'email' => $email !== null && ! $this->affiliates->emailTaken($email) ? $email : null,
                'password' => Hash::make(Str::random(48)),
                'status' => AccountStatus::Active,
                'approved_at' => now(),
            ]);

            return $affiliate;
        }, (new Affiliate)->getConnectionName());
    }

    public function update(Affiliate $affiliate, AffiliateUpdateData $data): Affiliate
    {
        $activated = $data->status === AccountStatus::Active && $affiliate->status !== AccountStatus::Active;
        $first = $activated && $affiliate->approved_at === null;

        $this->affiliates->update($affiliate, array_filter([
            'commission_type' => $data->commissionType,
            'commission_value' => $data->commissionType !== null ? $data->commissionValue : null,
            'status' => $data->status,
            'approved_at' => $first ? now() : null,
        ], static fn (mixed $value, string $key): bool => $value !== null || in_array($key, ['commission_type', 'commission_value'], true), ARRAY_FILTER_USE_BOTH));

        if ($activated) {
            $this->dispatchAfterCommit(new AffiliateActivated($affiliate->refresh()));
        }

        return $affiliate;
    }

    /**
     * @return array{0: RateType, 1: int}
     */
    public function commissionFor(Affiliate $affiliate): array
    {
        if ($affiliate->commission_type !== null && $affiliate->commission_value !== null) {
            return [$affiliate->commission_type, $affiliate->commission_value];
        }

        return $this->settings->defaultCommission();
    }

    public function commissionLabel(Affiliate $affiliate): string
    {
        [$type, $value] = $this->commissionFor($affiliate);

        return CommissionCalculator::label($type, $value);
    }

    public function generateCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while ($this->affiliates->codeExists($code));

        return $code;
    }
}

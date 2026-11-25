<?php

namespace App\Modules\Platform\Services;

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Services\VendorImpersonationService;
use App\Modules\Platform\DTOs\ImpersonationData;
use App\Modules\Platform\DTOs\VendorCommissionData;
use App\Modules\Platform\DTOs\VendorStoreData;
use App\Modules\Platform\Enums\AuditAction;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Exceptions\PlatformRuleException;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Admin actions on a store: edit store info, profit / commission, activate, suspend,
 * delete (drops the store database) and audited impersonation.
 */
class VendorService extends BaseService
{
    public function __construct(
        private readonly TenantRepositoryContract $tenants,
        private readonly Auditor $auditor,
        private readonly VendorImpersonationService $impersonation,
    ) {}

    /**
     * @return array<string, int> all + per listed status
     */
    public function counts(): array
    {
        $byStatus = $this->tenants->countsByStatus();
        $counts = ['all' => array_sum($byStatus)];

        foreach (TenantStatus::listed() as $status) {
            $counts[$status->value] = $byStatus[$status->value] ?? 0;
        }

        return $counts;
    }

    /**
     * Vendors list tabs: counts() plus stores whose current subscription is a trial.
     *
     * @return array<string, int>
     */
    public function tabCounts(): array
    {
        return [
            ...$this->counts(),
            SubscriptionStatus::Trial->value => $this->tenants->listing(['subscription_status' => SubscriptionStatus::Trial->value])->count(),
        ];
    }

    public function updateStore(Tenant $tenant, VendorStoreData $data): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = $this->tenants->update($tenant, [
            'name' => $data->name,
            'owner_name' => $data->ownerName,
            'owner_email' => Str::lower($data->ownerEmail),
            'owner_phone' => $data->ownerPhone,
            'default_country_id' => $data->defaultCountryId,
            'selling_country_ids' => $data->sellingCountryIds === [] ? null : array_values(array_map('intval', $data->sellingCountryIds)),
        ]);

        return $tenant;
    }

    public function updateCommission(Tenant $tenant, VendorCommissionData $data): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = $this->tenants->update($tenant, [
            'profit_percent_bps' => $data->profitPercentBps,
            'affiliate_commission_bps' => $data->affiliateCommissionBps,
        ]);

        return $tenant;
    }

    public function activate(Tenant $tenant): Tenant
    {
        $this->guard($tenant, TenantStatus::Active);

        /** @var Tenant $tenant */
        $tenant = $this->tenants->update($tenant, ['status' => TenantStatus::Active, 'suspended_at' => null]);

        return $tenant;
    }

    public function suspend(Tenant $tenant): Tenant
    {
        $this->guard($tenant, TenantStatus::Suspended);

        /** @var Tenant $tenant */
        $tenant = $this->tenants->update($tenant, ['status' => TenantStatus::Suspended, 'suspended_at' => now()]);

        return $tenant;
    }

    /**
     * Deletes the store: central rows cascade (domains, requests, compliance) and
     * stancl's TenantDeleted pipeline drops the store database.
     */
    public function delete(Tenant $tenant): void
    {
        $this->auditor->record(AuditAction::Deleted, $tenant, ['name' => $tenant->name, 'slug' => $tenant->slug]);
        $this->tenants->delete($tenant);
    }

    /**
     * Records why the admin opens the store panel, then signs in as the owner.
     * Returns the vendor panel URL.
     */
    public function impersonate(Admin $admin, Tenant $tenant, ImpersonationData $data, Request $request): string
    {
        if (! $tenant->status->isOperational() && $tenant->status !== TenantStatus::Suspended) {
            throw PlatformRuleException::noOwner();
        }

        $this->auditor->record(AuditAction::Impersonated, $tenant, [
            'reason' => $data->reason->value,
            'details' => $data->details,
        ]);

        try {
            return $this->impersonation->start($admin, $tenant, $request);
        } catch (NotFoundHttpException) {
            throw PlatformRuleException::noOwner();
        }
    }

    private function guard(Tenant $tenant, TenantStatus $next): void
    {
        if (! $tenant->status->canTransitionTo($next)) {
            throw PlatformRuleException::invalidTransition($tenant->status->label(), $next->label());
        }
    }
}

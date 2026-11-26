<?php

namespace App\Modules\Identity\Services;

use App\Modules\Commerce\Support\TenantRunner;
use App\Modules\Identity\DTOs\PermissionSyncReport;
use App\Modules\Identity\DTOs\PermissionSyncResult;
use App\Modules\Identity\Enums\Guard;
use App\Modules\Identity\Enums\SystemRole;
use App\Modules\Identity\Repositories\Contracts\RoleRepositoryContract;
use App\Modules\Identity\Support\PermissionCatalog;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Shared\Services\BaseService;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Brings the spatie permission tables in line with PermissionCatalog (QA-P5-019).
 * Central: every catalog permission exists; super-admin holds every admin permission and
 * affiliate every affiliate one. Store: every catalog permission exists; the owner role
 * holds every vendor permission. Custom roles are never touched. Idempotent; run by the
 * seeders, by provisioning (through the tenant seeder) and on every deploy.
 */
class PermissionSyncService extends BaseService
{
    /** Stores whose database exists (pending / provisioning / failed have none). */
    private const STATUSES = [TenantStatus::Active, TenantStatus::Trial, TenantStatus::Suspended, TenantStatus::Cancelled];

    public function __construct(
        private readonly RoleRepositoryContract $roles,
        private readonly TenantRepositoryContract $tenants,
        private readonly TenantRunner $runner,
        private readonly PermissionRegistrar $registrar,
    ) {}

    /**
     * @param  list<int>  $tenantIds  only these stores (empty = all)
     */
    public function run(array $tenantIds = [], bool $central = true): PermissionSyncReport
    {
        $report = new PermissionSyncReport;

        if ($central) {
            $result = $this->central();
            $report->centralCreated = $result->created;
            $report->centralGranted = $result->granted;
        }

        $this->tenants->query()
            ->whereIn('status', array_map(static fn (TenantStatus $status): string => $status->value, self::STATUSES))
            ->when($tenantIds !== [], static fn ($query) => $query->whereKey($tenantIds))
            ->orderBy('id')
            ->each(function (Tenant $tenant) use ($report): void {
                try {
                    $report->add($this->runner->run((int) $tenant->id, fn (): PermissionSyncResult => $this->tenant()));
                } catch (Throwable $exception) {
                    report($exception);
                    $report->failures[(int) $tenant->id] = $exception->getMessage();
                }
            });

        return $report;
    }

    /**
     * The central database (current connection).
     */
    public function central(): PermissionSyncResult
    {
        return $this->sync([
            [SystemRole::SuperAdmin->value, Guard::Admin->value],
            ['affiliate', Guard::Affiliate->value],
        ], PermissionCatalog::central());
    }

    /**
     * The store database of the current tenant context.
     */
    public function tenant(): PermissionSyncResult
    {
        return $this->sync([['owner', Guard::Vendor->value]], PermissionCatalog::tenant());
    }

    /**
     * @param  list<array{0: string, 1: string}>  $systemRoles  [role name, guard]
     * @param  array<string, list<string>>  $catalog  guard => permission names
     */
    private function sync(array $systemRoles, array $catalog): PermissionSyncResult
    {
        $this->registrar->forgetCachedPermissions();
        $created = 0;
        $granted = 0;

        foreach ($catalog as $guard => $permissions) {
            $created += $this->roles->ensurePermissions($permissions, $guard);
        }

        foreach ($systemRoles as [$role, $guard]) {
            $granted += $this->roles->syncRole($role, $guard, $catalog[$guard] ?? []);
        }

        $this->registrar->forgetCachedPermissions();

        return new PermissionSyncResult($created, $granted);
    }
}

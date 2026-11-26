<?php

namespace App\Modules\Catalog\Services\Requests;

use App\Modules\Catalog\DTOs\Requests\TenantChangeSubmissionData;
use App\Modules\Catalog\Enums\TenantChangeStatus;
use App\Modules\Catalog\Enums\TenantChangeType;
use App\Modules\Catalog\Events\Requests\TenantChangeReviewed;
use App\Modules\Catalog\Exceptions\RequestRuleException;
use App\Modules\Catalog\Models\Requests\TenantChangeRequest;
use App\Modules\Catalog\Repositories\Contracts\CategoryRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\TenantChangeRequestRepositoryContract;
use App\Modules\Identity\Models\Admin;
use App\Modules\Localization\Repositories\Contracts\CountryRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Store change requests (ADM-018i): the vendor asks to change its selling countries or
 * categories; approval writes the requested list to the tenant (selling_country_ids /
 * selling_category_ids), rejection keeps the store unchanged. Both carry an admin note.
 */
class TenantChangeService extends BaseService
{
    public function __construct(
        private readonly TenantChangeRequestRepositoryContract $changes,
        private readonly TenantRepositoryContract $tenants,
        private readonly CountryRepositoryContract $countries,
        private readonly CategoryRepositoryContract $categories,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<TenantChangeRequest>
     */
    public function listing(array $filters = []): Builder
    {
        return $this->changes->listing($filters);
    }

    public function pendingCount(): int
    {
        return $this->changes->pendingCount();
    }

    public function findOrFail(int $id): TenantChangeRequest
    {
        /** @var TenantChangeRequest $request */
        $request = $this->changes->findOrFail($id);

        return $request;
    }

    /**
     * The store's open request of one type (vendor Settings › General pending state).
     */
    public function pendingFor(int $tenantId, TenantChangeType $type): ?TenantChangeRequest
    {
        return $this->changes->pendingFor($tenantId, $type->value);
    }

    /**
     * Vendor API (vendor panel "Change requests"): one pending request per store and type.
     */
    public function submit(TenantChangeSubmissionData $data): TenantChangeRequest
    {
        /** @var Tenant $tenant */
        $tenant = $this->tenants->findOrFail($data->tenantId);

        if ($this->changes->pendingFor($tenant->id, $data->type->value) !== null) {
            throw RequestRuleException::pendingExists();
        }

        $current = $this->currentIds($tenant, $data->type);
        $requested = array_values(array_unique(array_map('intval', $data->requestedIds)));
        sort($requested);

        if ($requested === $current) {
            throw RequestRuleException::noChanges();
        }

        /** @var TenantChangeRequest $request */
        $request = $this->changes->create([
            'tenant_id' => $tenant->id,
            'type' => $data->type,
            'requested_ids' => $requested,
            'current_ids' => $current,
            'vendor_note' => $data->vendorNote,
            'submitted_by' => $data->submittedBy,
            'status' => TenantChangeStatus::Pending,
        ]);

        return $request;
    }

    public function approve(TenantChangeRequest $request, Admin $admin, ?string $note): TenantChangeRequest
    {
        $this->guardPending($request);

        $this->transaction(function () use ($request, $admin, $note): void {
            /** @var Tenant $tenant */
            $tenant = $this->tenants->findOrFail($request->tenant_id);
            $this->tenants->update($tenant, [$request->type->tenantAttribute() => array_values(array_map('intval', $request->requested_ids))]);
            $this->review($request, $admin, TenantChangeStatus::Approved, $note);
        }, $request->getConnectionName());

        return $request;
    }

    public function reject(TenantChangeRequest $request, Admin $admin, string $note): TenantChangeRequest
    {
        $this->guardPending($request);
        $this->review($request, $admin, TenantChangeStatus::Rejected, $note);

        return $request;
    }

    /**
     * Names of countries / categories, keyed by id, in the current locale.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function names(TenantChangeType $type, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $query = $type === TenantChangeType::Countries ? $this->countries->query() : $this->categories->query();

        return $query->whereIn('id', $ids)->get()->mapWithKeys(static fn (Model $model): array => [(int) $model->getKey() => (string) $model->getAttribute('name')])->all();
    }

    /**
     * @return list<int>
     */
    private function currentIds(Tenant $tenant, TenantChangeType $type): array
    {
        $ids = array_values(array_unique(array_map('intval', (array) $tenant->getAttribute($type->tenantAttribute()))));
        sort($ids);

        return $ids;
    }

    private function review(TenantChangeRequest $request, Admin $admin, TenantChangeStatus $status, ?string $note): void
    {
        $this->changes->update($request, [
            'status' => $status,
            'admin_note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
        $this->dispatchAfterCommit(new TenantChangeReviewed($request));
    }

    private function guardPending(TenantChangeRequest $request): void
    {
        if (! $request->isPending()) {
            throw RequestRuleException::alreadyReviewed();
        }
    }
}

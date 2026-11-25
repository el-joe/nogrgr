<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Inventory\DTOs\BranchData;
use App\Modules\Inventory\Models\Branch;
use App\Modules\Inventory\Repositories\Contracts\BranchRepositoryContract;
use App\Modules\Inventory\Repositories\Contracts\BranchStockRepositoryContract;
use App\Shared\Services\BaseService;
use DomainException;
use Illuminate\Database\Eloquent\Collection;

/**
 * Branches (ADM-030 / 030a): CRUD, default branch per country (one per country, moved
 * from the previous branch), served zones, and the guard against deleting a branch
 * that still holds stock.
 */
class BranchService extends BaseService
{
    public function __construct(
        private readonly BranchRepositoryContract $branches,
        private readonly BranchStockRepositoryContract $stock,
        private readonly CatalogLocales $locales,
    ) {}

    /**
     * @return Collection<int, Branch>
     */
    public function active(): Collection
    {
        return $this->branches->active();
    }

    /**
     * @return array{active: int, all: int}
     */
    public function counts(): array
    {
        return $this->branches->counts();
    }

    /**
     * @return array{skus: int, units: int}
     */
    public function totals(Branch $branch): array
    {
        return $this->stock->branchTotals($branch->id);
    }

    public function create(BranchData $data): Branch
    {
        return $this->transaction(function () use ($data): Branch {
            $branch = $this->branches->create($this->columns($data));
            $this->syncRelations($branch, $data);

            return $branch;
        }, $this->connection());
    }

    public function update(Branch $branch, BranchData $data): Branch
    {
        return $this->transaction(function () use ($branch, $data): Branch {
            $this->branches->update($branch, $this->columns($data));
            $this->syncRelations($branch, $data);

            return $branch;
        }, $this->connection());
    }

    public function delete(Branch $branch): void
    {
        if ($this->stock->branchTotals($branch->id)['units'] > 0) {
            throw new DomainException(__('inventory::branches.delete_has_stock', ['name' => $branch->name]));
        }

        $this->transaction(function () use ($branch): void {
            $this->branches->syncDefaultCountries($branch, []);
            $this->branches->delete($branch);
        }, $this->connection());
    }

    private function syncRelations(Branch $branch, BranchData $data): void
    {
        $this->branches->syncDefaultCountries($branch, $data->isActive ? array_values(array_unique($data->defaultCountryIds)) : []);
        $this->branches->syncZones($branch, array_values(array_unique($data->zoneCityIds)));
    }

    /**
     * @return array<string, mixed>
     */
    private function columns(BranchData $data): array
    {
        return [
            'name' => $this->locales->forStorage($data->name),
            'code' => strtoupper($data->code),
            'country_id' => $data->countryId,
            'city_id' => $data->cityId,
            'address' => $data->address,
            'phone' => $data->phone,
            'pickup_hours' => $data->pickupHours,
            'allows_pickup' => $data->allowsPickup,
            'is_active' => $data->isActive,
        ];
    }

    private function connection(): ?string
    {
        return (new Branch)->getConnectionName();
    }
}

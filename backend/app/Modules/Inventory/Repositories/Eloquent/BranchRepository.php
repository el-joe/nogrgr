<?php

namespace App\Modules\Inventory\Repositories\Eloquent;

use App\Modules\Inventory\Models\Branch;
use App\Modules\Inventory\Repositories\Contracts\BranchRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<Branch>
 */
class BranchRepository extends BaseRepository implements BranchRepositoryContract
{
    protected function model(): string
    {
        return Branch::class;
    }

    public function active(): Collection
    {
        return $this->query()->where('is_active', true)->orderBy('code')->get();
    }

    public function counts(): array
    {
        return [
            'active' => $this->query()->where('is_active', true)->count(),
            'all' => $this->query()->count(),
        ];
    }

    public function syncDefaultCountries(Branch $branch, array $countryIds): void
    {
        $table = DB::connection($branch->getConnectionName())->table('branch_default_countries');
        $table->clone()->where('branch_id', $branch->id)->whereNotIn('country_id', $countryIds)->delete();

        foreach ($countryIds as $countryId) {
            $table->clone()->updateOrInsert(['country_id' => $countryId], ['branch_id' => $branch->id]);
        }
    }

    public function syncZones(Branch $branch, array $zoneIds): void
    {
        $branch->zones()->sync($zoneIds);
    }
}

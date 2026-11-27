<?php

namespace App\Modules\Inventory\Repositories\Eloquent;

use App\Modules\Inventory\Models\Branch;
use App\Modules\Inventory\Repositories\Contracts\BranchRepositoryContract;
use App\Modules\Localization\Models\Country;
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

    public function activeDefault(?int $countryId): ?Branch
    {
        $defaults = DB::connection((new Branch)->getConnectionName())->table('branch_default_countries');
        $query = fn () => $this->query()->where('is_active', true)->orderBy('code');

        if ($countryId !== null) {
            $branchId = $defaults->clone()->where('country_id', $countryId)->value('branch_id');
            /** @var Branch|null $branch */
            $branch = $branchId !== null ? $query()->whereKey($branchId)->first() : null;

            if ($branch !== null) {
                return $branch;
            }
        }

        /** @var Branch|null $branch */
        $branch = $query()->whereIn('id', $defaults->clone()->select('branch_id'))->first();

        return $branch;
    }

    public function markDefaultIfMissing(Branch $branch): void
    {
        DB::connection($branch->getConnectionName())->table('branch_default_countries')->insertOrIgnore(['country_id' => $branch->country_id, 'branch_id' => $branch->id]);
    }

    public function freeCode(string $base): string
    {
        $code = $base;

        for ($suffix = 2; $this->query()->withTrashed()->where('code', $code)->exists(); $suffix++) {
            $code = $base.'-'.$suffix;
        }

        return $code;
    }

    public function platformCountryId(): ?int
    {
        $countries = Country::query()->where('is_active', true);
        $id = (clone $countries)->where('iso2', strtoupper((string) config('app.default_country')))->value('id')
            ?? (clone $countries)->orderBy('id')->value('id');

        return $id !== null ? (int) $id : null;
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

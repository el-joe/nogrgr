<?php

namespace App\Modules\Catalog\Repositories\Eloquent;

use App\Modules\Catalog\Enums\ImportDriver;
use App\Modules\Catalog\Enums\ImportLinkType;
use App\Modules\Catalog\Enums\ImportLogResult;
use App\Modules\Catalog\Enums\ImportStatus;
use App\Modules\Catalog\Models\CatalogImportCategoryMap;
use App\Modules\Catalog\Models\CatalogImportLink;
use App\Modules\Catalog\Models\CatalogImportLog;
use App\Modules\Catalog\Models\CatalogImportRun;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Repositories\Contracts\CatalogImportRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<CatalogImportRun>
 */
class CatalogImportRepository extends BaseRepository implements CatalogImportRepositoryContract
{
    protected function model(): string
    {
        return CatalogImportRun::class;
    }

    public function activeRun(ImportDriver $driver): ?CatalogImportRun
    {
        return $this->query()->where('driver', $driver->value)->whereIn('status', ImportStatus::activeValues())->latest('id')->first();
    }

    public function latestRun(ImportDriver $driver): ?CatalogImportRun
    {
        return $this->query()->with('starter')->where('driver', $driver->value)->latest('id')->first();
    }

    public function lastCompletedRun(ImportDriver $driver): ?CatalogImportRun
    {
        return $this->query()->where('driver', $driver->value)->where('status', ImportStatus::Completed->value)->latest('finished_at')->first();
    }

    public function increment(CatalogImportRun $run, array $amounts): void
    {
        $amounts = array_filter($amounts, static fn (int $amount): bool => $amount !== 0);

        if ($amounts === []) {
            return;
        }

        $updates = ['updated_at' => now()];

        foreach ($amounts as $column => $amount) {
            $updates[$column] = DB::raw('`'.str_replace('`', '', $column).'` + '.(int) $amount);
        }

        $this->query()->whereKey($run->id)->toBase()->update($updates);
    }

    public function log(CatalogImportRun $run, ImportLogResult $result, string $message, array $item = []): CatalogImportLog
    {
        return CatalogImportLog::query()->create([
            'run_id' => $run->id,
            'result' => $result,
            'message' => mb_substr($message, 0, 500),
            'external_id' => $item['external_id'] ?? null,
            'sku' => isset($item['sku']) ? mb_substr((string) $item['sku'], 0, 191) : null,
            'item_name' => isset($item['item_name']) ? mb_substr((string) $item['item_name'], 0, 191) : null,
            'product_id' => $item['product_id'] ?? null,
        ]);
    }

    public function logsQuery(CatalogImportRun $run): Builder
    {
        return CatalogImportLog::query()->where('run_id', $run->id);
    }

    public function historyQuery(): Builder
    {
        return $this->query()->with('starter');
    }

    public function linkedId(ImportDriver $driver, ImportLinkType $type, int $externalId): ?int
    {
        $id = $this->links($driver, $type)->where('external_id', $externalId)->value('local_id');

        return $id !== null ? (int) $id : null;
    }

    public function checksum(ImportDriver $driver, ImportLinkType $type, int $externalId): ?string
    {
        $checksum = $this->links($driver, $type)->where('external_id', $externalId)->value('checksum');

        return is_string($checksum) ? $checksum : null;
    }

    public function linkedIds(ImportDriver $driver, ImportLinkType $type, array $externalIds): array
    {
        if ($externalIds === []) {
            return [];
        }

        return $this->links($driver, $type)->whereIn('external_id', $externalIds)->pluck('local_id', 'external_id')
            ->mapWithKeys(static fn (mixed $local, mixed $external): array => [(int) $external => (int) $local])->all();
    }

    public function externalIdsFor(ImportDriver $driver, ImportLinkType $type, array $localIds): array
    {
        if ($localIds === []) {
            return [];
        }

        return $this->links($driver, $type)->whereIn('local_id', $localIds)->pluck('external_id', 'local_id')
            ->mapWithKeys(static fn (mixed $external, mixed $local): array => [(int) $local => (int) $external])->all();
    }

    public function saveLink(ImportDriver $driver, ImportLinkType $type, int $externalId, int $localId, ?string $checksum = null): void
    {
        CatalogImportLink::query()->updateOrCreate(
            ['driver' => $driver->value, 'type' => $type->value, 'external_id' => $externalId],
            ['local_id' => $localId, 'checksum' => $checksum],
        );
    }

    public function discoverCategories(ImportDriver $driver, array $categories): void
    {
        foreach ($categories as $category) {
            $map = CatalogImportCategoryMap::query()->firstOrNew(['driver' => $driver->value, 'external_id' => $category->externalId]);

            if (! $map->exists) {
                $map->category_id = Category::query()->where('external_source', $driver->value)->where('external_id', $category->externalId)->value('id');
            }

            $map->fill(['name' => $category->name, 'parent_external_id' => $category->parentExternalId])->save();
        }
    }

    public function categoryMapping(ImportDriver $driver, array $externalIds): array
    {
        if ($externalIds === []) {
            return [];
        }

        $mapped = CatalogImportCategoryMap::query()
            ->where('driver', $driver->value)
            ->whereIn('external_id', $externalIds)
            ->whereHas('category')
            ->pluck('category_id', 'external_id')
            ->all();
        $result = [];

        foreach ($externalIds as $externalId) {
            $result[$externalId] = isset($mapped[$externalId]) ? (int) $mapped[$externalId] : null;
        }

        return $result;
    }

    public function categoryMapsQuery(ImportDriver $driver): Builder
    {
        return CatalogImportCategoryMap::query()->with('category')->where('driver', $driver->value);
    }

    public function unmappedCount(ImportDriver $driver): int
    {
        return CatalogImportCategoryMap::query()->where('driver', $driver->value)->whereDoesntHave('category')->count();
    }

    public function categoryMapCount(ImportDriver $driver): int
    {
        return CatalogImportCategoryMap::query()->where('driver', $driver->value)->count();
    }

    public function saveCategoryMappings(ImportDriver $driver, array $mappings): void
    {
        foreach ($mappings as $mapId => $categoryId) {
            CatalogImportCategoryMap::query()->where('driver', $driver->value)->whereKey($mapId)->update(['category_id' => $categoryId, 'updated_at' => now()]);
        }
    }

    /**
     * @return Builder<CatalogImportLink>
     */
    private function links(ImportDriver $driver, ImportLinkType $type): Builder
    {
        return CatalogImportLink::query()->where('driver', $driver->value)->where('type', $type->value);
    }
}

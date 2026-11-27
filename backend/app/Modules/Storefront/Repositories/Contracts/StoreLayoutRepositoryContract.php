<?php

namespace App\Modules\Storefront\Repositories\Contracts;

use App\Modules\Storefront\Models\StoreLayout;
use App\Modules\Storefront\Models\StoreLayoutVersion;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Page-builder layouts + versions (tenant DB, VEN-032).
 *
 * @extends RepositoryContract<StoreLayout>
 */
interface StoreLayoutRepositoryContract extends RepositoryContract
{
    public function findBySlot(string $slot): ?StoreLayout;

    /**
     * @return Collection<int, StoreLayoutVersion> newest first
     */
    public function versions(StoreLayout $layout, int $limit = 30): Collection;

    public function findVersion(StoreLayout $layout, int $versionId): ?StoreLayoutVersion;

    /**
     * @param  list<array<string, mixed>>  $sections
     */
    public function addVersion(StoreLayout $layout, array $sections, ?int $staffId, ?string $staffName): StoreLayoutVersion;

    /**
     * Keep only the newest $keep versions (never the published one).
     */
    public function pruneVersions(StoreLayout $layout, int $keep): void;
}
